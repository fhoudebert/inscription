<?php
/*
 * Formulaire d'inscription générique — bibliothèque commune.
 * PHP 7.4 ou plus, aucune dépendance, aucune base de données, aucune session PHP.
 *
 * Ce fichier est inclus par index.php et admin.php ; il ne doit pas être appelé directement.
 */
declare(strict_types=1);

if (!defined('INS_PRIVE')) {
    http_response_code(403);
    exit;
}

const INS_COLONNES_FIXES = ['numero', 'date'];
// Noms interdits pour les champs (colonnes ajoutées automatiquement)
const INS_NOMS_RESERVES = ['numero', 'date', 'langue', 'acces', 'acces_nom'];
// Colonnes du fichier des accès personnels (prive/donnees/acces/<fichier>.csv)
const INS_COLONNES_ACCES = ['identifiant', 'nom', 'type', 'secret', 'etat', 'cree', 'repondu', 'modifie', 'echecs', 'bloque'];
const INS_ACCES_ECHECS_MAX = 10;      // essais de mot de passe avant blocage temporaire
const INS_ACCES_BLOCAGE = 15 * 60;    // durée du blocage (secondes)
const INS_ACCES_DUREE = 2 * 3600;     // durée de la connexion après saisie du mot de passe
// Textes traduisibles : « libelle_en = ... » donne la traduction anglaise de « libelle »
const INS_TRAD_FORMULAIRE = ['titre', 'intro', 'merci', 'bouton'];
const INS_TRAD_CHAMP = ['libelle', 'aide', 'exemple', 'gauche', 'droite', 'options'];
const INS_TYPES = [
    'texte', 'texte_long', 'email', 'telephone', 'nombre', 'date',
    'choix', 'liste', 'cases', 'case', 'echelle', 'titre', 'info',
];
// Types qui ne produisent pas de colonne dans le fichier de données
const INS_TYPES_DECORATIFS = ['titre', 'info'];
// Écritures alternatives (françaises et anglaises) acceptées pour les types
const INS_ALIAS_TYPES = [
    'text' => 'texte', 'textarea' => 'texte_long', 'zone' => 'texte_long', 'paragraphe' => 'texte_long',
    'longtext' => 'texte_long', 'long_text' => 'texte_long',
    'mail' => 'email', 'courriel' => 'email', 'tel' => 'telephone', 'téléphone' => 'telephone', 'phone' => 'telephone',
    'number' => 'nombre', 'radio' => 'choix', 'choice' => 'choix', 'select' => 'liste', 'list' => 'liste',
    'dropdown' => 'liste', 'checkbox' => 'cases', 'checkboxes' => 'cases',
    'consentement' => 'case', 'case_unique' => 'case', 'consent' => 'case',
    'note' => 'echelle', 'échelle' => 'echelle', 'scale' => 'echelle', 'rating' => 'echelle',
    'section' => 'titre', 'title' => 'titre', 'heading' => 'titre', 'texte_info' => 'info',
];
// Clés acceptées dans les descripteurs : nom interne => écritures possibles (français, anglais)
const INS_CLES_FORMULAIRE = [
    'titre' => ['titre', 'title'], 'intro' => ['intro', 'introduction'], 'merci' => ['merci', 'thanks'],
    'bouton' => ['bouton', 'button'], 'fichier' => ['fichier', 'file'],
    'ouverture' => ['ouverture', 'opens'], 'fermeture' => ['fermeture', 'closes'],
    'places' => ['places', 'limit'], 'notifier' => ['notifier', 'notify'],
    'diffusion' => ['diffusion', 'listed'], 'afficher_numero' => ['afficher_numero', 'show_number'],
    'langue' => ['langue', 'language'], 'langues' => ['langues', 'languages'], 'cache' => ['cache', 'hidden'],
    'acces' => ['acces', 'accès', 'access'], 'modifiable' => ['modifiable', 'editable'],
];
const INS_CLES_CHAMP = [
    'type' => ['type'], 'libelle' => ['libelle', 'label', 'texte'], 'aide' => ['aide', 'help'],
    'obligatoire' => ['obligatoire', 'required'], 'options' => ['options'], 'defaut' => ['defaut', 'default'],
    'min' => ['min'], 'max' => ['max'], 'unique' => ['unique'], 'exemple' => ['exemple', 'placeholder'],
    'gauche' => ['gauche', 'left'], 'droite' => ['droite', 'right'], 'si' => ['si', 'if'],
];

/* ------------------------------------------------------------------ */
/* Configuration                                                      */
/* ------------------------------------------------------------------ */

function ins_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $defauts = [
            'langue'              => 'fr',
            'dossier_formulaires' => INS_PRIVE . '/formulaires',
            'dossier_donnees'     => INS_PRIVE . '/donnees',
            'separateur'          => ';',
            'fuseau'              => 'Europe/Paris',
            'liste_publique'      => true,
            'longueur_max'        => 2000,
            'admin_actif'         => true,
            'admin_gestion'       => true,
            'admin_mot_de_passe'  => '',
            'expediteur'          => '',
            'debug'               => false,
        ];
        $fichier = INS_PRIVE . '/config.php';
        $c = is_file($fichier) ? require $fichier : [];
        $cfg = array_merge($defauts, is_array($c) ? $c : []);
        @date_default_timezone_set((string)$cfg['fuseau']);
        ins_langue((string)$cfg['langue']);
    }
    return $cfg;
}

/** Clé secrète servant à signer les jetons ; créée automatiquement au premier usage. */
function ins_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $dir = ins_dossier_donnees();
    $f = $dir . '/.secret';
    if (is_file($f)) {
        $s = trim((string)file_get_contents($f));
        if (strlen($s) >= 32) {
            return $secret = $s;
        }
    }
    $s = bin2hex(random_bytes(32));
    if (@file_put_contents($f, $s, LOCK_EX) === false) {
        throw new RuntimeException(t('err_ecriture', ['fichier' => $f]));
    }
    return $secret = $s;
}

function ins_dossier_donnees(): string
{
    $dir = rtrim((string)ins_config()['dossier_donnees'], '/');
    ins_preparer_dossier($dir);
    return $dir;
}

function ins_dossier_formulaires(): string
{
    return rtrim((string)ins_config()['dossier_formulaires'], '/');
}

/** Crée le dossier si besoin et y dépose les protections pour le cas où il serait dans la partie publique. */
function ins_preparer_dossier(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(t('err_dossier', ['dossier' => $dir]));
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', ins_htaccess_interdit());
    }
    if (!is_file($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
}

function ins_htaccess_interdit(): string
{
    return "# Accès web interdit / no web access\n"
        . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n";
}

/** Le dossier est-il servi par le site web (sous DOCUMENT_ROOT) ? Renvoie son URL relative si oui. */
function ins_url_si_public(string $dir): ?string
{
    $racine = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $reel = realpath($dir);
    if (!$racine || !$reel) {
        return null;
    }
    $racine = rtrim($racine, '/');
    if (strpos($reel . '/', $racine . '/') !== 0) {
        return null;
    }
    return substr($reel, strlen($racine)) ?: '/';
}

/* ------------------------------------------------------------------ */
/* Langues                                                            */
/* ------------------------------------------------------------------ */

/** Langues disponibles = fichiers prive/lang/<code>.php */
function ins_langues(): array
{
    $res = [];
    foreach (glob(INS_PRIVE . '/lang/*.php') ?: [] as $f) {
        $code = basename($f, '.php');
        if (preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/i', $code)) {
            $res[] = $code;
        }
    }
    sort($res);
    return $res;
}

/** Langue courante ; la change si $code est donné et disponible. */
function ins_langue(?string $code = null): string
{
    static $courante = 'fr';
    if ($code !== null && $code !== '') {
        $code = strtolower(trim($code));
        if (is_file(INS_PRIVE . '/lang/' . $code . '.php') && preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $code)) {
            $courante = $code;
        }
    }
    return $courante;
}

function ins_textes(string $code): array
{
    static $cache = [];
    if (!isset($cache[$code])) {
        $f = INS_PRIVE . '/lang/' . $code . '.php';
        $t = is_file($f) ? require $f : [];
        $cache[$code] = is_array($t) ? $t : [];
    }
    return $cache[$code];
}

/** Traduction : t('cle', ['nom' => 'valeur']) remplace {nom} dans le texte. */
function t(string $cle, array $params = []): string
{
    $txt = ins_textes(ins_langue())[$cle] ?? ins_textes('fr')[$cle] ?? $cle;
    foreach ($params as $k => $v) {
        $txt = str_replace('{' . $k . '}', (string)$v, $txt);
    }
    return $txt;
}

function ins_date_lisible(int $ts): string
{
    return date(t('format_date_heure'), $ts);
}

/* ------------------------------------------------------------------ */
/* Utilitaires                                                        */
/* ------------------------------------------------------------------ */

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ins_oui($v): bool
{
    if (is_bool($v)) {
        return $v;
    }
    $v = mb_strtolower(trim((string)$v));
    return in_array($v, ['1', 'oui', 'o', 'yes', 'y', 'true', 'vrai', 'x', 'on'], true);
}

function ins_id_valide(string $id): bool
{
    return (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/i', $id);
}

function ins_minuscules(string $s): string
{
    return mb_strtolower(trim($s), 'UTF-8');
}

/** Empêche l'interprétation d'une valeur comme formule par Excel / LibreOffice / GridCraft. */
function ins_csv_sur(string $s): string
{
    return ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false) ? "'" . $s : $s;
}

/** Première clé présente parmi ses écritures possibles (français, anglais). */
function ins_cle(array $tab, array $noms, $defaut = null)
{
    foreach ($noms as $n) {
        if (array_key_exists($n, $tab)) {
            return $tab[$n];
        }
    }
    return $defaut;
}

/** Traduction d'une clé : « libelle_en », « label_en »… ; null si absente. */
function ins_cle_trad(array $tab, array $noms, string $langue): ?string
{
    foreach ($noms as $n) {
        $k = $n . '_' . $langue;
        if (array_key_exists($k, $tab) && !is_array($tab[$k])) {
            return trim((string)$tab[$k]);
        }
    }
    return null;
}

function ins_liste_options(string $v): array
{
    return array_values(array_filter(array_map('trim', explode('|', $v)), 'strlen'));
}

/* ------------------------------------------------------------------ */
/* Descripteurs de formulaires                                        */
/* ------------------------------------------------------------------ */

