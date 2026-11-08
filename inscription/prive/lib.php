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
const INS_COLONNES_ACCES = ['identifiant', 'nom', 'type', 'secret', 'etat', 'cree', 'repondu', 'modifie', 'echecs', 'bloque', 'email', 'telephone'];
const INS_ACCES_ECHECS_MAX = 10;      // essais de mot de passe avant blocage temporaire
const INS_ACCES_BLOCAGE = 15 * 60;    // durée du blocage (secondes)
const INS_ACCES_DUREE = 2 * 3600;     // durée de la connexion après saisie du mot de passe
// Textes traduisibles : « libelle_en = ... » donne la traduction anglaise de « libelle »
const INS_TRAD_FORMULAIRE = ['titre', 'intro', 'merci', 'bouton', 'message', 'logo_texte', 'confirmation_texte'];
// Images (logos) : rangées dans prive/images/, servies par index.php?image=… ; SVG refusé (peut contenir du script)
const INS_TYPES_IMAGE = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
const INS_MIME_IMAGE = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
const INS_TRAD_CHAMP = ['libelle', 'aide', 'exemple', 'gauche', 'droite', 'options', 'lignes'];
const INS_TYPES = [
    'texte', 'texte_long', 'email', 'telephone', 'nombre', 'date',
    'choix', 'liste', 'cases', 'case', 'echelle', 'grille', 'commande', 'titre', 'info',
];
// Catalogues de produits (prive/catalogues/<nom>.csv) pour les champs « commande »
const INS_COLONNES_CATALOGUE = ['id', 'categorie', 'produit', 'unite', 'prix', 'pas', 'disponible'];
// États d'une commande dans l'administration
const INS_STATUTS_COMMANDE = ['nouvelle', 'preparee', 'remise', 'annulee'];
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
    'grid' => 'grille', 'matrix' => 'grille', 'tableau' => 'grille', 'matrice' => 'grille',
    'order' => 'commande', 'panier' => 'commande', 'basket' => 'commande',
    'section' => 'titre', 'title' => 'titre', 'heading' => 'titre', 'texte_info' => 'info',
];
// Clés acceptées dans les descripteurs : nom interne => écritures possibles (français, anglais)
const INS_CLES_FORMULAIRE = [
    'titre' => ['titre', 'title'], 'intro' => ['intro', 'introduction'], 'merci' => ['merci', 'thanks'],
    'bouton' => ['bouton', 'button'], 'fichier' => ['fichier', 'file'], 'message' => ['message'],
    'logo' => ['logo'], 'logo_texte' => ['logo_texte', 'logo_text', 'logo_alt'],
    'confirmation' => ['confirmation', 'confirm'], 'confirmation_texte' => ['confirmation_texte', 'confirmation_text'],
    'ouverture' => ['ouverture', 'opens'], 'fermeture' => ['fermeture', 'closes'],
    'places' => ['places', 'limit'], 'notifier' => ['notifier', 'notify'],
    'diffusion' => ['diffusion', 'listed'], 'afficher_numero' => ['afficher_numero', 'show_number'],
    'langue' => ['langue', 'language'], 'langues' => ['langues', 'languages'], 'cache' => ['cache', 'hidden'],
    'acces' => ['acces', 'accès', 'access'], 'modifiable' => ['modifiable', 'editable'],
];
const INS_CLES_CHAMP = [
    'type' => ['type'], 'libelle' => ['libelle', 'label', 'texte'], 'aide' => ['aide', 'help'],
    'obligatoire' => ['obligatoire', 'required'], 'options' => ['options', 'colonnes', 'columns'], 'defaut' => ['defaut', 'default'],
    'lignes' => ['lignes', 'rows'], 'multiple' => ['multiple'],
    'min' => ['min', 'minimum'], 'max' => ['max', 'maximum'], 'entier' => ['entier', 'integer'], 'unique' => ['unique'], 'exemple' => ['exemple', 'placeholder'],
    'gauche' => ['gauche', 'left'], 'droite' => ['droite', 'right'], 'si' => ['si', 'if'],
    'catalogue' => ['catalogue', 'catalog'],
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
            'devise'              => '€',
            'conditions_ou'       => false,
            'confirmation'        => true,
            'confirmation_max_heure' => 30,
            'logo'                => '',
            'logo_texte'          => '',
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

/**
 * Texte affiché avec liens : [texte du lien](https://…) ou adresse nue https://… ; mailto: accepté.
 * Tout le reste est échappé ; les liens s'ouvrent dans un nouvel onglet. Retours à la ligne conservés.
 */
function ins_texte(string $s): string
{
    $motif = '~\[([^\[\]\n]{1,200})\]\(\s*((?:https?://|mailto:)[^\s()<>"]+)\s*\)|\bhttps?://[^\s<>"]+~i';
    $h = '';
    $pos = 0;
    preg_match_all($motif, $s, $ms, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($ms as $m) {
        $h .= e(substr($s, $pos, $m[0][1] - $pos));
        $pos = $m[0][1] + strlen($m[0][0]);
        if (isset($m[2])) {
            [$texte, $url] = [$m[1][0], $m[2][0]];
        } else {
            $url = rtrim($m[0][0], '.,;:!?)»');            // ponctuation finale : hors du lien
            $pos -= strlen($m[0][0]) - strlen($url);
            $texte = $url;
        }
        $h .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($texte) . '</a>';
    }
    return nl2br($h . e(substr($s, $pos)));
}

/** Même texte pour un courriel (texte brut) : [texte](adresse) → « texte (adresse) ». */
function ins_texte_brut(string $s): string
{
    return (string)preg_replace('~\[([^\[\]\n]{1,200})\]\(\s*((?:https?://|mailto:)[^\s()<>"]+)\s*\)~i', '$1 ($2)', $s);
}

function ins_liste_options(string $v): array
{
    return array_values(array_filter(array_map('trim', explode('|', $v)), 'strlen'));
}

/** Repère d'une ligne de grille, d'après son libellé : « Vendredi 12 » → vendredi_12 (colonne <champ>_vendredi_12). */
function ins_id_ligne(string $libelle): string
{
    $s = function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $libelle) : $libelle;
    $s = trim((string)preg_replace('/[^a-z0-9]+/', '_', strtolower($s)), '_');
    return substr($s, 0, 40) ?: 'l' . substr(sha1($libelle), 0, 6);
}

/** Réponse à une grille en une seule valeur lisible : « Vendredi : Oui » une ligne par ligne de la grille. */
function ins_resume_grille(array $c, array $valeurs): string
{
    $r = [];
    foreach ($c['lignes_id'] as $i => $idl) {
        $v = (string)($valeurs[$c['nom'] . '_' . $idl] ?? '');
        if ($v !== '') {
            $r[] = $c['lignes'][$i] . ' : ' . $v;
        }
    }
    return implode("\n", $r);
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
        // Message précis (crochet ou guillemet manquant) quand on trouve la ligne fautive
        $diag = ins_diagnostic_ini($contenu, $f);
        if ($diag !== '') {
            throw new RuntimeException($diag);
        }
        $detail = (string)($err['message'] ?? '?');
        if (preg_match('/ on line (\d+)/', $detail, $m)) {
            $detail = t('err_syntaxe_ligne', ['n' => $m[1]]) . ' ' . preg_replace('/ in Unknown on line \d+$/', '', $detail);
        }
        throw new RuntimeException(t('err_syntaxe', ['f' => $f, 'detail' => $detail]));
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
        'message'         => trim((string)$lit('message')), // message de commande (vide = texte par défaut)
        'logo'            => trim((string)$lit('logo')),    // image de prive/images/ ; vide = logo du site ; « non » = aucun
        'logo_texte'      => trim((string)$lit('logo_texte')),
        'confirmation'    => trim((string)$lit('confirmation')),       // « oui » ou nom du champ e-mail du répondant
        'confirmation_texte' => trim((string)$lit('confirmation_texte')),
        'confirmation_champ' => '',
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
    if (in_array(ins_minuscules($form['logo']), ['non', 'aucun', 'no', 'none'], true)) {
        $form['logo'] = 'non';
    } elseif ($form['logo'] !== '' && !ins_image_valide($form['logo'])) {
        throw new RuntimeException(t('err_logo', ['f' => $f, 'logo' => $form['logo']]));
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
        if ($type === 'grille' && count($options) < 1) {
            throw new RuntimeException(t('err_grille_colonnes', ['f' => $f, 'nom' => $nom]));
        }
        if (in_array($type, ['choix', 'liste', 'cases'], true) && count($options) < 1) {
            throw new RuntimeException(t('err_options', ['f' => $f, 'nom' => $nom, 'type' => $type]));
        }
        $lignes = $type === 'grille' ? ins_liste_options((string)$lc('lignes')) : [];
        $lignesId = [];
        if ($type === 'grille') {
            if (!$lignes) {
                throw new RuntimeException(t('err_grille_lignes', ['f' => $f, 'nom' => $nom]));
            }
            foreach ($lignes as $lg) {
                $idl = ins_id_ligne($lg);
                if (in_array($idl, $lignesId, true)) {
                    throw new RuntimeException(t('err_grille_double', ['f' => $f, 'nom' => $nom, 'ligne' => $lg]));
                }
                $lignesId[] = $idl;
            }
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
            'entier'      => $type === 'nombre' && ins_oui($lc('entier')), // nombre entier seulement
            'unique'      => ins_oui($lc('unique')),
            'exemple'     => trim((string)$lc('exemple')),
            'gauche'      => trim((string)$lc('gauche')),
            'droite'      => trim((string)$lc('droite')),
            'si'          => [],
            'si_ou'       => false, // vrai : au moins une condition (||) ; faux : toutes (&)
            'trad'        => [],
            'catalogue'   => '',
            'lignes'      => $lignes,   // grille : libellés des lignes (traduisibles)
            'lignes_id'   => $lignesId, // grille : repères des lignes → colonnes <nom>_<repère> du CSV
            'multiple'    => $type === 'grille' && ins_oui($lc('multiple')),
        ];
        if ($type === 'commande') {
            $cat = trim((string)$lc('catalogue'));
            if (!ins_id_valide($cat)) {
                throw new RuntimeException(t('err_catalogue_cle', ['f' => $f, 'nom' => $nom]));
            }
            if (!is_file(ins_fichier_catalogue($cat))) {
                throw new RuntimeException(t('err_catalogue_absent', ['f' => $f, 'nom' => $nom, 'catalogue' => $cat]));
            }
            foreach ($form['champs'] as $autre) {
                if ($autre['type'] === 'commande') {
                    throw new RuntimeException(t('err_commande_unique', ['f' => $f, 'nom' => $nom]));
                }
            }
            $champ['catalogue'] = $cat;
        }
        foreach (array_slice($langues, 1) as $lg) {
            foreach (INS_TRAD_CHAMP as $cle) {
                $v = ins_cle_trad($p, INS_CLES_CHAMP[$cle], $lg);
                if ($v === null || $v === '') {
                    continue;
                }
                if ($cle === 'options' || $cle === 'lignes') {
                    $v = ins_liste_options($v);
                    $attendu = $cle === 'options' ? $options : $lignes;
                    if (count($v) !== count($attendu)) {
                        $cleTrad = $cle === 'lignes' ? 'lignes' : ($type === 'grille' ? 'colonnes' : 'options');
                        throw new RuntimeException(t('err_liste_trad', ['f' => $f, 'nom' => $nom, 'langue' => $lg, 'n' => count($attendu), 'cle' => $cleTrad]));
                    }
                }
                $champ['trad'][$lg][$cle] = $v;
            }
        }
        if (trim((string)$lc('si')) !== '') {
            $champ['si'] = ins_conditions((string)$lc('si'), $vus, $f, $nom);
            $champ['si_ou'] = strpos((string)$lc('si'), '||') !== false;
        }
        ins_verifier_bornes($champ, $f);
        $form['champs'][$nom] = $champ;
        if (!in_array($type, INS_TYPES_DECORATIFS, true)) {
            $vus[$nom] = $champ;
        }
    }
    if (!$vus) {
        throw new RuntimeException(t('err_aucun_champ', ['f' => $f]));
    }
    foreach ($form['champs'] as $c) {
        if ($c['type'] === 'commande' && isset($form['champs'][$c['nom'] . '_total'])) {
            throw new RuntimeException(t('err_nom_champ', ['f' => $f, 'nom' => $c['nom'] . '_total']));
        }
    }
    // Courriel de confirmation au répondant : vers le champ e-mail désigné, ou le premier champ e-mail
    $conf = $form['confirmation'];
    if (in_array(ins_minuscules($conf), ['', 'non', 'no', 'false', '0'], true)) {
        $form['confirmation'] = '';
    } else {
        $champsMail = array_keys(array_filter($form['champs'], function ($c) { return $c['type'] === 'email'; }));
        $cible = ins_oui($conf) ? ($champsMail[0] ?? '') : $conf;
        if (!in_array($cible, $champsMail, true)) {
            throw new RuntimeException(t('err_confirmation', ['f' => $f, 'champ' => ins_oui($conf) ? '' : $conf]));
        }
        $form['confirmation'] = ins_oui($conf) ? 'oui' : $conf;
        $form['confirmation_champ'] = $cible;
    }
    // Colonnes en double (une ligne de grille « vehiculer_samedi » et un champ du même nom…)
    $doubles = array_keys(array_filter(array_count_values(ins_colonnes($form + ['acces' => ''])), function ($n) { return $n > 1; }));
    if ($doubles) {
        throw new RuntimeException(t('err_colonne_double', ['f' => $f, 'colonne' => $doubles[0]]));
    }
    return $form;
}

/**
 * Contrôle min / max à la lecture du descripteur : nombres (virgule ou point) pour un nombre,
 * dates AAAA-MM-JJ pour une date, longueurs entières pour un texte ; min ≤ max ; valeur par défaut dans les bornes.
 */
function ins_verifier_bornes(array $c, string $f): void
{
    if ($c['min'] === null && $c['max'] === null) {
        return;
    }
    $genre = ['nombre' => 'nombre', 'date' => 'date', 'texte' => 'longueur', 'texte_long' => 'longueur'][$c['type']] ?? '';
    if ($genre === '') {
        return; // min / max sans effet sur ce type
    }
    $lire = function ($v) use ($genre) {
        if ($genre === 'longueur') {
            return preg_match('/^\d+$/', trim($v)) ? (int)$v : null;
        }
        return ins_valeur_comparable($genre, $v);
    };
    $b = [];
    foreach (['min', 'max'] as $k) {
        if ($c[$k] === null) {
            continue;
        }
        $b[$k] = $lire($c[$k]);
        if ($b[$k] === null) {
            throw new RuntimeException(t('err_borne_' . $genre, ['f' => $f, 'nom' => $c['nom'], 'cle' => $k, 'valeur' => $c[$k]]));
        }
    }
    if (isset($b['min'], $b['max']) && $b['min'] > $b['max']) {
        throw new RuntimeException(t('err_bornes_ordre', ['f' => $f, 'nom' => $c['nom'], 'min' => $c['min'], 'max' => $c['max']]));
    }
    if ($genre !== 'longueur' && $c['defaut'] !== '') {
        $d = ins_valeur_comparable($genre, $c['defaut']);
        if ($d === null || (isset($b['min']) && $d < $b['min']) || (isset($b['max']) && $d > $b['max'])) {
            throw new RuntimeException(t('err_defaut_bornes', ['f' => $f, 'nom' => $c['nom'], 'defaut' => $c['defaut']]));
        }
    }
}

/** Bornes d'un nombre en clair, pour l'aide sous le libellé : « Entre 1 et 30 », « Au moins 1 », « Nombre entier »… */
function ins_aide_bornes(array $c): string
{
    if ($c['type'] !== 'nombre') {
        return '';
    }
    $f = function ($v) { return str_replace('.', t('decimale'), (string)$v); };
    if ($c['min'] !== null && $c['max'] !== null) {
        $txt = t('borne_entre', ['min' => $f($c['min']), 'max' => $f($c['max'])]);
    } elseif ($c['min'] !== null) {
        $txt = t('borne_min', ['min' => $f($c['min'])]);
    } elseif ($c['max'] !== null) {
        $txt = t('borne_max', ['max' => $f($c['max'])]);
    } else {
        $txt = '';
    }
    if ($c['entier']) {
        $txt = $txt === '' ? t('borne_entier') : t('borne_entier_et', ['bornes' => $txt]);
    }
    return $txt;
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
 * Cherche la ligne fautive d'un .ini illisible : section sans crochet fermant ou ouvrant,
 * texte après « ] », ligne qui n'est ni [section] ni clé = valeur, guillemet jamais refermé.
 * @return string message d'erreur, ou '' si rien de précis n'est trouvé
 */
function ins_diagnostic_ini(string $contenu, string $f): string
{
    $lignes = preg_split('/\R/', $contenu) ?: [];
    $ouvert = 0; // ligne où un texte entre guillemets a commencé sans être refermé
    foreach ($lignes as $i => $brute) {
        $n = $i + 1;
        $l = trim($brute);
        $court = mb_strimwidth($l, 0, 60, '…');
        if ($ouvert) {
            if (ins_guillemets_impairs($l)) {
                $ouvert = 0;
            }
            continue;
        }
        if ($l === '' || $l[0] === ';' || $l[0] === '#') {
            continue;
        }
        if ($l[0] === '[') {
            if (strpos($l, ']') === false) {
                return t('err_ini_crochet_fermant', ['f' => $f, 'n' => $n, 'ligne' => $court]);
            }
            if (!preg_match('/^\[[^\]\[]*\]\s*(;.*)?$/', $l)) {
                return t('err_ini_section', ['f' => $f, 'n' => $n, 'ligne' => $court]);
            }
            continue;
        }
        $egal = strpos($l, '=');
        if ($egal === false) {
            return t(preg_match('/^[^"]*\]\s*(;.*)?$/', $l) ? 'err_ini_crochet_ouvrant' : 'err_ini_ligne', ['f' => $f, 'n' => $n, 'ligne' => $court]);
        }
        if (ins_guillemets_impairs(substr($l, $egal + 1))) {
            $ouvert = $n;
        }
    }
    return $ouvert ? t('err_ini_guillemet', ['f' => $f, 'n' => $ouvert]) : '';
}

/** Nombre impair de guillemets non échappés (\") dans un morceau de ligne ? */
function ins_guillemets_impairs(string $s): bool
{
    $s = str_replace('\\\\', '', $s);
    return preg_match_all('/(?<!\\\\)"/', $s) % 2 === 1;
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
    // ET (&) ou OU (||), sans mélange ; le OU s'active dans config.php (conditions_ou)
    $ou = strpos($expr, '||') !== false;
    if ($ou && strpos($expr, '&') !== false) {
        throw new RuntimeException(t('err_condition_mixte', ['f' => $f, 'nom' => $nom]));
    }
    if ($ou && !ins_config()['conditions_ou']) {
        throw new RuntimeException(t('err_condition_ou', ['f' => $f, 'nom' => $nom]));
    }
    foreach (explode($ou ? '||' : '&', $expr) as $morceau) {
        $morceau = trim($morceau);
        if ($morceau === '') {
            continue;
        }
        if (!preg_match('/^([a-z][a-z0-9_]*)\s*(!=|<=|>=|=|<|>)\s*(.+)$/is', $morceau, $m)) {
            throw new RuntimeException(t('err_condition', ['f' => $f, 'nom' => $nom, 'cond' => $morceau]));
        }
        $cible = $m[1];
        if (!isset($connus[$cible])) {
            throw new RuntimeException(t('err_condition_ordre', ['f' => $f, 'nom' => $nom, 'cible' => $cible]));
        }
        $valeurs = array_values(array_filter(array_map('trim', explode('|', $m[3])), 'strlen'));
        $c = $connus[$cible];
        if (in_array($c['type'], ['grille', 'commande'], true)) {
            throw new RuntimeException(t('err_condition_grille', ['f' => $f, 'nom' => $nom, 'cible' => $cible]));
        }
        $op = $m[2];
        $comparaison = !in_array($op, ['=', '!='], true);
        // Comparaison de nombres ou de dates : champ nombre, date, ou échelle dont les notes sont des nombres
        $num = in_array($c['type'], ['nombre', 'date'], true) || ($c['type'] === 'echelle' && $comparaison);
        if ($comparaison && !$num) {
            throw new RuntimeException(t('err_condition_comparaison', ['f' => $f, 'nom' => $nom, 'cible' => $cible, 'op' => $op]));
        }
        if ($num) {
            if ($comparaison && count($valeurs) !== 1) {
                throw new RuntimeException(t('err_condition_valeur', ['f' => $f, 'nom' => $nom, 'cible' => $cible, 'valeur' => trim($m[3])]));
            }
            if ($c['type'] === 'echelle' && array_filter($c['options'], function ($o) { return ins_valeur_comparable('nombre', $o) === null; })) {
                throw new RuntimeException(t('err_condition_echelle', ['f' => $f, 'nom' => $nom, 'cible' => $cible]));
            }
            foreach ($valeurs as $v) {
                if (ins_valeur_comparable($c['type'] === 'date' ? 'date' : 'nombre', $v) === null) {
                    throw new RuntimeException(t($c['type'] === 'date' ? 'err_condition_date' : 'err_condition_valeur',
                        ['f' => $f, 'nom' => $nom, 'cible' => $cible, 'valeur' => $v]));
                }
            }
            $res[] = ['champ' => $cible, 'op' => $op, 'egal' => $op !== '!=', 'valeurs' => $valeurs, 'num' => $c['type'] === 'date' ? 'date' : 'nombre'];
            continue;
        }
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
        $res[] = ['champ' => $cible, 'op' => $op, 'egal' => $op === '=', 'valeurs' => $valeurs, 'num' => ''];
    }
    return $res;
}

/** Valeur comparable d'une saisie : nombre (virgule ou point) ou date AAAA-MM-JJ ; null si illisible ou vide. */
function ins_valeur_comparable(string $genre, string $v)
{
    $v = trim($v);
    if ($genre === 'date') {
        $d = DateTime::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : null;
    }
    return preg_match('/^-?\d+([.,]\d+)?$/', $v) ? (float)str_replace(',', '.', $v) : null;
}

/** Condition « si » sur un nombre ou une date (=, !=, <, <=, >, >=). Saisie vide ou illisible : condition fausse. */
function ins_comparer(array $cond, string $saisie): bool
{
    $x = ins_valeur_comparable($cond['num'], $saisie);
    if ($x === null) {
        return false;
    }
    $res = [];
    foreach ($cond['valeurs'] as $v) {
        $y = ins_valeur_comparable($cond['num'], (string)$v);
        $res[] = $y === null ? null : ($x < $y ? -1 : ($x > $y ? 1 : 0));
    }
    $res = array_values(array_filter($res, function ($r) { return $r !== null; }));
    if (!$res) {
        return false;
    }
    switch ($cond['op']) {
        case '=':  return in_array(0, $res, true);
        case '!=': return !in_array(0, $res, true);
        case '<':  return $res[0] < 0;
        case '<=': return $res[0] <= 0;
        case '>':  return $res[0] > 0;
        default:   return $res[0] >= 0; // >=
    }
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
            if ($c['type'] === 'grille') {
                foreach ($c['lignes_id'] as $idl) {
                    $cols[] = $c['nom'] . '_' . $idl; // une colonne par ligne de la grille
                }
                continue;
            }
            $cols[] = $c['nom'];
            if ($c['type'] === 'commande') {
                $cols[] = $c['nom'] . '_total'; // total estimé, en nombre
            }
        }
    }
    return $cols;
}