/**
 * Formulaires diffusés (affichés dans la liste publique).
 * @return array<string,string> identifiant => titre
 */
function ins_liste_formulaires(?string $langue = null): array
{
    $liste = [];
    foreach (glob(ins_dossier_formulaires() . '/*.ini') ?: [] as $f) {
        $id = basename($f, '.ini');
        if (!ins_id_valide($id)) {
            continue;
        }
        try {
            $form = ins_charger_formulaire($id);
            if ($form['diffusion']) {
                $liste[$id] = $langue !== null ? ins_localiser($form, $langue)['titre'] : $form['titre'];
            }
        } catch (Throwable $ex) {
            error_log('inscription : ' . $ex->getMessage());
        }
    }
    asort($liste, SORT_NATURAL | SORT_FLAG_CASE);
    return $liste;
}

/**
 * Lit et vérifie un descripteur. $chemin permet de vérifier un fichier
 * avant de l'installer (téléversement depuis admin.php).
 */
function ins_charger_formulaire(string $id, ?string $chemin = null): array
{
    if (!ins_id_valide($id)) {
        throw new InvalidArgumentException(t('err_id_formulaire', ['id' => $id]));
    }
    $chemin = $chemin ?? ins_dossier_formulaires() . '/' . $id . '.ini';
    $contenu = is_file($chemin) ? file_get_contents($chemin) : false;
    if ($contenu === false) {
        throw new RuntimeException(t('err_formulaire_absent', ['id' => $id]));
    }
    return ins_analyser_formulaire($id, $contenu);
}

/** Analyse et vérifie le texte d'un descripteur (sans fichier : sert aussi à l'éditeur). */
function ins_analyser_formulaire(string $id, string $contenu): array
{
    if (!ins_id_valide($id)) {
        throw new InvalidArgumentException(t('err_id_formulaire', ['id' => $id]));
    }
    $f = "$id.ini";
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? '';
    if (!mb_check_encoding($contenu, 'UTF-8')) {
        throw new RuntimeException(t('err_utf8', ['f' => $f]));
    }
    if (strpos($contenu, '${') !== false) {
        // ${...} ferait lire des variables d'environnement du serveur par parse_ini
        throw new RuntimeException(t('err_dollar', ['f' => $f]));
    }
    error_clear_last();
    $ini = @parse_ini_string($contenu, true, INI_SCANNER_NORMAL);
    if ($ini === false) {
        $err = error_get_last();
        throw new RuntimeException(t('err_syntaxe', ['f' => $f, 'detail' => $err['message'] ?? '?']));
    }

    $g = $ini['formulaire'] ?? ($ini['form'] ?? []);
    unset($ini['formulaire'], $ini['form']);
    $lit = function (string $cle, $defaut = '') use ($g) {
        $v = ins_cle(is_array($g) ? $g : [], INS_CLES_FORMULAIRE[$cle], $defaut);
        return is_array($v) ? $defaut : $v;
    };

    // Langue de base (celle des textes sans suffixe et des valeurs enregistrées) et langues proposées
    $base = strtolower(trim((string)$lit('langue')));
    $langues = array_map('strtolower', ins_liste_options((string)$lit('langues')));
    if ($base === '') {
        $base = $langues[0] ?? (string)ins_config()['langue'];
    }
    $langues = array_values(array_unique(array_merge([$base], $langues)));
    foreach ($langues as $lg) {
        if (!in_array($lg, ins_langues(), true)) {
            throw new RuntimeException(t('err_langue', ['f' => $f, 'langue' => $lg]));
        }
    }

    // Accès restreint : « personnel » (lien + mot de passe) ou « lien » (lien personnel seul)
    $acces = ins_minuscules((string)$lit('acces'));
    $modesAcces = ['' => '', 'libre' => '', 'open' => '', 'non' => '',
        'personnel' => 'personnel', 'personal' => 'personnel', 'mot_de_passe' => 'personnel', 'password' => 'personnel',
        'lien' => 'lien', 'link' => 'lien'];
    if (!array_key_exists($acces, $modesAcces)) {
        throw new RuntimeException(t('err_acces_mode', ['f' => $f, 'valeur' => $acces]));
    }
    $acces = $modesAcces[$acces];

    $diffusion = $lit('diffusion', null);
    $form = [
        'id'              => $id,
        'titre'           => trim((string)$lit('titre', $id)),
        'intro'           => trim((string)$lit('intro')),
        'merci'           => trim((string)$lit('merci')),   // vide = texte par défaut de la langue
        'bouton'          => trim((string)$lit('bouton')),  // idem
        'fichier'         => trim((string)$lit('fichier', $id)),
        'ouverture'       => ins_date_ini($lit('ouverture'), $f, 'ouverture'),
        'fermeture'       => ins_date_ini($lit('fermeture'), $f, 'fermeture'),
        'ouverture_texte' => trim((string)$lit('ouverture')),   // tel qu'écrit (pour l'éditeur)
        'fermeture_texte' => trim((string)$lit('fermeture')),
        'places'          => max(0, (int)$lit('places', 0)),
        'notifier'        => trim((string)$lit('notifier')),
        // diffusion = oui par défaut ; l'ancienne clé « cache = oui » vaut diffusion = non
        'diffusion'       => $acces === '' && ($diffusion !== null ? ins_oui($diffusion) : !ins_oui($lit('cache'))),
        'acces'           => $acces,
        'modifiable'      => $acces !== '' && ins_oui($lit('modifiable', 'oui')),
        'afficher_numero' => ins_oui($lit('afficher_numero', 'oui')),
        'langue'          => $base,
        'langues'         => $langues,
        'trad'            => [],
        'champs'          => [],
    ];
    foreach (array_slice($langues, 1) as $lg) {
        foreach (INS_TRAD_FORMULAIRE as $cle) {
            $v = ins_cle_trad(is_array($g) ? $g : [], INS_CLES_FORMULAIRE[$cle], $lg);
            if ($v !== null && $v !== '') {
                $form['trad'][$lg][$cle] = $v;
            }
        }
    }
    if (!ins_id_valide($form['fichier'])) {
        throw new RuntimeException(t('err_fichier', ['f' => $f]));
    }

    $vus = [];
    foreach ($ini as $nom => $p) {
        $nom = (string)$nom;
        if (!is_array($p)) {
            throw new RuntimeException(t('err_hors_section', ['f' => $f, 'nom' => $nom]));
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/i', $nom) || in_array(strtolower($nom), INS_NOMS_RESERVES, true)) {
            throw new RuntimeException(t('err_nom_champ', ['f' => $f, 'nom' => $nom]));
        }
        $lc = function (string $cle, $defaut = '') use ($p) {
            $v = ins_cle($p, INS_CLES_CHAMP[$cle], $defaut);
            return is_array($v) ? $defaut : $v;
        };
        $type = ins_minuscules((string)$lc('type', 'texte'));
        $type = INS_ALIAS_TYPES[$type] ?? $type;
        if (!in_array($type, INS_TYPES, true)) {
            throw new RuntimeException(t('err_type', ['f' => $f, 'nom' => $nom, 'type' => $type, 'types' => implode(', ', INS_TYPES)]));
        }
        $options = [];
        if (trim((string)$lc('options')) !== '') {
            $options = array_values(array_filter(array_map('trim', explode('|', (string)$lc('options'))), 'strlen'));
        }
        if ($type === 'echelle' && !$options) {
            $options = ['1', '2', '3', '4', '5'];
        }
        if (in_array($type, ['choix', 'liste', 'cases'], true) && count($options) < 1) {
            throw new RuntimeException(t('err_options', ['f' => $f, 'nom' => $nom, 'type' => $type]));
        }
        $min = (string)$lc('min');
        $max = (string)$lc('max');
        $champ = [
            'nom'         => $nom,
            'type'        => $type,
            'libelle'     => trim((string)$lc('libelle', $nom)),
            'aide'        => trim((string)$lc('aide')),
            'obligatoire' => ins_oui($lc('obligatoire')),
            'options'     => $options,
            'defaut'      => trim((string)$lc('defaut')),
            'min'         => $min !== '' ? $min : null,
            'max'         => $max !== '' ? $max : null,
            'unique'      => ins_oui($lc('unique')),
            'exemple'     => trim((string)$lc('exemple')),
            'gauche'      => trim((string)$lc('gauche')),
            'droite'      => trim((string)$lc('droite')),
            'si'          => [],
            'trad'        => [],
        ];
        foreach (array_slice($langues, 1) as $lg) {
            foreach (INS_TRAD_CHAMP as $cle) {
                $v = ins_cle_trad($p, INS_CLES_CHAMP[$cle], $lg);
                if ($v === null || $v === '') {
                    continue;
                }
                if ($cle === 'options') {
                    $v = ins_liste_options($v);
                    if (count($v) !== count($options)) {
                        throw new RuntimeException(t('err_options_trad', ['f' => $f, 'nom' => $nom, 'langue' => $lg, 'n' => count($options)]));
                    }
                }
                $champ['trad'][$lg][$cle] = $v;
            }
        }
        if (trim((string)$lc('si')) !== '') {
            $champ['si'] = ins_conditions((string)$lc('si'), $vus, $f, $nom);
        }
        $form['champs'][$nom] = $champ;
        if (!in_array($type, INS_TYPES_DECORATIFS, true)) {
            $vus[$nom] = $champ;
        }
    }
    if (!$vus) {
        throw new RuntimeException(t('err_aucun_champ', ['f' => $f]));
    }
    return $form;
}

function ins_date_ini($v, string $f, string $cle): ?int
{
    $v = trim((string)$v);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        $v .= $cle === 'fermeture' ? ' 23:59:59' : ' 00:00:00';
    }
    $t = strtotime($v);
    if ($t === false) {
        throw new RuntimeException(t('err_date', ['f' => $f, 'cle' => $cle]));
    }
    return $t;
}

/**
 * « si » : conditions d'affichage, séparées par « & » (toutes doivent être vraies).
 *   profil = Bénévole | Média    → l'une de ces réponses est choisie
 *   profil != Bénévole           → aucune de ces réponses n'est choisie
 *   accord = oui                 → pour un champ de type « case » (valeurs internes 1 / 0)
 */
function ins_conditions(string $expr, array $connus, string $f, string $nom): array
{
    $res = [];
    foreach (explode('&', $expr) as $morceau) {
        $morceau = trim($morceau);
        if ($morceau === '') {
            continue;
        }
        if (!preg_match('/^([a-z][a-z0-9_]*)\s*(!=|=)\s*(.+)$/is', $morceau, $m)) {
            throw new RuntimeException(t('err_condition', ['f' => $f, 'nom' => $nom, 'cond' => $morceau]));
        }
        $cible = $m[1];
        if (!isset($connus[$cible])) {
            throw new RuntimeException(t('err_condition_ordre', ['f' => $f, 'nom' => $nom, 'cible' => $cible]));
        }
        $valeurs = array_values(array_filter(array_map('trim', explode('|', $m[3])), 'strlen'));
        $c = $connus[$cible];
        if ($c['type'] === 'case') {
            $valeurs = array_map(function ($v) { return ins_oui($v) ? '1' : '0'; }, $valeurs);
        } elseif ($c['options']) {
            $permis = array_map('ins_minuscules', $c['options']);
            foreach ($valeurs as $v) {
                if (!in_array(ins_minuscules($v), $permis, true)) {
                    throw new RuntimeException(t('err_condition_option', ['f' => $f, 'nom' => $nom, 'valeur' => $v, 'cible' => $cible]));
                }
            }
        }
        $res[] = ['champ' => $cible, 'egal' => $m[2] === '=', 'valeurs' => $valeurs];
    }
    return $res;
}

/**
 * Version du formulaire dans la langue demandée (textes traduits quand ils existent,
 * sinon ceux de la langue de base). Chaque champ reçoit « options_aff » : les libellés
 * affichés des options, alors que « options » garde les valeurs enregistrées (langue de base).
 */
function ins_localiser(array $form, string $langue): array
{
    if (!in_array($langue, $form['langues'], true)) {
        $langue = $form['langue'];
    }
    foreach ($form['trad'][$langue] ?? [] as $cle => $v) {
        $form[$cle] = $v;
    }
    foreach ($form['champs'] as $nom => $c) {
        $c['options_aff'] = $c['options'];
        foreach ($c['trad'][$langue] ?? [] as $cle => $v) {
            $c[$cle === 'options' ? 'options_aff' : $cle] = $v;
        }
        $form['champs'][$nom] = $c;
    }
    $form['langue_affichee'] = $langue;
    return $form;
}

/**
 * Langue du visiteur parmi $possibles : lien « ?lang= » (mémorisé dans un cookie),
 * sinon cookie, sinon langue préférée du navigateur, sinon $defaut.
 */
function ins_langue_visiteur(array $possibles, string $defaut): string
{
    $demande = isset($_GET['lang']) && is_string($_GET['lang']) ? strtolower($_GET['lang']) : '';
    if ($demande !== '' && in_array($demande, ins_langues(), true)) {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('ins_lang', $demande, ['expires' => time() + 365 * 86400, 'path' => '/', 'httponly' => true, 'secure' => $https, 'samesite' => 'Lax']);
        $_COOKIE['ins_lang'] = $demande;
        if (in_array($demande, $possibles, true)) {
            return $demande;
        }
    }
    $cookie = isset($_COOKIE['ins_lang']) && is_string($_COOKIE['ins_lang']) ? $_COOKIE['ins_lang'] : '';
    if (in_array($cookie, $possibles, true)) {
        return $cookie;
    }
    // Accept-Language : « fr-FR,fr;q=0.9,en;q=0.8 »
    $prefs = [];
    foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $i => $morceau) {
        if (preg_match('/^\s*([a-z]{2,3})(?:-[a-z0-9]+)?\s*(?:;\s*q=([0-9.]+))?/i', $morceau, $m)) {
            $q = isset($m[2]) && $m[2] !== '' ? (float)$m[2] : 1.0;
            $lg = strtolower($m[1]);
            if (!isset($prefs[$lg]) || $prefs[$lg] < $q - $i / 1000) {
                $prefs[$lg] = $q - $i / 1000; // à qualité égale, l'ordre du navigateur départage
            }
        }
    }
    arsort($prefs);
    foreach ($prefs as $lg => $q) {
        if ($q > 0 && in_array($lg, $possibles, true)) {
            return $lg;
        }
    }
    return in_array($defaut, $possibles, true) ? $defaut : ($possibles[0] ?? $defaut);
}

/** Liens de changement de langue : « English · Français » (langue courante en gras). */
function ins_liens_langues(array $langues, string $courante, array $params = []): string
{
    if (count($langues) < 2) {
        return '';
    }
    $liens = [];
    foreach ($langues as $lg) {
        $nom = ins_textes($lg)['nom_langue'] ?? $lg;
        $liens[] = $lg === $courante
            ? '<b>' . e($nom) . '</b>'
            : '<a href="?' . e(http_build_query($params + ['lang' => $lg])) . '" hreflang="' . e($lg) . '" lang="' . e($lg) . '">' . e($nom) . '</a>';
    }
    return '<p class="langues">' . implode(' · ', $liens) . '</p>';
}

/** Le formulaire est-il proposé en plusieurs langues ? (ajoute la colonne « langue » au CSV) */
function ins_multilingue(array $form): bool
{
    return count($form['langues']) > 1;
}

/** Colonnes du fichier de données, dans l'ordre. */
function ins_colonnes(array $form): array
{
    $cols = INS_COLONNES_FIXES;
    if (ins_multilingue($form)) {
        $cols[] = 'langue';
    }
    if ($form['acces'] !== '') {
        $cols[] = 'acces';
        $cols[] = 'acces_nom';
    }
    foreach ($form['champs'] as $c) {
        if (!in_array($c['type'], INS_TYPES_DECORATIFS, true)) {
            $cols[] = $c['nom'];
        }
    }
    return $cols;
}

function ins_fichier_donnees(array $form): string
{
    return ins_dossier_donnees() . '/' . $form['fichier'] . '.csv';
}

/* ------------------------------------------------------------------ */
/* Diffusion et lien de partage                                       */
/* ------------------------------------------------------------------ */

/** Clé du lien de partage d'un formulaire (16 caractères, dérivée de la clé secrète). */
function ins_cle_partage(string $id): string
{
    return substr(ins_signer('partage|' . $id), 0, 16);
}

/** Le visiteur a-t-il le droit d'ouvrir ce formulaire ? (diffusé, ou lien de partage valide) */
function ins_acces_autorise(array $form, $cle): bool
{
    if ($form['diffusion']) {
        return true;
    }
    if ($form['acces'] !== '') {
        return false; // accès restreint : uniquement par les liens personnels
    }
    return is_string($cle) && $cle !== '' && hash_equals(ins_cle_partage($form['id']), $cle);
}

/** Adresse relative du formulaire (avec la clé de partage s'il n'est pas diffusé). */
function ins_url_formulaire(array $form, ?string $langue = null): string
{
    return '?' . http_build_query(ins_params_formulaire($form, $langue));
}

function ins_params_formulaire(array $form, ?string $langue = null): array
{
    $q = ['f' => $form['id']];
    if (!$form['diffusion'] && $form['acces'] === '') {
        $q['k'] = ins_cle_partage($form['id']);
    }
    if ($langue !== null && ins_multilingue($form)) {
        $q['lang'] = $langue;
    }
    return $q;
}

/* ------------------------------------------------------------------ */
/* État du formulaire                                                 */
/* ------------------------------------------------------------------ */

/** @return string '' si ouvert, sinon le message à afficher */
function ins_etat(array $form, bool $modification = false): string
{
    $t = time();
    if ($form['ouverture'] !== null && $t < $form['ouverture']) {
        return t('etat_pas_ouvert', ['date' => ins_date_lisible($form['ouverture'])]);
    }
    if ($form['fermeture'] !== null && $t > $form['fermeture']) {
        return t('etat_clos');
    }
    if (!$modification && $form['places'] > 0 && ins_nombre_inscrits($form) >= $form['places']) {
        return t('etat_complet');
    }
    return '';
}

function ins_nombre_inscrits(array $form): int
{
    $f = ins_fichier_donnees($form);
    return is_file($f) ? max(0, count(ins_lire_csv($f)) - 1) : 0;
}

/* ------------------------------------------------------------------ */
/* Validation                                                         */
/* ------------------------------------------------------------------ */

/** Valeurs choisies d'un champ à choix (tableau, en casse du descripteur). */
function ins_choisis(array $c, $brut): array
{
    $brut = is_array($brut) ? $brut : ($brut === null || $brut === '' ? [] : [$brut]);
    $res = [];
    foreach ($c['options'] as $opt) {
        foreach ($brut as $b) {
            if (is_string($b) && $b === $opt) {
                $res[] = $opt;
                break;
            }
        }
    }
    return $res;
}

/** Valeur enregistrée pour une case cochée / non cochée (dans la langue du formulaire). */
function ins_valeur_case(bool $cochee, string $langue): string
{
    $txt = ins_textes($langue) + ins_textes('fr');
    return $cochee ? $txt['val_oui'] : $txt['val_non'];
}

function ins_visible(array $c, array $valeurs, array $visibles, array $champs, string $langue): bool
{
    foreach ($c['si'] as $cond) {
        $cible = $cond['champ'];
        if (empty($visibles[$cible])) {
            return false; // dépend d'un champ lui-même masqué
        }
        $v = $valeurs[$cible] ?? '';
        if ($champs[$cible]['type'] === 'case') {
            $choisis = [$v === ins_valeur_case(true, $langue) ? '1' : '0'];
        } else {
            $choisis = $v === '' ? [] : array_map('ins_minuscules', array_map('trim', explode('|', $v)));
        }
        $trouve = false;
        foreach ($cond['valeurs'] as $attendu) {
            if (in_array(ins_minuscules($attendu), $choisis, true)) {
                $trouve = true;
                break;
            }
        }
        if ($trouve !== $cond['egal']) {
            return false;
        }
    }
    return true;
}

/**
 * @return array{0: array<string,string>, 1: array<string,string>, 2: array<string,mixed>}
 *         valeurs à enregistrer, erreurs par champ, saisie brute (pour réafficher)
 */