function ins_fichier_donnees(array $form): string
{
    return ins_dossier_donnees() . '/' . $form['fichier'] . '.csv';
}

/** Autres formulaires qui écrivent dans le même fichier de réponses (et les mêmes accès) que $form. */
function ins_formulaires_meme_fichier(array $form): array
{
    $res = [];
    foreach (glob(ins_dossier_formulaires() . '/*.ini') ?: [] as $f) {
        $id = basename($f, '.ini');
        if ($id === $form['id'] || !ins_id_valide($id)) {
            continue;
        }
        try {
            if (ins_charger_formulaire($id)['fichier'] === $form['fichier']) {
                $res[] = $id;
            }
        } catch (Throwable $ex) {
            // un formulaire mal écrit est signalé ailleurs
        }
    }
    return $res;
}

/** Refuse un formulaire dont le fichier de réponses est déjà celui d'un autre formulaire. */
function ins_verifier_fichier_unique(array $form): void
{
    $autres = ins_formulaires_meme_fichier($form);
    if ($autres) {
        throw new RuntimeException(t('err_fichier_partage', ['f' => $form['id'] . '.ini', 'fichier' => $form['fichier'], 'autres' => implode(', ', $autres)]));
    }
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
    if (!$c['si']) {
        return true;
    }
    foreach ($c['si'] as $cond) {
        $vraie = ins_condition_vraie($cond, $valeurs, $visibles, $champs, $langue);
        if ($vraie && $c['si_ou']) {
            return true;  // OU : une condition suffit
        }
        if (!$vraie && !$c['si_ou']) {
            return false; // ET : une condition fausse suffit
        }
    }
    return !$c['si_ou'];
}