function ins_valider(array $form, array $post): array
{
    $max = (int)ins_config()['longueur_max'];
    $valeurs = [];
    $erreurs = [];
    $brut = [];
    $visibles = [];

    foreach ($form['champs'] as $nom => $c) {
        if (in_array($c['type'], INS_TYPES_DECORATIFS, true)) {
            continue;
        }
        $b = $post[$nom] ?? null;
        $brut[$nom] = $b;
        $visible = ins_visible($c, $valeurs, $visibles, $form['champs'], $form['langue']);
        $visibles[$nom] = $visible;
        if (!$visible) {
            $valeurs[$nom] = '';
            continue;
        }

        $err = '';
        switch ($c['type']) {
            case 'choix':
            case 'echelle':
            case 'liste':
                $sel = ins_choisis($c, is_array($b) ? null : $b);
                $v = $sel[0] ?? '';
                if ($v === '' && is_string($b) && $b !== '') {
                    $err = t('v_choix_invalide');
                }
                break;
            case 'cases':
                $v = implode(' | ', ins_choisis($c, $b));
                break;
            case 'case':
                $cochee = $b !== null && $b !== '' && !is_array($b);
                $v = ins_valeur_case($cochee, $form['langue']); // toujours dans la langue de base
                if ($c['obligatoire'] && !$cochee) {
                    $err = t('v_case_obligatoire');
                }
                break;
            default:
                $v = is_string($b) ? trim(str_replace("\r\n", "\n", $b)) : '';
                if ($c['type'] !== 'texte_long') {
                    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
                }
                if (!mb_check_encoding($v, 'UTF-8')) {
                    $v = '';
                    $err = t('v_texte_invalide');
                } elseif (mb_strlen($v) > $max) {
                    $err = t('v_trop_long', ['n' => $max]);
                } elseif ($v !== '') {
                    $err = ins_verifier_format($c, $v);
                }
        }
        if ($err === '' && $c['obligatoire'] && $c['type'] !== 'case' && $v === '') {
            $err = t('v_obligatoire');
        }
        if ($err !== '') {
            $erreurs[$nom] = $err;
        }
        $valeurs[$nom] = $v;
    }
    return [$valeurs, $erreurs, $brut];
}

function ins_verifier_format(array $c, string $v): string
{
    switch ($c['type']) {
        case 'email':
            return filter_var($v, FILTER_VALIDATE_EMAIL) ? '' : t('v_email');
        case 'telephone':
            return preg_match('/^\+?[0-9 .\-()]{6,25}$/', $v) ? '' : t('v_telephone');
        case 'nombre':
            if (!preg_match('/^-?\d+([.,]\d+)?$/', $v)) {
                return t('v_nombre');
            }
            $n = (float)str_replace(',', '.', $v);
            if ($c['min'] !== null && $n < (float)$c['min']) {
                return t('v_au_moins', ['n' => $c['min']]);
            }
            if ($c['max'] !== null && $n > (float)$c['max']) {
                return t('v_au_plus', ['n' => $c['max']]);
            }
            return '';
        case 'date':
            $d = DateTime::createFromFormat('!Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) {
                return t('v_date');
            }
            if ($c['min'] !== null && $v < $c['min']) {
                return t('v_date_tot');
            }
            if ($c['max'] !== null && $v > $c['max']) {
                return t('v_date_tard');
            }
            return '';
        case 'texte':
        case 'texte_long':
            if ($c['min'] !== null && mb_strlen($v) < (int)$c['min']) {
                return t('v_car_min', ['n' => (int)$c['min']]);
            }
            if ($c['max'] !== null && mb_strlen($v) > (int)$c['max']) {
                return t('v_car_max', ['n' => (int)$c['max']]);
            }
    }
    return '';
}

/* ------------------------------------------------------------------ */
/* Enregistrement                                                     */
/* ------------------------------------------------------------------ */

function ins_lire_csv(string $fichier): array
{
    $h = @fopen($fichier, 'rb');
    if (!$h) {
        return [];
    }
    $sep = (string)ins_config()['separateur'];
    $lignes = [];
    while (($r = fgetcsv($h, 0, $sep, '"', '')) !== false) {
        if ($r === [null]) {
            continue;
        }
        $lignes[] = $r;
    }
    fclose($h);
    if ($lignes) {
        $lignes[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$lignes[0][0]);
    }
    return $lignes;
}

function ins_nouveau_numero(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        if ($i === 3) {
            $s .= '-';
        }
    }
    return $s;
}

/** Verrou commun à toutes les écritures (réponses et accès). */
function ins_verrouiller()
{
    $dir = ins_dossier_donnees();
    $verrou = @fopen($dir . '/.verrou', 'c');
    if (!$verrou || !flock($verrou, LOCK_EX)) {
        throw new RuntimeException(t('err_verrou', ['dossier' => $dir]));
    }
    return $verrou;
}

function ins_deverrouiller($verrou): void
{
    flock($verrou, LOCK_UN);
    fclose($verrou);
}

/** Réécrit entièrement un fichier CSV (fichier temporaire puis renommage : jamais de fichier à moitié écrit). */
function ins_ecrire_csv(string $fichier, array $lignes): void
{
    $sep = (string)ins_config()['separateur'];
    $tmp = $fichier . '.tmp-' . bin2hex(random_bytes(4));
    $h = @fopen($tmp, 'wb');
    if (!$h) {
        throw new RuntimeException(t('err_ecriture', ['fichier' => $fichier]));
    }
    fwrite($h, "\xEF\xBB\xBF");
    foreach ($lignes as $l) {
        fputcsv($h, $l, $sep, '"', '');
    }
    fclose($h);
    if (!@rename($tmp, $fichier)) {
        @unlink($tmp);
        throw new RuntimeException(t('err_ecriture', ['fichier' => $fichier]));
    }
}

/** Retire l'apostrophe ajoutée par ins_csv_sur() (pour réafficher une réponse enregistrée). */
function ins_csv_brut(string $s): string
{
    return (strlen($s) > 1 && $s[0] === "'" && strpos("=+-@\t\r", $s[1]) !== false) ? substr($s, 1) : $s;
}

/**
 * Ajoute une réponse au fichier CSV (sous verrou). Pour un formulaire à accès restreint, $acces
 * (ligne du fichier des accès) identifie le répondant : sa réponse précédente est remplacée.
 * @return array{0: ?string, 1: array<string,string>, 2: string} numéro, erreurs, message global
 */
function ins_enregistrer(array $form, array $valeurs, string $langue = '', ?array $acces = null): array
{
    $dir = ins_dossier_donnees();
    $fichier = ins_fichier_donnees($form);
    $cols = ins_colonnes($form);
    $sep = (string)ins_config()['separateur'];

    $verrou = ins_verrouiller();
    try {
        // Accès restreint : relire l'accès sous verrou (révoqué entre-temps ? déjà répondu ?)
        $tousAcces = [];
        if ($form['acces'] !== '') {
            $tousAcces = ins_lire_acces($form);
            $acces = $acces !== null ? ($tousAcces[$acces['identifiant']] ?? null) : null;
            if ($acces === null || $acces['etat'] !== 'actif') {
                return [null, [], t('acces_revoque')];
            }
            if ($acces['repondu'] !== '' && !$form['modifiable']) {
                return [null, [], t('acces_deja_repondu')];
            }
        }

        $lignes = is_file($fichier) ? ins_lire_csv($fichier) : [];

        // Si les champs du formulaire ont changé, l'ancien fichier est mis de côté.
        if ($lignes && $lignes[0] !== $cols) {
            rename($fichier, $dir . '/' . $form['fichier'] . '-ancien-' . date('Ymd-His') . '.csv');
            $lignes = [];
        }
        $existantes = array_slice($lignes, 1);

        // Réponse précédente de cet accès (modification)
        $index = null;
        if ($acces !== null) {
            $ia = array_search('acces', $cols, true);
            foreach ($existantes as $k => $l) {
                if (($l[$ia] ?? '') === $acces['identifiant']) {
                    $index = $k;
                    break;
                }
            }
        }

        if ($index === null && $form['places'] > 0 && count($existantes) >= $form['places']) {
            return [null, [], t('etat_complet')];
        }

        // Champs « unique = oui » : refus des doublons (casse ignorée), hors réponse modifiée
        $erreurs = [];
        foreach ($form['champs'] as $nom => $c) {
            if (!$c['unique'] || ($valeurs[$nom] ?? '') === '') {
                continue;
            }
            $i = array_search($nom, $cols, true);
            $cherche = ins_minuscules(ins_csv_sur($valeurs[$nom]));
            foreach ($existantes as $k => $l) {
                if ($k !== $index && ins_minuscules((string)($l[$i] ?? '')) === $cherche) {
                    $erreurs[$nom] = t('v_deja_utilise');
                    break;
                }
            }
        }
        if ($erreurs) {
            return [null, $erreurs, ''];
        }

        if ($index !== null) {
            $numero = (string)$existantes[$index][0];
        } else {
            $pris = [];
            foreach ($existantes as $l) {
                $pris[$l[0] ?? ''] = true;
            }
            do {
                $numero = ins_nouveau_numero();
            } while (isset($pris[$numero]));
        }

        $ligne = [];
        foreach ($cols as $nom) {
            if ($nom === 'numero') {
                $ligne[] = $numero;
            } elseif ($nom === 'date') {
                $ligne[] = date('Y-m-d H:i:s');
            } elseif ($nom === 'langue') {
                $ligne[] = $langue !== '' ? $langue : $form['langue'];
            } elseif ($nom === 'acces') {
                $ligne[] = $acces['identifiant'] ?? '';
            } elseif ($nom === 'acces_nom') {
                $ligne[] = ins_csv_sur($acces['nom'] ?? '');
            } else {
                $ligne[] = ins_csv_sur((string)($valeurs[$nom] ?? ''));
            }
        }

        if ($index !== null) {
            $existantes[$index] = $ligne;
            ins_ecrire_csv($fichier, array_merge([$cols], $existantes));
        } else {
            $nouveau = !is_file($fichier) || filesize($fichier) === 0;
            $h = @fopen($fichier, 'ab');
            if (!$h) {
                throw new RuntimeException(t('err_ecriture', ['fichier' => $fichier]));
            }
            if ($nouveau) {
                fwrite($h, "\xEF\xBB\xBF"); // BOM : les tableurs reconnaissent l'UTF-8
                fputcsv($h, $cols, $sep, '"', '');
            }
            fputcsv($h, $ligne, $sep, '"', '');
            fflush($h);
            fclose($h);
        }

        if ($acces !== null) {
            $maintenant = date('Y-m-d H:i:s');
            if ($acces['repondu'] === '') {
                $tousAcces[$acces['identifiant']]['repondu'] = $maintenant;
            } else {
                $tousAcces[$acces['identifiant']]['modifie'] = $maintenant;
            }
            ins_ecrire_acces($form, $tousAcces);
        }
        return [$numero, [], ''];
    } finally {
        ins_deverrouiller($verrou);
    }
}