/** Une condition « si » : fausse si le champ visé est lui-même masqué. */
function ins_condition_vraie(array $cond, array $valeurs, array $visibles, array $champs, string $langue): bool
{
    $cible = $cond['champ'];
    if (empty($visibles[$cible])) {
        return false;
    }
    $v = $valeurs[$cible] ?? '';
    if (!empty($cond['num'])) {
        return ins_comparer($cond, (string)$v);
    }
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
    return $trouve === $cond['egal'];
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
            foreach ($c['lignes_id'] as $idl) {
                $valeurs[$nom . '_' . $idl] = '';
            }
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
            case 'grille':
                $b = is_array($b) ? $b : [];
                $manquent = [];
                foreach ($c['lignes_id'] as $i => $idl) {
                    $bl = $b[$idl] ?? null;
                    if ($c['multiple']) {
                        $vl = implode(' | ', ins_choisis($c, is_array($bl) ? $bl : null));
                    } else {
                        $vl = ins_choisis($c, is_array($bl) ? null : $bl)[0] ?? '';
                        if ($vl === '' && is_string($bl) && $bl !== '') {
                            $err = t('v_choix_invalide');
                        }
                    }
                    $valeurs[$nom . '_' . $idl] = $vl;
                    if ($vl === '') {
                        $manquent[] = $c['lignes'][$i];
                    }
                }
                if ($err === '' && $c['obligatoire'] && $manquent) {
                    $err = count($manquent) === count($c['lignes']) ? t('v_obligatoire') : t('v_grille_lignes', ['lignes' => implode(', ', $manquent)]);
                }
                $v = ins_resume_grille($c, $valeurs); // pour la notification et les messages
                break;
            case 'commande':
                [$lignesCmd, $err] = ins_valider_commande($c, is_array($b) ? $b : []);
                $total = ins_total_lignes($lignesCmd);
                $v = ins_texte_lignes($lignesCmd);
                $valeurs[$nom . '_total'] = $lignesCmd ? ins_nombre_csv($total) : '';
                $valeurs['__commande'] = ['champ' => $nom, 'lignes' => $lignesCmd, 'total' => $total];
                if ($err === '' && $c['obligatoire'] && !$lignesCmd) {
                    $err = t('cmd_vide');
                }
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
        if ($err === '' && $c['obligatoire'] && !in_array($c['type'], ['case', 'commande', 'grille'], true) && $v === '') {
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
            if ($c['entier'] && !preg_match('/^-?\d+$/', $v)) {
                return t('v_entier');
            }
            $mini = $c['min'] !== null ? ins_valeur_comparable('nombre', (string)$c['min']) : null;
            $maxi = $c['max'] !== null ? ins_valeur_comparable('nombre', (string)$c['max']) : null;
            if (($mini !== null && $n < $mini) || ($maxi !== null && $n > $maxi)) {
                $fmt = function ($x) { return str_replace('.', t('decimale'), (string)$x); };
                return $mini !== null && $maxi !== null ? t('v_entre', ['min' => $fmt($c['min']), 'max' => $fmt($c['max'])])
                    : ($mini !== null ? t('v_au_moins', ['n' => $fmt($c['min'])]) : t('v_au_plus', ['n' => $fmt($c['max'])]));
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
                $ligne[] = ins_csv_sur($acces !== null ? ins_nom_acces($acces) : '');
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

        if (isset($valeurs['__commande'])) {
            ins_enregistrer_detail_commande($form, $numero, $valeurs['__commande'], $index !== null);
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
            // Quantités commandées (pour pré-remplir le tableau de commande)
            $det = ins_lire_commandes($form)[$res['numero'] ?? ''] ?? null;
            if ($det) {
                $res['__commande'] = [];
                foreach ($det['lignes'] as $lg) {
                    $res['__commande'][$lg['id']] = ins_nombre_saisie((float)$lg['qte']);
                }
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
            $a['email'] = ins_csv_brut($a['email']);
            $a['telephone'] = ins_csv_brut($a['telephone']);
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
            $l[] = in_array($nom, ['nom', 'email', 'telephone'], true) ? ins_csv_sur((string)($a[$nom] ?? '')) : (string)($a[$nom] ?? '');
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
/**
 * Une ligne de la liste d'accès : nom et contact, dans n'importe quel ordre et avec n'importe quel séparateur.
 *   « Marie Durand ; marie@exemple.fr », « Jean Martin 06 12 34 56 78 », « paul@exemple.fr »
 * @return array{nom: string, email: string, telephone: string}
 */
function ins_analyser_ligne_acces(string $ligne): array
{
    $l = ' ' . trim($ligne) . ' ';
    $email = '';
    if (preg_match('/[^\s;,<>"()]+@[^\s;,<>"()]+\.[a-z]{2,}/iu', $l, $m) && filter_var($m[0], FILTER_VALIDATE_EMAIL)) {
        $email = $m[0];
        $l = str_replace($m[0], ' ', $l);
    }
    $tel = '';
    if (preg_match('/\+?\(?\d[\d .\-\/()]{6,}\d/', $l, $m)) {
        $tel = trim($m[0]);
        $l = str_replace($m[0], ' ', $l);
    }
    $nom = trim((string)preg_replace('/[\s;,\t<>|]+/u', ' ', $l), " -:");
    return ['nom' => mb_substr($nom, 0, 200), 'email' => mb_substr($email, 0, 200), 'telephone' => mb_substr($tel, 0, 40)];
}

/** Nom affiché d'un accès : son nom, sinon son contact. */
function ins_nom_acces(array $a): string
{
    foreach (['nom', 'email', 'telephone'] as $k) {
        if (($a[$k] ?? '') !== '') {
            return (string)$a[$k];
        }
    }
    return '';
}

/**
 * Champs du formulaire déjà connus grâce à l'accès : le nom va dans le champ « nom » (sinon le premier texte court),
 * l'e-mail dans le premier champ e-mail, le téléphone dans le premier champ téléphone.
 * @return array<string,string> nom du champ => valeur
 */
function ins_champs_connus(array $form, array $acces): array
{
    $cibles = ['nom' => null, 'email' => null, 'telephone' => null];
    if (($form['champs']['nom']['type'] ?? '') === 'texte') {
        $cibles['nom'] = 'nom';
    }
    foreach ($form['champs'] as $c) {
        if ($c['type'] === 'texte' && $cibles['nom'] === null) {
            $cibles['nom'] = $c['nom'];
        } elseif ($c['type'] === 'email' && $cibles['email'] === null) {
            $cibles['email'] = $c['nom'];
        } elseif ($c['type'] === 'telephone' && $cibles['telephone'] === null) {
            $cibles['telephone'] = $c['nom'];
        }
    }
    $res = [];
    foreach ($cibles as $info => $champ) {
        if ($champ !== null && trim((string)($acces[$info] ?? '')) !== '' && empty($form['champs'][$champ]['si'])) {
            $res[$champ] = trim((string)$acces[$info]);
        }
    }
    return $res;
}

/** Les champs connus grâce à l'accès ne sont plus obligatoires (et sont pré-remplis). */
function ins_appliquer_acces(array $form, array $connus): array
{
    foreach ($connus as $champ => $v) {
        $form['champs'][$champ]['obligatoire'] = false;
        $form['champs'][$champ]['aide'] = trim($form['champs'][$champ]['aide'] . "\n" . t('acces_champ_connu'));
    }
    return $form;
}

function ins_creer_acces(array $form, array $noms): array
{
    $type = $form['acces'] === 'lien' ? 'lien' : 'mdp';
    $verrou = ins_verrouiller();
    try {
        $tous = ins_lire_acces($form);
        $crees = [];
        foreach ($noms as $ligne) {
            $info = ins_analyser_ligne_acces((string)$ligne);
            do {
                $id = ins_nouveau_numero();
            } while (isset($tous[$id]));
            [$clair, $empreinte] = ins_nouveau_secret($type);
            $tous[$id] = ['identifiant' => $id, 'nom' => $info['nom'], 'type' => $type, 'secret' => $empreinte,
                'etat' => 'actif', 'cree' => date('Y-m-d H:i:s'), 'repondu' => '', 'modifie' => '', 'echecs' => '0', 'bloque' => '',
                'email' => $info['email'], 'telephone' => $info['telephone']];
            $crees[] = ['identifiant' => $id, 'nom' => $info['nom'], 'email' => $info['email'], 'telephone' => $info['telephone'], 'secret' => $clair];
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
            $v = $c['type'] === 'grille' ? ins_resume_grille($c, $valeurs) : ($valeurs[$nom] ?? '');
            $corps .= ins_libelle_mail($c['libelle']) . (in_array($c['type'], ['commande', 'grille'], true) ? "\n" : ' ') . $v . "\n";
        }
    }
    if (isset($valeurs['__commande'])) {
        $corps .= t('cmd_total_estime') . ' : ' . ins_prix((float)$valeurs['__commande']['total']) . "\n";
    }
    $entetes = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    $exp = (string)ins_config()['expediteur'];
    if ($exp !== '' && filter_var($exp, FILTER_VALIDATE_EMAIL)) {
        $entetes .= "From: $exp\r\n";
    }
    $sujet = '=?UTF-8?B?' . base64_encode(t('mail_sujet', ['titre' => $form['titre']])) . '?=';
    @mail(implode(',', $dest), $sujet, $corps, $entetes);
}

/**
 * Courriel de confirmation au répondant (clé « confirmation » du formulaire, si config.php le permet) :
 * dans la langue du répondant, avec le récapitulatif de sa réponse. Garde-fous contre l'envoi de courriels
 * à des tiers par des robots : au plus un message par adresse et par formulaire toutes les 10 minutes,
 * et au plus « confirmation_max_heure » messages par heure pour tout le site.
 * @return bool vrai si le message a été confié au serveur de courrier
 */
function ins_confirmer(array $form, string $numero, array $valeurs, bool $modification): bool
{
    $cfg = ins_config();
    if (!$cfg['confirmation'] || $form['confirmation_champ'] === '' || !function_exists('mail')) {
        return false;
    }
    $dest = trim((string)($valeurs[$form['confirmation_champ']] ?? ''));
    if (!filter_var($dest, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $dest)) {
        return false;
    }
    if (!ins_quota_confirmation($form['id'] . '|' . ins_minuscules($dest), (int)$cfg['confirmation_max_heure'])) {
        error_log('inscription : courriel de confirmation non envoyé (limite atteinte) pour ' . $form['id']);
        return false;
    }
    $intro = $form['confirmation_texte'] !== '' ? $form['confirmation_texte'] : ($form['merci'] !== '' ? $form['merci'] : t('merci_defaut'));
    $corps = ins_texte_brut($intro) . "\n\n";
    if ($form['afficher_numero']) {
        $corps .= t('numero_reponse') . ' ' . $numero . "\n\n";
    }
    $corps .= t($modification ? 'conf_recap_modif' : 'conf_recap', ['titre' => $form['titre']]) . "\n\n";
    foreach ($form['champs'] as $nom => $c) {
        if (in_array($c['type'], INS_TYPES_DECORATIFS, true)) {
            continue;
        }
        if ($c['type'] === 'grille') {
            $v = ins_resume_grille($c, $valeurs);
        } elseif ($c['type'] === 'case') {
            $v = ($valeurs[$nom] ?? '') === ins_valeur_case(true, $form['langue']) ? t('val_oui') : t('val_non');
        } else {
            $v = ins_valeur_affichee($c, (string)($valeurs[$nom] ?? ''));
        }
        if ($v === '') {
            continue;
        }
        $corps .= ins_libelle_mail($c['libelle']) . (in_array($c['type'], ['commande', 'grille', 'texte_long'], true) ? "\n" : ' ') . $v . "\n";
    }
    if (isset($valeurs['__commande'])) {
        $corps .= t('cmd_total_estime') . ' : ' . ins_prix((float)$valeurs['__commande']['total']) . "\n";
    }
    $corps .= "\n-- \n" . t('conf_pied') . "\n";
    $entetes = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    $exp = (string)$cfg['expediteur'];
    if ($exp !== '' && filter_var($exp, FILTER_VALIDATE_EMAIL)) {
        $entetes .= "From: $exp\r\n";
    }
    // Réponse au message : vers l'organisateur (première adresse de « notifier »)
    $orga = trim(explode(',', $form['notifier'])[0] ?? '');
    if ($orga !== '' && filter_var($orga, FILTER_VALIDATE_EMAIL)) {
        $entetes .= "Reply-To: $orga\r\n";
    }
    $sujet = '=?UTF-8?B?' . base64_encode(t($modification ? 'conf_sujet_modif' : 'conf_sujet', ['titre' => $form['titre']])) . '?=';
    return @mail($dest, $sujet, $corps, $entetes);
}

/** Libellé suivi de « : » dans un courriel (sans doubler un « ? » ou « : » final). */
function ins_libelle_mail(string $libelle): string
{
    $l = rtrim(ins_texte_brut($libelle));
    return preg_match('/[?:!]$/u', $l) ? $l : $l . ' :';
}

/** Valeur enregistrée (langue de base) affichée dans la langue du formulaire : options traduites. */
function ins_valeur_affichee(array $c, string $v): string
{
    if ($v === '' || empty($c['options']) || !in_array($c['type'], ['choix', 'liste', 'cases', 'echelle'], true)) {
        return $v;
    }
    return implode(' | ', array_map(function ($x) use ($c) {
        $i = array_search($x, $c['options'], true);
        return $i === false ? $x : (string)($c['options_aff'][$i] ?? $x);
    }, array_map('trim', explode('|', $v))));
}

/** Limites d'envoi des confirmations (fichier donnees/.confirmations.json). Vrai si l'envoi est permis (et compté). */
function ins_quota_confirmation(string $cle, int $maxHeure): bool
{
    $f = ins_dossier_donnees() . '/.confirmations.json';
    $verrou = ins_verrouiller();
    try {
        $now = time();
        $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
        $envois = array_filter(is_array($j['envois'] ?? null) ? $j['envois'] : [], function ($t) use ($now) { return is_int($t) && $t > $now - 3600; });
        $derniers = array_filter(is_array($j['derniers'] ?? null) ? $j['derniers'] : [], function ($t) use ($now) { return is_int($t) && $t > $now - 600; });
        $h = substr(sha1($cle), 0, 16); // adresse jamais conservée en clair
        if (isset($derniers[$h]) || ($maxHeure > 0 && count($envois) >= $maxHeure)) {
            return false;
        }
        $envois[] = $now;
        $derniers[$h] = $now;
        @file_put_contents($f, json_encode(['envois' => array_values($envois), 'derniers' => $derniers]), LOCK_EX);
        return true;
    } finally {
        ins_deverrouiller($verrou);
    }
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
/* Images (logos)                                                     */
/* ------------------------------------------------------------------ */

function ins_dossier_images(): string
{
    $dir = INS_PRIVE . '/images';
    ins_preparer_dossier($dir);
    return $dir;
}

/** Nom d'image acceptable : lettres, chiffres, - et _, extension d'image connue (jamais de chemin). */
function ins_image_valide(string $nom): bool
{
    return (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,80}\.(png|jpe?g|gif|webp)$/i', $nom);
}

/** @return string[] images disponibles */
function ins_images(): array
{
    $res = [];
    foreach (glob(INS_PRIVE . '/images/*') ?: [] as $f) {
        if (is_file($f) && ins_image_valide(basename($f))) {
            $res[] = basename($f);
        }
    }
    sort($res, SORT_NATURAL | SORT_FLAG_CASE);
    return $res;
}

/**
 * Enregistre une image envoyée : type lu dans le contenu (pas dans le nom), PNG / JPEG / GIF / WebP seulement,
 * 1 Mo et 4000 × 4000 au plus ; le nom est nettoyé et l'extension fixée par le serveur.
 * @return string nom de l'image enregistrée
 */
function ins_enregistrer_image(string $tmp, string $nomOrigine): string
{
    if (!is_file($tmp) || filesize($tmp) > 1024 * 1024) {
        throw new RuntimeException(t('img_trop_grosse'));
    }
    $info = @getimagesize($tmp);
    if (!$info || !isset(INS_TYPES_IMAGE[$info[2]])) {
        throw new RuntimeException(t('img_type'));
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > 4000 || $info[1] > 4000) {
        throw new RuntimeException(t('img_dimensions'));
    }
    $ext = INS_TYPES_IMAGE[$info[2]];
    $base = pathinfo($nomOrigine, PATHINFO_FILENAME);
    $base = strtolower(function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) : $base);
    $base = trim((string)preg_replace('/[^a-z0-9]+/', '-', $base), '-') ?: 'image';
    $base = substr($base, 0, 60);
    $dir = ins_dossier_images();
    $nom = $base . '.' . $ext;
    for ($i = 2; is_file($dir . '/' . $nom); $i++) {
        $nom = $base . '-' . $i . '.' . $ext;
    }
    if (!@move_uploaded_file($tmp, $dir . '/' . $nom) && !@rename($tmp, $dir . '/' . $nom)) {
        throw new RuntimeException(t('err_ecriture', ['fichier' => $nom]));
    }
    return $nom;
}

/** Envoie une image de prive/images/ au navigateur (appelé par index.php?image=…), avec mise en cache. */
function ins_servir_image(string $nom): void
{
    $f = INS_PRIVE . '/images/' . $nom;
    if (!ins_image_valide($nom) || !is_file($f)) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
    $etag = '"' . substr(sha1($nom . filemtime($f) . filesize($f)), 0, 16) . '"';
    header('Content-Type: ' . INS_MIME_IMAGE[$ext]);
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Cache-Control: public, max-age=2592000');
    header('ETag: ' . $etag);
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

/** Taille de fichier lisible : 12 Ko, 1,2 Mo. */
function ins_taille_lisible(int $o): string
{
    if ($o < 1024 * 1024) {
        return t('taille_ko', ['n' => max(1, (int)round($o / 1024))]);
    }
    return t('taille_mo', ['n' => number_format($o / 1048576, 1, t('decimale'), '')]);
}

/** Adresse d'une image (relative à index.php / admin.php), avec un repère de version pour le cache. */
function ins_url_image(string $nom): string
{
    $f = INS_PRIVE . '/images/' . $nom;
    return 'index.php?' . http_build_query(['image' => $nom, 'v' => is_file($f) ? substr(sha1((string)filemtime($f)), 0, 8) : '0']);
}

/** Logo à afficher : celui du formulaire, sinon celui du site (config.php) ; « non » = aucun. Null si absent. */
function ins_logo(?array $form = null): ?array
{
    $cfg = ins_config();
    $nom = $form !== null ? $form['logo'] : '';
    $texte = $form !== null ? $form['logo_texte'] : '';
    if ($nom === 'non') {
        return null;
    }
    if ($nom === '') {
        $nom = (string)$cfg['logo'];
        $texte = $texte !== '' ? $texte : (string)$cfg['logo_texte'];
    }
    if ($nom === '' || !ins_image_valide($nom) || !is_file(INS_PRIVE . '/images/' . $nom)) {
        return null;
    }
    return ['nom' => $nom, 'texte' => $texte];
}

/** En-tête de page : logo à gauche du titre. */
function ins_tete(string $titre, ?array $form = null): string
{
    $logo = ins_logo($form);
    if ($logo === null) {
        return '<h1>' . e($titre) . '</h1>';
    }
    return '<header class="tete"><img src="' . e(ins_url_image($logo['nom'])) . '" alt="' . e($logo['texte']) . '"><h1>' . e($titre) . '</h1></header>';
}

/* ------------------------------------------------------------------ */
/* Commandes : catalogues, montants, détail des commandes             */
/* ------------------------------------------------------------------ */

function ins_dossier_catalogues(): string
{
    $dir = INS_PRIVE . '/catalogues';
    ins_preparer_dossier($dir);
    return $dir;
}

function ins_fichier_catalogue(string $nom): string
{
    return INS_PRIVE . '/catalogues/' . $nom . '.csv';
}

/** @return string[] noms des catalogues disponibles */
function ins_catalogues(): array
{
    $res = [];
    foreach (glob(INS_PRIVE . '/catalogues/*.csv') ?: [] as $f) {
        $n = basename($f, '.csv');
        if (ins_id_valide($n)) {
            $res[] = $n;
        }
    }
    sort($res);
    return $res;
}

/** Nombre saisi (« 1,5 », « 1.50 € », « 2 ») → float, ou null si illisible. */
function ins_lire_nombre($v): ?float
{
    $v = str_replace(["\u{00A0}", ' ', '€', '$', '£'], '', trim((string)$v));
    $v = str_replace(',', '.', $v);
    return preg_match('/^-?\d+(\.\d+)?$/', $v) ? (float)$v : null;
}

/** Montant affiché : « 1 234,50 € » (fr), « €1,234.50 » (en). */
function ins_prix(float $m): string
{
    $n = number_format(round($m, 2), 2, t('nombre_decimal'), t('nombre_milliers'));
    return t('format_prix', ['n' => $n, 'devise' => (string)ins_config()['devise']]);
}

/** Quantité affichée sans zéros inutiles : 2, 0,5, 1,25. */
function ins_quantite(float $q): string
{
    $s = rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.');
    return str_replace('.', t('nombre_decimal'), $s);
}

/** Quantité pour une zone de saisie numérique (toujours avec un point). */
function ins_nombre_saisie(float $q): string
{
    return rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.');
}

/** Quantité pour le fichier CSV (jusqu'à 3 décimales), même convention que ins_nombre_csv(). */
function ins_quantite_csv(float $q): string
{
    $s = ins_nombre_saisie($q);
    return (string)ins_config()['separateur'] === ';' ? str_replace('.', ',', $s) : $s;
}

/** Nombre pour le fichier CSV : virgule décimale avec le séparateur « ; » (tableurs français), point sinon. */
function ins_nombre_csv(float $m): string
{
    $s = number_format(round($m, 2), 2, '.', '');
    return (string)ins_config()['separateur'] === ';' ? str_replace('.', ',', $s) : $s;
}

/**
 * Lit un catalogue. Chaque produit : id, categorie, produit, unite, prix (float), pas (float, 0 = libre), disponible (bool).
 * @return array<string,array> indexé par id
 */
function ins_lire_catalogue(string $nom): array
{
    $res = [];
    $f = ins_fichier_catalogue($nom);
    if (ins_id_valide($nom) && is_file($f)) {
        $lignes = ins_lire_csv($f);
        foreach (array_slice($lignes, 1) as $l) {
            $p = [];
            foreach (INS_COLONNES_CATALOGUE as $k => $col) {
                $p[$col] = trim((string)($l[$k] ?? ''));
            }
            if ($p['id'] === '' || $p['produit'] === '') {
                continue;
            }
            $p['produit'] = ins_csv_brut($p['produit']);
            $p['categorie'] = ins_csv_brut($p['categorie']);
            $p['unite'] = ins_csv_brut($p['unite']);
            $p['prix'] = max(0.0, (float)(ins_lire_nombre($p['prix']) ?? 0));
            $p['pas'] = max(0.0, (float)(ins_lire_nombre($p['pas']) ?? 0));
            $p['disponible'] = ins_oui($p['disponible']);
            $res[$p['id']] = $p;
        }
    }
    return $res;
}

/** Enregistre un catalogue (sous verrou). Les produits sans id en reçoivent un. */
function ins_ecrire_catalogue(string $nom, array $produits): void
{
    if (!ins_id_valide($nom)) {
        throw new RuntimeException(t('adm_up_nom', ['nom' => $nom]));
    }
    ins_dossier_catalogues();
    $verrou = ins_verrouiller();
    try {
        $pris = [];
        $lignes = [INS_COLONNES_CATALOGUE];
        foreach ($produits as $p) {
            $id = (string)($p['id'] ?? '');
            if (!preg_match('/^[a-z0-9_-]{1,80}$/', $id) || isset($pris[$id])) {
                $base = substr(trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $p['produit'] . '-' . $p['unite']) ?: 'p')), '-'), 0, 60) ?: 'p';
                $id = $base;
                $i = 2;
                while (isset($pris[$id])) {
                    $id = $base . '-' . $i++;
                }
            }
            $pris[$id] = true;
            $lignes[] = [$id, ins_csv_sur((string)$p['categorie']), ins_csv_sur((string)$p['produit']), ins_csv_sur((string)$p['unite']),
                ins_nombre_csv((float)$p['prix']), $p['pas'] > 0 ? ins_quantite_csv((float)$p['pas']) : '', $p['disponible'] ? 'oui' : 'non'];
        }
        ins_ecrire_csv(ins_fichier_catalogue($nom), $lignes);
    } finally {
        ins_deverrouiller($verrou);
    }
}

/**
 * Lit un CSV de catalogue venu d'ailleurs (tableur) : séparateur « ; », « , » ou tabulation,
 * en-têtes reconnus en français ou en anglais (produit, unité, prix, disponible, catégorie, pas).
 * @return array liste de produits (sans id)
 */
function ins_importer_catalogue(string $contenu): array
{
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', str_replace("\r\n", "\n", $contenu)) ?? '';
    if (!mb_check_encoding($contenu, 'UTF-8')) {
        $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
    }
    $premiere = strtok($contenu, "\n") ?: '';
    $sep = substr_count($premiere, ';') >= max(1, substr_count($premiere, ',')) ? ';' : (substr_count($premiere, "\t") > substr_count($premiere, ',') ? "\t" : ',');
    $h = fopen('php://temp', 'w+');
    fwrite($h, $contenu);
    rewind($h);
    $entete = null;
    $cols = [];
    $res = [];
    $noms = [
        'produit' => ['produit', 'product', 'article', 'nom', 'name', 'designation'],
        'categorie' => ['categorie', 'category', 'rayon', 'famille'],
        'unite' => ['unite', 'unit', 'conditionnement'],
        'prix' => ['prix', 'prix unitaire', 'price', 'unit price', 'tarif'],
        'pas' => ['pas', 'step', 'increment'],
        'disponible' => ['disponible', 'available', 'dispo', 'stock'],
        'variete' => ['variete', 'variety', 'sorte'],
    ];
    while (($l = fgetcsv($h, 0, $sep, '"', '')) !== false) {
        if ($entete === null) {
            foreach ($l as $i => $t) {
                $t = strtolower(trim((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$t)));
                foreach ($noms as $cle => $alias) {
                    if (in_array($t, $alias, true) && !isset($cols[$cle])) {
                        $cols[$cle] = $i;
                    }
                }
            }
            if (!isset($cols['produit'])) {
                throw new RuntimeException(t('adm_cat_import_entete'));
            }
            $entete = $l;
            continue;
        }
        $val = function (string $cle) use ($l, $cols) {
            return isset($cols[$cle]) ? trim((string)($l[$cols[$cle]] ?? '')) : '';
        };
        $produit = $val('produit');
        if ($produit === '' || !preg_match('/\p{L}/u', $produit)) {
            continue; // lignes vides ou sans nom de produit
        }
        if ($val('variete') !== '') {
            $produit .= ' ' . $val('variete');
        }
        $dispo = $val('disponible');
        $res[] = ['id' => '', 'categorie' => $val('categorie'), 'produit' => mb_substr($produit, 0, 120), 'unite' => mb_substr($val('unite'), 0, 40),
            'prix' => max(0.0, (float)(ins_lire_nombre($val('prix')) ?? 0)), 'pas' => max(0.0, (float)(ins_lire_nombre($val('pas')) ?? 0)),
            'disponible' => $dispo === '' ? true : (ins_oui($dispo) || (ins_lire_nombre($dispo) ?? 0) > 0)];
    }
    fclose($h);
    return $res;
}

/**
 * Vérifie les quantités envoyées pour un champ commande. Les prix viennent TOUJOURS du catalogue.
 * @return array{0: array, 1: string} lignes commandées, message d'erreur
 */
function ins_valider_commande(array $c, array $quantites): array
{
    $catalogue = ins_lire_catalogue($c['catalogue']);
    $lignes = [];
    foreach ($catalogue as $id => $p) {
        $brut = $quantites[$id] ?? '';
        if (!is_string($brut) || trim($brut) === '') {
            continue;
        }
        $q = ins_lire_nombre($brut);
        if ($q === null || $q < 0 || $q > 10000) {
            return [[], t('cmd_quantite_invalide', ['produit' => $p['produit']])];
        }
        if ($q == 0) {
            continue;
        }
        if (!$p['disponible']) {
            return [[], t('cmd_indisponible', ['produit' => $p['produit']])];
        }
        if ($p['pas'] > 0 && abs($q / $p['pas'] - round($q / $p['pas'])) > 1e-6) {
            return [[], t('cmd_pas', ['produit' => $p['produit'], 'pas' => ins_quantite($p['pas']), 'unite' => $p['unite']])];
        }
        $lignes[] = ['id' => $id, 'produit' => $p['produit'], 'unite' => $p['unite'], 'prix' => $p['prix'], 'qte' => round($q, 3)];
    }
    return [$lignes, ''];
}

function ins_total_lignes(array $lignes): float
{
    $t = 0.0;
    foreach ($lignes as $l) {
        $t += round((float)$l['prix'] * (float)$l['qte'], 2);
    }
    return round($t, 2);
}

/** Une ligne lisible par produit : « Carotte (botte) : 2 × 1,50 € = 3,00 € ». */
function ins_texte_lignes(array $lignes): string
{
    $res = [];
    foreach ($lignes as $l) {
        $res[] = t('cmd_ligne', ['produit' => $l['produit'], 'unite' => $l['unite'], 'qte' => ins_quantite((float)$l['qte']),
            'prix' => ins_prix((float)$l['prix']), 'montant' => ins_prix(round((float)$l['prix'] * (float)$l['qte'], 2))]);
    }
    return implode("\n", $res);
}

/* Détail structuré des commandes : prive/donnees/commandes/<fichier>.json, indexé par numéro de réponse */

function ins_fichier_commandes(array $form): string
{
    $dir = ins_dossier_donnees() . '/commandes';
    ins_preparer_dossier($dir);
    return $dir . '/' . $form['fichier'] . '.json';
}

function ins_lire_commandes(array $form): array
{
    $f = ins_fichier_commandes($form);
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    return is_array($d) ? $d : [];
}

/** À appeler sous verrou. */
function ins_ecrire_commandes(array $form, array $commandes): void
{
    $f = ins_fichier_commandes($form);
    $tmp = $f . '.tmp-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, json_encode($commandes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION)) === false
        || !@rename($tmp, $f)) {
        @unlink($tmp);
        throw new RuntimeException(t('err_ecriture', ['fichier' => $f]));
    }
}

/** Enregistre (sous le verrou de ins_enregistrer) la commande d'une réponse ; une modification par le client est signalée. */
function ins_enregistrer_detail_commande(array $form, string $numero, array $cmd, bool $modification): void
{
    $toutes = ins_lire_commandes($form);
    $avant = $toutes[$numero] ?? null;
    $toutes[$numero] = [
        'date' => date('Y-m-d H:i:s'),
        'champ' => $cmd['champ'],
        'lignes' => $cmd['lignes'],
        'total' => $cmd['total'],
        'statut' => $avant['statut'] ?? 'nouvelle',
        'final' => $avant['final'] ?? null,
        'note' => $avant['note'] ?? '',
        'modifiee' => $modification && !empty($avant['final']),
    ];
    ins_ecrire_commandes($form, $toutes);
}

/** Champ « commande » du formulaire, ou null. */
function ins_champ_commande(array $form): ?array
{
    foreach ($form['champs'] as $c) {
        if ($c['type'] === 'commande') {
            return $c;
        }
    }
    return null;
}

/** Tableau de commande : produits disponibles par catégorie, quantité, sous-total et total estimé en direct. */
function ins_rendu_commande(array $c, array $quantites, string $err, string $si): string
{
    $nom = $c['nom'];
    $produits = array_filter(ins_lire_catalogue($c['catalogue']), function ($p) { return $p['disponible']; });
    $req = $c['obligatoire'] ? ' <span class="req" title="' . e(t('obligatoire')) . '">*</span>' : '';
    $fmt = ['decimal' => t('nombre_decimal'), 'milliers' => t('nombre_milliers'), 'format' => t('format_prix'), 'devise' => (string)ins_config()['devise']];
    $h = '<div class="champ commande' . ($err !== '' ? ' erreur' : '') . '" data-nom="' . e($nom) . '" data-type="commande"' . $si . '>'
        . '<fieldset style="border:0;margin:0;padding:0"><legend class="lib">' . e($c['libelle']) . $req . '</legend>';
    if ($c['aide'] !== '') {
        $h .= '<div class="aide">' . ins_texte($c['aide']) . '</div>';
    }
    if (!$produits) {
        $h .= '<p class="info">' . e(t('cmd_aucun_produit')) . '</p>';
    } else {
        $h .= '<div class="defile-x"><table class="cmd" data-format="' . e(json_encode($fmt, JSON_UNESCAPED_UNICODE)) . '"><thead><tr><th>'
            . e(t('cmd_produit')) . '</th><th class="nb">' . e(t('cmd_prix')) . '</th><th class="nb">' . e(t('cmd_quantite')) . '</th><th class="nb">'
            . e(t('cmd_montant')) . '</th></tr></thead><tbody>';
        $categorie = null;
        foreach ($produits as $id => $p) {
            if ($p['categorie'] !== $categorie && $p['categorie'] !== '') {
                $h .= '<tr class="cmd-cat"><th colspan="4">' . e($p['categorie']) . '</th></tr>';
            }
            $categorie = $p['categorie'];
            $idc = 'q_' . $nom . '_' . $id;
            $val = isset($quantites[$id]) && is_string($quantites[$id]) ? $quantites[$id] : '';
            $h .= '<tr><td><label for="' . e($idc) . '">' . e($p['produit']) . '</label></td>'
                . '<td class="nb">' . e(ins_prix($p['prix'])) . ' / ' . e($p['unite']) . '</td>'
                . '<td class="nb"><input type="number" class="cmd-q" id="' . e($idc) . '" name="' . e($nom) . '[' . e($id) . ']" min="0" max="10000" step="'
                . ($p['pas'] > 0 ? e(ins_nombre_saisie($p['pas'])) : 'any') . '" inputmode="decimal" value="' . e($val) . '" data-prix="'
                . e(ins_nombre_saisie($p['prix'])) . '" aria-label="' . e(t('cmd_quantite') . ' — ' . $p['produit'] . ' (' . $p['unite'] . ')') . '"></td>'
                . '<td class="nb cmd-st"></td></tr>';
        }
        $h .= '</tbody><tfoot><tr><th colspan="3" class="nb">' . e(t('cmd_total_estime')) . '</th><th class="nb cmd-total">' . e(ins_prix(0)) . '</th></tr></tfoot></table></div>'
            . '<p class="petit">' . e(t('cmd_note_estimation')) . '</p>';
    }
    if ($err !== '') {
        $h .= '<div class="msg">' . e($err) . '</div>';
    }
    $h .= '</fieldset></div>';
    static $script = false;
    if (!$script) {
        $script = true;
        $h .= ins_script_commande();
    }
    return $h;
}

/** Calcul en direct des sous-totaux et du total estimé (le serveur refait le calcul avec les prix du catalogue). */
function ins_script_commande(): string
{
    return <<<'JS'
<script>
(function () {
  function prix(m, f) {
    var p = Math.round(m * 100) / 100, ent = Math.floor(Math.abs(p)).toString(), dec = Math.round((Math.abs(p) % 1) * 100).toString();
    while (dec.length < 2) dec = '0' + dec;
    ent = ent.replace(/\B(?=(\d{3})+(?!\d))/g, f.milliers);
    return f.format.split('{n}').join((p < 0 ? '-' : '') + ent + f.decimal + dec).split('{devise}').join(f.devise);
  }
  function calcul(table) {
    var f = JSON.parse(table.getAttribute('data-format')), total = 0;
    table.querySelectorAll('.cmd-q').forEach(function (el) {
      var q = parseFloat(String(el.value).replace(',', '.')), st = el.closest('tr').querySelector('.cmd-st');
      if (q > 0) {
        var m = Math.round(q * parseFloat(el.getAttribute('data-prix')) * 100) / 100;
        total += m;
        st.textContent = prix(m, f);
        el.closest('tr').classList.add('cmd-choisi');
      } else {
        st.textContent = '';
        el.closest('tr').classList.remove('cmd-choisi');
      }
    });
    table.querySelector('.cmd-total').textContent = prix(total, f);
  }
  document.querySelectorAll('table.cmd').forEach(function (t) {
    t.addEventListener('input', function () { calcul(t); });
    calcul(t);
  });
})();
</script>
JS;
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
        $si = $c['si'] ? ' data-si="' . e(json_encode($c['si'], JSON_UNESCAPED_UNICODE)) . '"' . ($c['si_ou'] ? ' data-si-ou' : '') : '';

        if ($c['type'] === 'titre') {
            $h .= '<h2 data-bloc="' . e($nom) . '"' . $si . '>' . e($c['libelle']) . '</h2>';
            continue;
        }
        if ($c['type'] === 'info') {
            $h .= '<div class="info" data-bloc="' . e($nom) . '"' . $si . '>' . ins_texte($c['libelle']) . '</div>';
            continue;
        }

        if ($c['type'] === 'commande') {
            $q = $premier ? ($reponseExistante['__commande'] ?? []) : (is_array($brut[$nom] ?? null) ? $brut[$nom] : []);
            $h .= ins_rendu_commande($c, $q, $erreurs[$nom] ?? '', $si);
            continue;
        }
        if ($c['type'] === 'grille') {
            $h .= ins_rendu_grille($c, $premier ? ($reponseExistante ?? []) : null, is_array($brut[$nom] ?? null) ? $brut[$nom] : [], $erreurs[$nom] ?? '', $si);
            continue;
        }
        if ($premier && $reponseExistante !== null && array_key_exists($nom, $reponseExistante)) {
            $b = $reponseExistante[$nom]; // réponse précédente, ou valeur connue grâce à l'accès
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
        $bornes = ins_aide_bornes($c);
        if ($c['aide'] !== '' || $bornes !== '') {
            $h .= '<div class="aide" id="' . $idh . '_a">' . ins_texte($c['aide']) . ($c['aide'] !== '' && $bornes !== '' ? ' — ' : '') . e($bornes) . '</div>';
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
                $h .= '<label class="opt lib"><input type="checkbox" id="' . $idh . '" name="' . e($nom) . '" value="1"' . $ch . $aria . '> <span>' . ins_texte($c['libelle']) . $req . '</span></label>';
                break;
            default:
                $types = ['email' => 'email', 'telephone' => 'tel', 'nombre' => 'text', 'date' => 'date'];
                $tp = $types[$c['type']] ?? 'text';
                $extra = $c['type'] === 'nombre' ? ' inputmode="' . ($c['entier'] ? 'numeric' : 'decimal') . '"' : '';
                if ($bornes !== '') { // bornes lues par les lecteurs d'écran avec le champ
                    $aria = $err !== '' ? str_replace($idh . '_e"', $idh . '_e ' . $idh . '_a"', $aria) : ' aria-describedby="' . $idh . '_a"';
                }
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

/**
 * Champ « grille » : un tableau, une ligne par question (Vendredi, Samedi…), une colonne par réponse (Oui, Non) ;
 * boutons radio (une réponse par ligne) ou cases à cocher (multiple = oui). Sur téléphone, les lignes s'empilent.
 * $existante : réponse enregistrée (colonnes <nom>_<repère>) au premier affichage ; $saisie : saisie renvoyée.
 */
function ins_rendu_grille(array $c, ?array $existante, array $saisie, string $err, string $si): string
{
    $nom = $c['nom'];
    $idh = 'c_' . $nom;
    $req = $c['obligatoire'] ? ' <span class="req" title="' . e(t('obligatoire')) . '">*</span>' : '';
    $h = '<div class="champ' . ($err !== '' ? ' erreur' : '') . '" data-nom="' . e($nom) . '" data-type="grille"' . $si . '>'
        . '<fieldset style="border:0;margin:0;padding:0"' . ($err !== '' ? ' aria-describedby="' . $idh . '_e"' : '') . '>'
        . '<legend class="lib">' . e($c['libelle']) . $req . '</legend>';
    if ($c['aide'] !== '') {
        $h .= '<div class="aide">' . ins_texte($c['aide']) . '</div>';
    }
    $h .= '<table class="grille"><thead><tr><td></td>';
    foreach ($c['options_aff'] as $o) {
        $h .= '<th scope="col">' . e($o) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($c['lignes_id'] as $i => $idl) {
        if ($existante !== null) {
            $v = (string)($existante[$nom . '_' . $idl] ?? '');
            $sel = $v === '' ? [] : array_map('trim', explode('|', $v));
        } else {
            $b = $saisie[$idl] ?? null;
            $sel = is_array($b) ? array_values(array_filter($b, 'is_string')) : (is_string($b) ? [$b] : []);
        }
        $libLigne = (string)($c['lignes'][$i] ?? $idl);
        $manque = $err !== '' && !$sel;
        $h .= '<tr' . ($manque ? ' class="manque"' : '') . '><th scope="row" id="' . e($idh . '_' . $idl) . '">' . e($libLigne) . '</th>';
        foreach ($c['options'] as $k => $opt) {
            $aff = (string)$c['options_aff'][$k];
            $h .= '<td><label class="opt"><input type="' . ($c['multiple'] ? 'checkbox' : 'radio') . '" name="' . e($nom . '[' . $idl . ']' . ($c['multiple'] ? '[]' : '')) . '"'
                . ' value="' . e($opt) . '"' . (in_array($opt, $sel, true) ? ' checked' : '') . ' aria-label="' . e($libLigne . ' : ' . $aff) . '">'
                . '<span class="grille-m" aria-hidden="true">' . e($aff) . '</span></label></td>';
        }
        $h .= '</tr>';
    }
    $h .= '</tbody></table></fieldset>';
    if ($err !== '') {
        $h .= '<div class="msg" id="' . $idh . '_e">' . e($err) . '</div>';
    }
    return $h . '</div>';
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
  // Nombres (virgule ou point) et dates AAAA-MM-JJ ; saisie vide ou illisible : condition fausse
  function lire(genre, v) {
    v = String(v).trim();
    if (genre === 'date') return /^\d{4}-\d{2}-\d{2}$/.test(v) ? v : null;
    return /^-?\d+([.,]\d+)?$/.test(v) ? parseFloat(v.replace(',', '.')) : null;
  }
  function comparer(c, saisie) {
    var x = lire(c.num, saisie);
    if (x === null) return false;
    var r = c.valeurs.map(function (y) { y = lire(c.num, y); return y === null ? null : (x < y ? -1 : x > y ? 1 : 0); })
      .filter(function (d) { return d !== null; });
    if (!r.length) return false;
    switch (c.op) {
      case '=': return r.indexOf(0) >= 0;
      case '!=': return r.indexOf(0) < 0;
      case '<': return r[0] < 0;
      case '<=': return r[0] <= 0;
      case '>': return r[0] > 0;
      default: return r[0] >= 0;
    }
  }
  function maj() {
    blocs.forEach(function (bloc) {
      var conds = JSON.parse(bloc.getAttribute('data-si')), ou = bloc.hasAttribute('data-si-ou');
      var res = conds.map(function (c) {
        var v = valeurs(c.champ);
        if (v === null) return false; // champ visé masqué
        if (c.num) return comparer(c, v[0] || '');
        var trouve = c.valeurs.some(function (x) { return v.indexOf(x.toLowerCase()) >= 0; });
        return trouve === c.egal;
      });
      var vis = ou ? res.indexOf(true) >= 0 : res.indexOf(false) < 0; // OU : une suffit ; ET : toutes
      bloc.classList.toggle('cache', !vis);
      bloc.querySelectorAll('input,select,textarea').forEach(function (el) { el.disabled = !vis; });
    });
  }
  form.addEventListener('change', maj);
  form.addEventListener('input', maj); // nombres et dates : pendant la frappe
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
    'nombre'     => ['aide', 'obligatoire', 'defaut', 'exemple', 'min', 'max', 'entier', 'unique'],
    'date'       => ['aide', 'obligatoire', 'defaut', 'min', 'max'],
    'choix'      => ['aide', 'obligatoire', 'defaut', 'options'],
    'liste'      => ['aide', 'obligatoire', 'defaut', 'options'],
    'cases'      => ['aide', 'obligatoire', 'defaut', 'options'],
    'echelle'    => ['aide', 'obligatoire', 'defaut', 'options', 'gauche', 'droite'],
    'grille'     => ['aide', 'obligatoire', 'lignes', 'options', 'multiple'],
    'case'       => ['aide', 'obligatoire', 'defaut'],
    'commande'   => ['aide', 'obligatoire', 'catalogue'],
    'titre'      => [],
    'info'       => [],
];

/** Modèle éditable (tableaux simples, prêt pour JSON) d'un formulaire analysé. */
function ins_modele(array $form): array
{
    $g = [];
    foreach (['titre', 'intro', 'merci', 'bouton', 'message', 'logo', 'logo_texte', 'confirmation', 'confirmation_texte', 'fichier', 'notifier', 'langue', 'acces'] as $k) {
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
            'min' => (string)($c['min'] ?? ''), 'max' => (string)($c['max'] ?? ''), 'unique' => (bool)$c['unique'], 'entier' => (bool)$c['entier'],
            'exemple' => $c['exemple'], 'gauche' => $c['gauche'], 'droite' => $c['droite'], 'catalogue' => $c['catalogue'],
            'lignes' => array_values($c['lignes']), 'multiple' => (bool)$c['multiple'],
            'si' => array_map(function ($x) { return ['champ' => $x['champ'], 'op' => $x['op'], 'egal' => $x['egal'], 'valeurs' => array_values($x['valeurs'])]; }, $c['si']),
            'si_ou' => (bool)$c['si_ou'],
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
    foreach (['titre', 'intro', 'merci', 'bouton', 'message'] as $k) {
        $tr = [];
        foreach ($autres as $lg) {
            $tr[$lg] = $trad($g['trad'] ?? [], $lg, $k);
        }
        $ecrire($l, $k, ins_modele_texte($g[$k] ?? ''), $tr);
    }
    $logo = ins_modele_texte($g['logo'] ?? '');
    if ($logo !== '' && $logo !== 'non' && !ins_image_valide($logo)) {
        throw new RuntimeException(t('err_logo', ['f' => "$id.ini", 'logo' => $logo]));
    }
    $ecrire($l, 'logo', $logo);
    $tr = [];
    foreach ($autres as $lg) {
        $tr[$lg] = $trad($g['trad'] ?? [], $lg, 'logo_texte');
    }
    $ecrire($l, 'logo_texte', ins_modele_texte($g['logo_texte'] ?? ''), $tr);
    $conf = ins_modele_texte($g['confirmation'] ?? '');
    $ecrire($l, 'confirmation', $conf);
    if ($conf !== '') {
        $tr = [];
        foreach ($autres as $lg) {
            $tr[$lg] = $trad($g['trad'] ?? [], $lg, 'confirmation_texte');
        }
        $ecrire($l, 'confirmation_texte', ins_modele_texte($g['confirmation_texte'] ?? ''), $tr);
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
        if (in_array('catalogue', $props, true)) {
            $cat = ins_modele_texte($c['catalogue'] ?? '');
            if ($cat !== '' && !ins_id_valide($cat)) {
                throw new RuntimeException(t('err_catalogue_cle', ['f' => "$id.ini", 'nom' => $nom]));
            }
            $ecrire($l, 'catalogue', $cat);
        }
        if (in_array('lignes', $props, true)) {
            $ecrire($l, 'lignes', ins_modele_liste($c['lignes'] ?? []), $tr('lignes'));
        }
        if (in_array('options', $props, true) && $type === 'grille') {
            $ecrire($l, 'colonnes', ins_modele_liste($c['options'] ?? []), $tr('options'));
        } elseif (in_array('options', $props, true)) {
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
        if (in_array('entier', $props, true) && !empty($c['entier'])) {
            $l[] = 'entier = oui';
        }
        if (in_array('multiple', $props, true) && !empty($c['multiple'])) {
            $l[] = 'multiple = oui';
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
            $op = ins_modele_texte($cond['op'] ?? '');
            if (in_array($op, ['<', '<=', '>', '>='], true)) {
                $conds[] = $cible . ' ' . $op . ' ' . $vals[0];
                continue;
            }
            $conds[] = $cible . (empty($cond['egal']) ? ' != ' : ' = ') . implode(' | ', $vals);
        }
        $ou = !empty($c['si_ou']) && count($conds) > 1;
        if ($ou && !ins_config()['conditions_ou']) {
            throw new RuntimeException(t('err_condition_ou', ['f' => "$id.ini", 'nom' => $nom]));
        }
        $ecrire($l, 'si', implode($ou ? ' || ' : ' & ', $conds));
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
    $h .= ins_tete($f['titre'], $f);
    if ($noteAcces !== '') {
        $h .= '<p class="petit" lang="' . e($avant) . '">' . e($noteAcces) . '</p>';
    }
    if ($f['intro'] !== '') {
        $h .= '<div class="info">' . ins_texte($f['intro']) . '</div>';
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
.vignettes{display:flex;flex-wrap:wrap;gap:1rem;margin:.5rem 0}.vignettes figure{margin:0;width:13rem}
.vignettes img{max-width:100%;max-height:6rem;background:#fff;border:1px solid #ddd;padding:.3rem}
.tete{display:flex;align-items:center;gap:1rem;margin:.5rem 0 1rem}
.tete img{max-height:80px;max-width:35%;height:auto;width:auto;flex:none}
.tete h1{margin:0}
.lien{display:flex;gap:.4rem;align-items:center;margin:.4rem 0}
table.cmd{font-size:.95rem}
table.cmd td,table.cmd th{border-left:0;border-right:0}
table.cmd .nb{text-align:right;white-space:nowrap}
table.cmd input.cmd-q{width:5.5rem;text-align:right;padding:.3rem}
table.cmd .cmd-cat th{background:#f1f3f5;font-size:.85rem;color:#57606a;position:static}
table.cmd tr.cmd-choisi td{background:#eef4fa}
table.cmd tfoot th{background:#fff;font-size:1.05rem;position:static}
.defile-x{overflow-x:auto}
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
table.grille{width:auto;max-width:100%;background:transparent;font-size:inherit;margin:.3rem 0}
table.grille th,table.grille td{position:static;background:transparent;border:0;border-bottom:1px solid #e3e8ee;padding:.35rem .7rem;text-align:center;font-weight:normal}
table.grille thead th{font-weight:600;font-size:.9rem}
table.grille tbody th{text-align:left;padding-left:0}
table.grille label.opt{display:inline-flex;margin:0;padding:.2rem;justify-content:center}
table.grille .grille-m{display:none}
table.grille tr.manque th{color:#b42318;font-weight:600}
@media (max-width:30rem){table.grille thead{display:none}table.grille,table.grille tbody,table.grille tr{display:block}table.grille tr{padding:.35rem 0;border-bottom:1px solid #e3e8ee}table.grille th,table.grille td{display:inline-block;border:0;padding:.15rem .9rem .15rem 0}table.grille tbody th{display:block;font-weight:600}table.grille .grille-m{display:inline;margin-left:.3rem}table.grille label.opt{justify-content:flex-start}}
@media (max-width:30rem){.tete{gap:.6rem}.tete img{max-height:56px}table.cmd input.cmd-q{width:3.6rem}table.cmd td,table.cmd th{padding:.3rem .25rem}table.cmd{font-size:.9rem}.echelle .notes{gap:0}.echelle .opt{min-width:1.6rem}main{padding-left:.6rem;padding-right:.6rem}.champ{padding:.7rem .7rem}}
CSS;
}