/** Réponse enregistrée pour un accès (valeurs par nom de champ), ou null. */
function ins_reponse_acces(array $form, string $identifiant): ?array
{
    $fichier = ins_fichier_donnees($form);
    if (!is_file($fichier)) {
        return null;
    }
    $lignes = ins_lire_csv($fichier);
    $cols = $lignes[0] ?? [];
    $ia = array_search('acces', $cols, true);
    if ($ia === false) {
        return null;
    }
    foreach (array_slice($lignes, 1) as $l) {
        if (($l[$ia] ?? '') === $identifiant) {
            $res = [];
            foreach ($cols as $k => $nom) {
                $res[$nom] = ins_csv_brut((string)($l[$k] ?? ''));
            }
            return $res;
        }
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* Accès personnels (formulaires à diffusion restreinte)              */
/* ------------------------------------------------------------------ */

function ins_fichier_acces(array $form): string
{
    $dir = ins_dossier_donnees() . '/acces';
    ins_preparer_dossier($dir);
    return $dir . '/' . $form['fichier'] . '.csv';
}

/** @return array<string,array> accès indexés par identifiant */
function ins_lire_acces(array $form): array
{
    $f = ins_fichier_acces($form);
    $res = [];
    if (!is_file($f)) {
        return $res;
    }
    foreach (array_slice(ins_lire_csv($f), 1) as $l) {
        $a = [];
        foreach (INS_COLONNES_ACCES as $k => $nom) {
            $a[$nom] = (string)($l[$k] ?? '');
        }
        if ($a['identifiant'] !== '') {
            $a['nom'] = ins_csv_brut($a['nom']);
            $res[$a['identifiant']] = $a;
        }
    }
    return $res;
}

/** À appeler sous verrou. */
function ins_ecrire_acces(array $form, array $acces): void
{
    $lignes = [INS_COLONNES_ACCES];
    foreach ($acces as $a) {
        $l = [];
        foreach (INS_COLONNES_ACCES as $nom) {
            $l[] = $nom === 'nom' ? ins_csv_sur((string)$a[$nom]) : (string)$a[$nom];
        }
        $lignes[] = $l;
    }
    ins_ecrire_csv(ins_fichier_acces($form), $lignes);
}

function ins_aleatoire(int $n): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans 0/O, 1/l/I
    $s = '';
    for ($i = 0; $i < $n; $i++) {
        $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $s;
}

/** Nouveau secret d'un accès : [secret en clair, empreinte à stocker]. */
function ins_nouveau_secret(string $type): array
{
    if ($type === 'lien') {
        $clair = ins_aleatoire(24);
        return [$clair, 'sha256:' . hash('sha256', $clair)];
    }
    $clair = ins_aleatoire(10);
    return [$clair, password_hash($clair, PASSWORD_DEFAULT)];
}

/**
 * Crée des accès pour une liste de noms (un accès par nom ; noms vides = accès anonymes).
 * @return array<int,array{identifiant:string,nom:string,secret:string}> secrets EN CLAIR, à communiquer une seule fois
 */
function ins_creer_acces(array $form, array $noms): array
{
    $type = $form['acces'] === 'lien' ? 'lien' : 'mdp';
    $verrou = ins_verrouiller();
    try {
        $tous = ins_lire_acces($form);
        $crees = [];
        foreach ($noms as $nom) {
            do {
                $id = ins_nouveau_numero();
            } while (isset($tous[$id]));
            [$clair, $empreinte] = ins_nouveau_secret($type);
            $tous[$id] = ['identifiant' => $id, 'nom' => trim((string)$nom), 'type' => $type, 'secret' => $empreinte,
                'etat' => 'actif', 'cree' => date('Y-m-d H:i:s'), 'repondu' => '', 'modifie' => '', 'echecs' => '0', 'bloque' => ''];
            $crees[] = ['identifiant' => $id, 'nom' => trim((string)$nom), 'secret' => $clair];
        }
        ins_ecrire_acces($form, $tous);
        return $crees;
    } finally {
        ins_deverrouiller($verrou);
    }
}

/**
 * Modifie un accès : « revoquer », « reactiver » ou « renouveler » (nouveau secret, renvoyé en clair).
 * @return string|null nouveau secret en clair pour « renouveler »
 */
function ins_modifier_acces(array $form, string $id, string $action): ?string
{
    $verrou = ins_verrouiller();
    try {
        $tous = ins_lire_acces($form);
        if (!isset($tous[$id])) {
            throw new RuntimeException(t('introuvable'));
        }
        $clair = null;
        if ($action === 'revoquer') {
            $tous[$id]['etat'] = 'revoque';
        } elseif ($action === 'reactiver') {
            $tous[$id]['etat'] = 'actif';
            $tous[$id]['echecs'] = '0';
            $tous[$id]['bloque'] = '';
        } elseif ($action === 'renouveler') {
            $type = $form['acces'] === 'lien' ? 'lien' : 'mdp';
            [$clair, $empreinte] = ins_nouveau_secret($type);
            $tous[$id]['type'] = $type;
            $tous[$id]['secret'] = $empreinte;
            $tous[$id]['echecs'] = '0';
            $tous[$id]['bloque'] = '';
        } else {
            throw new RuntimeException(t('introuvable'));
        }
        ins_ecrire_acces($form, $tous);
        return $clair;
    } finally {
        ins_deverrouiller($verrou);
    }
}

/** Accès en mode « lien seul » : identifiant + jeton du lien. */
function ins_verifier_lien(array $form, string $id, $jeton): ?array
{
    $a = ins_lire_acces($form)[$id] ?? null;
    if ($a === null || $a['type'] !== 'lien' || !is_string($jeton) || $jeton === '') {
        return null;
    }
    return hash_equals($a['secret'], 'sha256:' . hash('sha256', $jeton)) ? $a : null;
}

/**
 * Accès en mode « lien + mot de passe » : vérifie le mot de passe, compte les échecs, bloque après trop d'essais.
 * @return array{0: ?array, 1: string} accès, code d'erreur ('' si succès)
 */
function ins_verifier_mot_de_passe(array $form, string $id, string $mdp): array
{
    $verrou = ins_verrouiller();
    try {
        $tous = ins_lire_acces($form);
        $a = $tous[$id] ?? null;
        if ($a === null || $a['type'] !== 'mdp') {
            password_verify($mdp, password_hash('-', PASSWORD_DEFAULT)); // même durée qu'un vrai essai
            return [null, 'acces_mdp_incorrect'];
        }
        if ($a['bloque'] !== '' && (int)$a['bloque'] > time()) {
            return [null, 'acces_bloque'];
        }
        if (!password_verify($mdp, $a['secret'])) {
            $echecs = (int)$a['echecs'] + 1;
            $tous[$id]['echecs'] = (string)$echecs;
            if ($echecs >= INS_ACCES_ECHECS_MAX) {
                $tous[$id]['echecs'] = '0';
                $tous[$id]['bloque'] = (string)(time() + INS_ACCES_BLOCAGE);
            }
            ins_ecrire_acces($form, $tous);
            return [null, $echecs >= INS_ACCES_ECHECS_MAX ? 'acces_bloque' : 'acces_mdp_incorrect'];
        }
        if ($a['echecs'] !== '0' || $a['bloque'] !== '') {
            $tous[$id]['echecs'] = '0';
            $tous[$id]['bloque'] = '';
            ins_ecrire_acces($form, $tous);
        }
        return [$tous[$id], ''];
    } finally {
        ins_deverrouiller($verrou);
    }
}

/** Nom du cookie de connexion d'un formulaire à mot de passe. */
function ins_cookie_acces(string $fid): string
{
    return 'ins_acces_' . substr(hash('sha256', $fid), 0, 12);
}

/** Valeur du cookie : identifiant.expiration.signature (la signature dépend du mot de passe actuel). */
function ins_jeton_acces(string $fid, array $a): string
{
    $exp = time() + INS_ACCES_DUREE;
    return $a['identifiant'] . '.' . $exp . '.' . ins_signer("acces|$fid|{$a['identifiant']}|$exp|" . substr($a['secret'], -16));
}

/** Accès connecté par cookie (mode « lien + mot de passe »), ou null. */
function ins_acces_par_cookie(array $form, string $id): ?array
{
    $v = $_COOKIE[ins_cookie_acces($form['id'])] ?? '';
    if (!is_string($v) || !preg_match('/^([A-Z0-9]{4}-[A-Z0-9]{4})\.(\d+)\.([0-9a-f]{32})$/', $v, $m) || $m[1] !== $id || (int)$m[2] < time()) {
        return null;
    }
    $a = ins_lire_acces($form)[$id] ?? null;
    if ($a === null || $a['type'] !== 'mdp') {
        return null;
    }
    return hash_equals(ins_signer("acces|{$form['id']}|$id|{$m[2]}|" . substr($a['secret'], -16)), $m[3]) ? $a : null;
}

/** Adresse de base du formulaire public (https://site/dossier/index.php). */
function ins_url_base(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $hote = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $chemin = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . $hote . $chemin . '/index.php';
}

/** Lien personnel d'un accès (avec le jeton pour le mode « lien seul »). */
function ins_lien_acces(array $form, string $id, ?string $jeton = null): string
{
    $q = ['f' => $form['id'], 'i' => $id];
    if ($jeton !== null && $form['acces'] === 'lien') {
        $q['t'] = $jeton;
    }
    return ins_url_base() . '?' . http_build_query($q);
}

/** Envoi facultatif d'un courriel au gestionnaire (« notifier = » dans le descripteur). */
function ins_notifier(array $form, string $numero, array $valeurs, ?array $acces = null): void
{
    if ($form['notifier'] === '' || !function_exists('mail')) {
        return;
    }
    $dest = array_filter(array_map('trim', explode(',', $form['notifier'])), function ($a) {
        return (bool)filter_var($a, FILTER_VALIDATE_EMAIL);
    });
    if (!$dest) {
        return;
    }
    $corps = t('mail_corps', ['numero' => $numero, 'titre' => $form['titre']]) . "\n";
    if ($acces !== null) {
        $corps .= t('mail_acces', ['id' => $acces['identifiant'], 'nom' => $acces['nom']]) . "\n";
    }
    $corps .= "\n";
    foreach ($form['champs'] as $nom => $c) {
        if (!in_array($c['type'], INS_TYPES_DECORATIFS, true)) {
            $corps .= $c['libelle'] . ' : ' . ($valeurs[$nom] ?? '') . "\n";
        }
    }
    $entetes = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    $exp = (string)ins_config()['expediteur'];
    if ($exp !== '' && filter_var($exp, FILTER_VALIDATE_EMAIL)) {
        $entetes .= "From: $exp\r\n";
    }
    $sujet = '=?UTF-8?B?' . base64_encode(t('mail_sujet', ['titre' => $form['titre']])) . '?=';
    @mail(implode(',', $dest), $sujet, $corps, $entetes);
}

/* ------------------------------------------------------------------ */
/* Jetons signés (remplacent les sessions PHP)                        */
/* ------------------------------------------------------------------ */

function ins_signer(string $donnee): string
{
    return substr(hash_hmac('sha256', $donnee, ins_secret()), 0, 32);
}

function ins_jeton(string $fid): string
{
    $t = (string)time();
    return $t . '.' . ins_signer("formulaire|$fid|$t");
}

/** @return string '' si valide, sinon le motif du refus */
function ins_verifier_jeton(string $fid, $jeton): string
{
    if (!is_string($jeton) || !preg_match('/^(\d+)\.([0-9a-f]{32})$/', $jeton, $m)) {
        return 'invalide';
    }
    if (!hash_equals(ins_signer("formulaire|$fid|{$m[1]}"), $m[2])) {
        return 'invalide';
    }
    $age = time() - (int)$m[1];
    if ($age < 3) {
        return 'trop_rapide'; // un humain met plus de 3 secondes à remplir un formulaire
    }
    if ($age > 86400) {
        return 'expire';
    }
    return '';
}

/* ------------------------------------------------------------------ */
/* Affichage d'un formulaire (page publique et aperçu de l'éditeur)   */
/* ------------------------------------------------------------------ */

/**
 * HTML des champs d'un formulaire (déjà localisé par ins_localiser).
 * $premier : premier affichage (valeurs par défaut, ou réponse précédente $reponseExistante) ;
 * sinon $brut et $erreurs viennent de l'envoi refusé.
 */
function ins_rendu_champs(array $form, array $brut, array $erreurs, bool $premier, ?array $reponseExistante = null): string
{
    $h = '';
    foreach ($form['champs'] as $nom => $c) {
        $si = $c['si'] ? ' data-si="' . e(json_encode($c['si'], JSON_UNESCAPED_UNICODE)) . '"' : '';

        if ($c['type'] === 'titre') {
            $h .= '<h2 data-bloc="' . e($nom) . '"' . $si . '>' . e($c['libelle']) . '</h2>';
            continue;
        }
        if ($c['type'] === 'info') {
            $h .= '<div class="info" data-bloc="' . e($nom) . '"' . $si . '>' . nl2br(e($c['libelle'])) . '</div>';
            continue;
        }

        if ($premier && $reponseExistante !== null) {
            $b = $reponseExistante[$nom] ?? null; // modification : réponse précédente
        } else {
            $b = $premier ? ($c['defaut'] !== '' ? $c['defaut'] : null) : ($brut[$nom] ?? null);
        }
        $err = $erreurs[$nom] ?? '';
        $idh = 'c_' . $nom;
        $req = $c['obligatoire'] ? ' <span class="req" title="' . e(t('obligatoire')) . '">*</span>' : '';
        $aria = $err !== '' ? ' aria-invalid="true" aria-describedby="' . $idh . '_e"' : '';
        $groupe = in_array($c['type'], ['choix', 'cases', 'echelle'], true);

        $h .= '<div class="champ' . ($err !== '' ? ' erreur' : '') . '" data-nom="' . e($nom) . '" data-type="' . e($c['type']) . '"' . $si . '>';
        if ($c['type'] !== 'case') {
            $h .= $groupe
                ? '<fieldset style="border:0;margin:0;padding:0"><legend class="lib">' . e($c['libelle']) . $req . '</legend>'
                : '<label class="lib" for="' . $idh . '">' . e($c['libelle']) . $req . '</label>';
        }
        if ($c['aide'] !== '') {
            $h .= '<div class="aide">' . nl2br(e($c['aide'])) . '</div>';
        }

        $ph = $c['exemple'] !== '' ? ' placeholder="' . e($c['exemple']) . '"' : '';
        $val = is_string($b) ? $b : '';
        switch ($c['type']) {
            case 'texte_long':
                $h .= '<textarea id="' . $idh . '" name="' . e($nom) . '"' . $ph . $aria . '>' . e($val) . '</textarea>';
                break;
            case 'choix':
                foreach ($c['options'] as $i => $opt) {
                    $ch = $val === $opt ? ' checked' : '';
                    $h .= '<label class="opt"><input type="radio" name="' . e($nom) . '" value="' . e($opt) . '"' . $ch . '> <span>' . e($c['options_aff'][$i]) . '</span></label>';
                }
                break;
            case 'echelle':
                $h .= '<div class="echelle"><div class="notes">';
                foreach ($c['options'] as $i => $opt) {
                    $ch = $val === $opt ? ' checked' : '';
                    $h .= '<label class="opt"><input type="radio" name="' . e($nom) . '" value="' . e($opt) . '"' . $ch . '><span>' . e($c['options_aff'][$i]) . '</span></label>';
                }
                $h .= '</div>';
                if ($c['gauche'] !== '' || $c['droite'] !== '') {
                    $h .= '<div class="bouts"><span>' . e($c['gauche']) . '</span><span>' . e($c['droite']) . '</span></div>';
                }
                $h .= '</div>';
                break;
            case 'liste':
                $h .= '<select id="' . $idh . '" name="' . e($nom) . '"' . $aria . '><option value="">' . e(t('choisir')) . '</option>';
                foreach ($c['options'] as $i => $opt) {
                    $h .= '<option value="' . e($opt) . '"' . ($val === $opt ? ' selected' : '') . '>' . e($c['options_aff'][$i]) . '</option>';
                }
                $h .= '</select>';
                break;
            case 'cases':
                $sel = is_array($b) ? $b : (is_string($b) && $b !== '' ? array_map('trim', explode('|', $b)) : []);
                foreach ($c['options'] as $i => $opt) {
                    $ch = in_array($opt, $sel, true) ? ' checked' : '';
                    $h .= '<label class="opt"><input type="checkbox" name="' . e($nom) . '[]" value="' . e($opt) . '"' . $ch . '> <span>' . e($c['options_aff'][$i]) . '</span></label>';
                }
                break;
            case 'case':
                $ch = ($premier ? ins_oui($val) : ($b !== null && $b !== '')) ? ' checked' : '';
                $h .= '<label class="opt lib"><input type="checkbox" id="' . $idh . '" name="' . e($nom) . '" value="1"' . $ch . $aria . '> <span>' . e($c['libelle']) . $req . '</span></label>';
                break;
            default:
                $types = ['email' => 'email', 'telephone' => 'tel', 'nombre' => 'text', 'date' => 'date'];
                $tp = $types[$c['type']] ?? 'text';
                $extra = $c['type'] === 'nombre' ? ' inputmode="decimal"' : '';
                if ($c['type'] === 'date') {
                    $extra .= ($c['min'] !== null ? ' min="' . e($c['min']) . '"' : '') . ($c['max'] !== null ? ' max="' . e($c['max']) . '"' : '');
                }
                $auto = ['email' => ' autocomplete="email"', 'telephone' => ' autocomplete="tel"'][$c['type']] ?? '';
                $h .= '<input type="' . $tp . '" id="' . $idh . '" name="' . e($nom) . '" value="' . e($val) . '"' . $ph . $extra . $auto . $aria . '>';
        }
        if ($groupe) {
            $h .= '</fieldset>';
        }
        if ($err !== '') {
            $h .= '<div class="msg" id="' . $idh . '_e">' . e($err) . '</div>';
        }
        $h .= '</div>';
    }
    return $h;
}

/** Script de l'affichage conditionnel (« si = »). */
function ins_script_conditions(): string
{
    return <<<'JS'
<script>
/* Affichage conditionnel (« si = » dans le descripteur). Sans JavaScript, tout reste visible
   et le serveur applique les mêmes règles. */
(function () {
  var form = document.querySelector('form');
  if (!form) return;
  var blocs = form.querySelectorAll('[data-si]');
  if (!blocs.length) return;
  function valeurs(nom) {
    var bloc = form.querySelector('.champ[data-nom="' + nom + '"]');
    if (!bloc || bloc.classList.contains('cache')) return null;
    var type = bloc.getAttribute('data-type'), res = [];
    bloc.querySelectorAll('input,select,textarea').forEach(function (el) {
      if (type === 'case') res.push(el.checked ? '1' : '0');
      else if (el.type === 'radio' || el.type === 'checkbox') { if (el.checked) res.push(el.value); }
      else if (el.value !== '') res.push(el.value);
    });
    return res.map(function (v) { return v.trim().toLowerCase(); });
  }
  function maj() {
    blocs.forEach(function (bloc) {
      var conds = JSON.parse(bloc.getAttribute('data-si')), vis = true;
      conds.forEach(function (c) {
        var v = valeurs(c.champ);
        if (v === null) { vis = false; return; }
        var trouve = c.valeurs.some(function (x) { return v.indexOf(x.toLowerCase()) >= 0; });
        if (trouve !== c.egal) vis = false;
      });
      bloc.classList.toggle('cache', !vis);
      bloc.querySelectorAll('input,select,textarea').forEach(function (el) { el.disabled = !vis; });
    });
  }
  form.addEventListener('change', maj);
  maj(); maj(); // deux passes pour les dépendances en chaîne
})();
</script>
JS;
}

/* ------------------------------------------------------------------ */
/* Éditeur : formulaire ⇄ modèle ⇄ texte .ini                         */
/* ------------------------------------------------------------------ */

// Propriétés de champ utiles selon le type (les autres sont ignorées à l'écriture)
const INS_PROPRIETES_TYPE = [
    'texte'      => ['aide', 'obligatoire', 'defaut', 'exemple', 'min', 'max', 'unique'],
    'texte_long' => ['aide', 'obligatoire', 'defaut', 'exemple', 'min', 'max'],
    'email'      => ['aide', 'obligatoire', 'defaut', 'exemple', 'unique'],
    'telephone'  => ['aide', 'obligatoire', 'defaut', 'exemple', 'unique'],
    'nombre'     => ['aide', 'obligatoire', 'defaut', 'exemple', 'min', 'max', 'unique'],
    'date'       => ['aide', 'obligatoire', 'defaut', 'min', 'max'],
    'choix'      => ['aide', 'obligatoire', 'defaut', 'options'],
    'liste'      => ['aide', 'obligatoire', 'defaut', 'options'],
    'cases'      => ['aide', 'obligatoire', 'defaut', 'options'],
    'echelle'    => ['aide', 'obligatoire', 'defaut', 'options', 'gauche', 'droite'],
    'case'       => ['aide', 'obligatoire', 'defaut'],
    'titre'      => [],
    'info'       => [],
];

/** Modèle éditable (tableaux simples, prêt pour JSON) d'un formulaire analysé. */
function ins_modele(array $form): array
{
    $g = [];
    foreach (['titre', 'intro', 'merci', 'bouton', 'fichier', 'notifier', 'langue', 'acces'] as $k) {
        $g[$k] = (string)$form[$k];
    }
    // vide = nom du formulaire (défaut) : ne pas figer un nom provisoire ou celui d'un autre formulaire
    $g['fichier'] = $form['fichier'] === $form['id'] ? '' : $form['fichier'];
    $g['ouverture'] = $form['ouverture_texte'];
    $g['fermeture'] = $form['fermeture_texte'];
    $g['places'] = (int)$form['places'];
    $g['diffusion'] = $form['acces'] !== '' ? true : (bool)$form['diffusion'];
    $g['afficher_numero'] = (bool)$form['afficher_numero'];
    $g['modifiable'] = $form['acces'] !== '' ? (bool)$form['modifiable'] : true;
    $g['langues'] = array_values($form['langues']);
    $g['trad'] = (object)$form['trad'];
    $champs = [];
    foreach ($form['champs'] as $c) {
        $champs[] = [
            'nom' => $c['nom'], 'type' => $c['type'], 'libelle' => $c['libelle'], 'aide' => $c['aide'],
            'obligatoire' => (bool)$c['obligatoire'], 'options' => array_values($c['options']), 'defaut' => $c['defaut'],
            'min' => (string)($c['min'] ?? ''), 'max' => (string)($c['max'] ?? ''), 'unique' => (bool)$c['unique'],
            'exemple' => $c['exemple'], 'gauche' => $c['gauche'], 'droite' => $c['droite'],
            'si' => array_map(function ($x) { return ['champ' => $x['champ'], 'egal' => $x['egal'], 'valeurs' => array_values($x['valeurs'])]; }, $c['si']),
            'trad' => (object)$c['trad'],
        ];
    }
    return ['formulaire' => $g, 'champs' => $champs];
}

/** Valeur entre guillemets pour le .ini (antislash et guillemet échappés). */
function ins_ini_texte(string $v): string
{
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
}

function ins_modele_texte($v): string
{
    return is_scalar($v) ? trim(str_replace("\r\n", "\n", (string)$v)) : '';
}

function ins_modele_liste($v): array
{
    if (is_string($v)) {
        $v = preg_split('/\R|\|/', $v) ?: [];
    }
    return is_array($v) ? array_values(array_filter(array_map('ins_modele_texte', $v), 'strlen')) : [];
}

/** Écrit le texte .ini d'un modèle (venu de l'éditeur : chaque valeur est contrôlée). */
function ins_modele_vers_ini(string $id, array $m): string
{
    $g = is_array($m['formulaire'] ?? null) ? $m['formulaire'] : [];
    $champs = is_array($m['champs'] ?? null) ? array_values($m['champs']) : [];
    $trad = function ($tab, string $lg, string $cle) {
        $tab = is_object($tab) ? (array)$tab : (is_array($tab) ? $tab : []);
        $t = $tab[$lg] ?? null;
        $t = is_object($t) ? (array)$t : (is_array($t) ? $t : []);
        return $t[$cle] ?? null;
    };

    $base = ins_minuscules(ins_modele_texte($g['langue'] ?? '')) ?: (string)ins_config()['langue'];
    $langues = array_values(array_unique(array_merge([$base], array_map('ins_minuscules', ins_modele_liste($g['langues'] ?? [])))));
    foreach ($langues as $lg) {
        if (!preg_match('/^[a-z]{2,3}(-[a-z]{2,4})?$/', $lg)) {
            throw new RuntimeException(t('err_langue', ['f' => "$id.ini", 'langue' => $lg]));
        }
    }
    $autres = array_slice($langues, 1);
    // Garde-fous : rien de ce qui vient du navigateur ne doit pouvoir créer une section ou une clé
    foreach ($champs as $c) {
        $nom = is_array($c) ? ins_modele_texte($c['nom'] ?? '') : '';
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/i', $nom) || in_array(strtolower($nom), INS_NOMS_RESERVES, true)) {
            throw new RuntimeException(t('err_nom_champ', ['f' => "$id.ini", 'nom' => $nom]));
        }
        $type = ins_modele_texte($c['type'] ?? '');
        if (!in_array($type, INS_TYPES, true)) {
            throw new RuntimeException(t('err_type', ['f' => "$id.ini", 'nom' => $nom, 'type' => $type, 'types' => implode(', ', INS_TYPES)]));
        }
    }
    if (!in_array(ins_modele_texte($g['acces'] ?? ''), ['', 'personnel', 'lien'], true)) {
        throw new RuntimeException(t('err_acces_mode', ['f' => "$id.ini", 'valeur' => ins_modele_texte($g['acces'] ?? '')]));
    }

    $l = ['; ' . t('edi_ini_entete', ['date' => date('Y-m-d H:i')]), '', '[formulaire]'];
    $ecrire = function (array &$l, string $cle, $val, array $trads = []) {
        if (is_array($val)) {
            $val = implode(' | ', $val);
        }
        if ($val !== '' && $val !== null) {
            $l[] = $cle . ' = ' . ins_ini_texte((string)$val);
        }
        foreach ($trads as $lg => $tv) {
            if (is_array($tv)) {
                $tv = implode(' | ', ins_modele_liste($tv));
            }
            $tv = ins_modele_texte($tv);
            if ($tv !== '') {
                $l[] = $cle . '_' . $lg . ' = ' . ins_ini_texte($tv);
            }
        }
    };
    foreach (['titre', 'intro', 'merci', 'bouton'] as $k) {
        $tr = [];
        foreach ($autres as $lg) {
            $tr[$lg] = $trad($g['trad'] ?? [], $lg, $k);
        }
        $ecrire($l, $k, ins_modele_texte($g[$k] ?? ''), $tr);
    }
    $fichier = ins_modele_texte($g['fichier'] ?? '');
    if ($fichier !== '' && $fichier !== $id) {
        $ecrire($l, 'fichier', $fichier);
    }
    foreach (['ouverture', 'fermeture'] as $k) {
        $v = str_replace('T', ' ', ins_modele_texte($g[$k] ?? ''));
        $ecrire($l, $k, preg_replace('/ 00:00$/', '', $v) ?? $v);
    }
    if ((int)($g['places'] ?? 0) > 0) {
        $l[] = 'places = ' . (int)$g['places'];
    }
    $ecrire($l, 'notifier', ins_modele_texte($g['notifier'] ?? ''));
    if (count($langues) > 1) {
        $ecrire($l, 'langues', implode(' | ', $langues));
    } elseif ($base !== (string)ins_config()['langue']) {
        $ecrire($l, 'langue', $base);
    }
    $acces = ins_modele_texte($g['acces'] ?? '');
    if ($acces !== '') {
        $ecrire($l, 'acces', $acces);
        if (empty($g['modifiable'])) {
            $l[] = 'modifiable = non';
        }
    } elseif (array_key_exists('diffusion', $g) && empty($g['diffusion'])) {
        $l[] = 'diffusion = non';
    }
    if (array_key_exists('afficher_numero', $g) && empty($g['afficher_numero'])) {
        $l[] = 'afficher_numero = non';
    }

    $types = [];
    foreach ($champs as $c) {
        if (is_array($c)) {
            $types[ins_modele_texte($c['nom'] ?? '')] = ins_modele_texte($c['type'] ?? '');
        }
    }
    foreach ($champs as $c) {
        if (!is_array($c)) {
            continue;
        }
        $nom = ins_modele_texte($c['nom'] ?? '');
        $type = ins_modele_texte($c['type'] ?? 'texte');
        $props = INS_PROPRIETES_TYPE[$type] ?? INS_PROPRIETES_TYPE['texte'];
        $l[] = '';
        $l[] = '[' . $nom . ']';
        $l[] = 'type = ' . $type;
        $tr = function (string $k) use ($c, $autres, $trad) {
            $r = [];
            foreach ($autres as $lg) {
                $r[$lg] = $trad($c['trad'] ?? [], $lg, $k);
            }
            return $r;
        };
        $ecrire($l, 'libelle', ins_modele_texte($c['libelle'] ?? ''), $tr('libelle'));
        foreach (['aide', 'exemple', 'gauche', 'droite'] as $k) {
            if (in_array($k, $props, true)) {
                $ecrire($l, $k, ins_modele_texte($c[$k] ?? ''), $tr($k));
            }
        }
        if (in_array('options', $props, true)) {
            $opts = ins_modele_liste($c['options'] ?? []);
            if (!($type === 'echelle' && $opts === ['1', '2', '3', '4', '5'])) {
                $ecrire($l, 'options', $opts, $tr('options'));
            } else {
                $ecrire($l, 'options', '', $tr('options'));
            }
        }
        if (in_array('obligatoire', $props, true) && !empty($c['obligatoire'])) {
            $l[] = 'obligatoire = oui';
        }
        foreach (['defaut', 'min', 'max'] as $k) {
            if (in_array($k, $props, true)) {
                $v = ins_modele_texte($c[$k] ?? '');
                if ($k === 'defaut' && $type === 'case') {
                    $v = $v !== '' && ins_oui($v) ? 'oui' : '';
                }
                $ecrire($l, $k, $v);
            }
        }
        if (in_array('unique', $props, true) && !empty($c['unique'])) {
            $l[] = 'unique = oui';
        }
        $conds = [];
        foreach (is_array($c['si'] ?? null) ? $c['si'] : [] as $cond) {
            if (!is_array($cond)) {
                continue;
            }
            $cible = ins_modele_texte($cond['champ'] ?? '');
            $vals = ins_modele_liste($cond['valeurs'] ?? []);
            if ($cible === '' || !$vals) {
                continue;
            }
            if (($types[$cible] ?? '') === 'case') {
                $vals = array_map(function ($v) { return ins_oui($v) ? 'oui' : 'non'; }, $vals);
            }
            $conds[] = $cible . (empty($cond['egal']) ? ' != ' : ' = ') . implode(' | ', $vals);
        }
        $ecrire($l, 'si', implode(' & ', $conds));
    }
    return implode("\n", $l) . "\n";
}

/** Document HTML complet de l'aperçu d'un formulaire (pour l'iframe de l'éditeur). */
function ins_apercu_document(array $form, string $langue, string $choisi = ''): string
{
    $avant = ins_langue();
    // Textes de l'éditeur : dans la langue de l'administration ; le formulaire : dans sa langue
    $bandeau = t('edi_apercu_bandeau');
    $noteAcces = $form['acces'] !== '' ? t($form['acces'] === 'lien' ? 'edi_apercu_lien' : 'edi_apercu_personnel') : '';
    $lg = in_array($langue, $form['langues'], true) ? $langue : $form['langue'];
    ins_langue($lg);
    $f = ins_localiser($form, $lg);
    $h = '<!DOCTYPE html><html lang="' . e($lg) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<style>' . ins_css() . 'main{padding-top:.5rem}.champ,h2,.info{cursor:pointer}.choisi{outline:3px solid #1f5f8b;outline-offset:2px}'
        . '.bandeau{font-size:.8rem;color:#57606a;border-bottom:1px dashed #c9d2dc;padding:.2rem 0;margin-bottom:.5rem}</style></head><body><main>';
    $h .= '<div class="bandeau" lang="' . e($avant) . '">' . e($bandeau) . '</div>';
    $h .= ins_liens_langues($f['langues'], $lg);
    $h .= '<h1>' . e($f['titre']) . '</h1>';
    if ($noteAcces !== '') {
        $h .= '<p class="petit" lang="' . e($avant) . '">' . e($noteAcces) . '</p>';
    }
    if ($f['intro'] !== '') {
        $h .= '<div class="info">' . nl2br(e($f['intro'])) . '</div>';
    }
    $h .= '<form onsubmit="return false" novalidate>' . ins_rendu_champs($f, [], [], true)
        . '<p class="petit"><span class="req">*</span> ' . e(t('champ_obligatoire')) . '</p>'
        . '<p><button type="button" disabled>' . e($f['bouton'] !== '' ? $f['bouton'] : t('bouton_defaut')) . '</button></p></form>';
    $h .= ins_script_conditions();
    // Clic sur un champ → sélection dans l'éditeur ; liens de langue → changement de langue d'aperçu
    $h .= '<script>(function(){var choisi=' . json_encode($choisi) . ';'
        . 'function marque(n){document.querySelectorAll(".choisi").forEach(function(x){x.classList.remove("choisi")});'
        . 'var el=n&&document.querySelector("[data-nom=\""+n+"\"],[data-bloc=\""+n+"\"]");if(el){el.classList.add("choisi");}}'
        . 'document.addEventListener("click",function(ev){var a=ev.target.closest("a[hreflang]");if(a){ev.preventDefault();'
        . 'parent.postMessage({ins:"langue",langue:a.getAttribute("hreflang")},"*");return;}'
        . 'var b=ev.target.closest("[data-nom],[data-bloc]");if(b){var n=b.getAttribute("data-nom")||b.getAttribute("data-bloc");'
        . 'marque(n);parent.postMessage({ins:"choisir",nom:n},"*");}});'
        . 'window.addEventListener("message",function(ev){if(ev.data&&ev.data.ins==="marquer"){marque(ev.data.nom);}});marque(choisi);})();</script>';
    $h .= '</main></body></html>';
    ins_langue($avant);
    return $h;
}

/** Modèles de départ pour un nouveau formulaire. */
function ins_modeles_depart(): array
{
    return [
        'vide' => "[formulaire]\ntitre = " . ins_ini_texte(t('edi_modele_vide_titre')) . "\n\n[nom]\ntype = texte\nlibelle = "
            . ins_ini_texte(t('edi_modele_nom')) . "\nobligatoire = oui\n",
        'inscription' => "[formulaire]\ntitre = " . ins_ini_texte(t('edi_modele_inscription_titre')) . "\nplaces = 50\n\n"
            . "[nom]\ntype = texte\nlibelle = " . ins_ini_texte(t('edi_modele_nom')) . "\nobligatoire = oui\n\n"
            . "[email]\ntype = email\nlibelle = " . ins_ini_texte(t('edi_modele_email')) . "\nobligatoire = oui\nunique = oui\n\n"
            . "[presence]\ntype = choix\nlibelle = " . ins_ini_texte(t('edi_modele_presence')) . "\noptions = " . ins_ini_texte(t('edi_modele_presence_options')) . "\nobligatoire = oui\n\n"
            . "[remarques]\ntype = texte_long\nlibelle = " . ins_ini_texte(t('edi_modele_remarques')) . "\n",
        'sondage' => "[formulaire]\ntitre = " . ins_ini_texte(t('edi_modele_sondage_titre')) . "\nafficher_numero = non\n\n"
            . "[satisfaction]\ntype = echelle\nlibelle = " . ins_ini_texte(t('edi_modele_satisfaction')) . "\ngauche = " . ins_ini_texte(t('edi_modele_gauche'))
            . "\ndroite = " . ins_ini_texte(t('edi_modele_droite')) . "\nobligatoire = oui\n\n"
            . "[commentaires]\ntype = texte_long\nlibelle = " . ins_ini_texte(t('edi_modele_commentaires')) . "\n",
    ];
}

/* ------------------------------------------------------------------ */
/* Gabarit HTML                                                       */
/* ------------------------------------------------------------------ */

function ins_entete(string $titre): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    echo '<!DOCTYPE html><html lang="' . e(ins_langue()) . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex">'
        . '<title>' . e($titre) . '</title><style>' . ins_css() . '</style></head><body><main>';
}

function ins_pied(): void
{
    echo '</main></body></html>';
}

function ins_css(): string
{
    return <<<CSS
:root{--c:#1f5f8b;--err:#b00020;--fond:#f5f7fa;--bord:#c9d2dc}
*{box-sizing:border-box}
body{margin:0;background:var(--fond);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1b1f23}
main{max-width:46rem;margin:0 auto;padding:1.5rem 1rem 3rem}
main.large{max-width:none}
h1{font-size:1.6rem;margin:.5rem 0 1rem;color:var(--c)}
h2{font-size:1.2rem;margin:1.8rem 0 .6rem;border-bottom:1px solid var(--bord);padding-bottom:.2rem}
.champ{background:#fff;border:1px solid var(--bord);border-radius:8px;padding:.8rem 1rem;margin:.7rem 0}
.champ.erreur{border-color:var(--err)}
label.lib,.lib{display:block;font-weight:600;margin-bottom:.35rem}
.req{color:var(--err)}
.aide{font-size:.9rem;color:#57606a;margin:.1rem 0 .4rem}
.msg{color:var(--err);font-size:.92rem;margin-top:.3rem}
input[type=text],input[type=email],input[type=tel],input[type=number],input[type=date],input[type=password],input[type=url],select,textarea{width:100%;padding:.5rem;border:1px solid var(--bord);border-radius:6px;font:inherit;background:#fff}
textarea{min-height:6rem;resize:vertical}
.opt{display:flex;gap:.5rem;align-items:flex-start;margin:.25rem 0;font-weight:normal}
.opt input{margin-top:.3rem}
.btn,button{display:inline-block;background:var(--c);color:#fff;border:0;border-radius:6px;padding:.5rem 1rem;font:inherit;cursor:pointer;text-decoration:none;margin:.15rem 0}
.btn.sec,button.sec{background:#57606a}
.btn.dang,button.dang{background:var(--err)}
.alerte{background:#fdecee;border:1px solid var(--err);color:var(--err);border-radius:6px;padding:.7rem 1rem;margin:1rem 0}
.ok{background:#e8f5e9;border:1px solid #2e7d32;border-radius:6px;padding:1rem;margin:1rem 0}
.ok code{font-size:1.3rem;font-weight:700}
.info{background:#eef4fa;border-radius:6px;padding:.7rem 1rem}
.pot{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}
.cache{display:none}
.echelle{display:inline-block;max-width:100%}
.echelle .notes{display:flex;flex-wrap:wrap;gap:.2rem}
.echelle .opt{flex-direction:column;align-items:center;gap:0;margin:0;min-width:2.4rem}
.echelle .bouts{display:flex;justify-content:space-between;gap:1rem;font-size:.88rem;color:#57606a;margin-top:.2rem}
.lien{display:flex;gap:.4rem;align-items:center;margin:.4rem 0}
.lien input{flex:1;font-family:ui-monospace,monospace;font-size:.85rem}
.etiq{display:inline-block;font-size:.8rem;border-radius:4px;padding:0 .4rem;background:#e9eef3}
.etiq.prive{background:#fff3cd}
details{margin:.4rem 0}
summary{cursor:pointer;color:var(--c)}
table{border-collapse:collapse;background:#fff;font-size:.9rem;width:100%}
th,td{border:1px solid var(--bord);padding:.3rem .5rem;text-align:left;vertical-align:top}
th{background:#e9eef3;position:sticky;top:0}
.defile{overflow:auto;max-height:75vh}
.petit{font-size:.88rem;color:#57606a}
td code{white-space:nowrap}
td.actions form{display:block;margin:.15rem 0}
td.actions button{width:100%;white-space:nowrap}
.langues{text-align:right;font-size:.9rem}
@media (max-width:30rem){.echelle .notes{gap:0}.echelle .opt{min-width:1.6rem}main{padding-left:.6rem;padding-right:.6rem}.champ{padding:.7rem .7rem}}
CSS;
}
