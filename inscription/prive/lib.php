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
    'langue' => ['langue', 'language'], 'cache' => ['cache', 'hidden'],
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

/* ------------------------------------------------------------------ */
/* Descripteurs de formulaires                                        */
/* ------------------------------------------------------------------ */

/**
 * Formulaires diffusés (affichés dans la liste publique).
 * @return array<string,string> identifiant => titre
 */
function ins_liste_formulaires(): array
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
                $liste[$id] = $form['titre'];
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
        'places'          => max(0, (int)$lit('places', 0)),
        'notifier'        => trim((string)$lit('notifier')),
        // diffusion = oui par défaut ; l'ancienne clé « cache = oui » vaut diffusion = non
        'diffusion'       => $diffusion !== null ? ins_oui($diffusion) : !ins_oui($lit('cache')),
        'afficher_numero' => ins_oui($lit('afficher_numero', 'oui')),
        'langue'          => trim((string)$lit('langue')),
        'champs'          => [],
    ];
    if (!ins_id_valide($form['fichier'])) {
        throw new RuntimeException(t('err_fichier', ['f' => $f]));
    }

    $vus = [];
    foreach ($ini as $nom => $p) {
        $nom = (string)$nom;
        if (!is_array($p)) {
            throw new RuntimeException(t('err_hors_section', ['f' => $f, 'nom' => $nom]));
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/i', $nom) || in_array(strtolower($nom), INS_COLONNES_FIXES, true)) {
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
        ];
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

/** Colonnes du fichier de données, dans l'ordre. */
function ins_colonnes(array $form): array
{
    $cols = INS_COLONNES_FIXES;
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
    return is_string($cle) && $cle !== '' && hash_equals(ins_cle_partage($form['id']), $cle);
}

/** Adresse relative du formulaire (avec la clé de partage s'il n'est pas diffusé). */
function ins_url_formulaire(array $form): string
{
    $q = ['f' => $form['id']];
    if (!$form['diffusion']) {
        $q['k'] = ins_cle_partage($form['id']);
    }
    return '?' . http_build_query($q);
}

/* ------------------------------------------------------------------ */
/* État du formulaire                                                 */
/* ------------------------------------------------------------------ */

/** @return string '' si ouvert, sinon le message à afficher */
function ins_etat(array $form): string
{
    $t = time();
    if ($form['ouverture'] !== null && $t < $form['ouverture']) {
        return t('etat_pas_ouvert', ['date' => ins_date_lisible($form['ouverture'])]);
    }
    if ($form['fermeture'] !== null && $t > $form['fermeture']) {
        return t('etat_clos');
    }
    if ($form['places'] > 0 && ins_nombre_inscrits($form) >= $form['places']) {
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
function ins_valeur_case(bool $cochee): string
{
    return $cochee ? t('val_oui') : t('val_non');
}

function ins_visible(array $c, array $valeurs, array $visibles, array $champs): bool
{
    foreach ($c['si'] as $cond) {
        $cible = $cond['champ'];
        if (empty($visibles[$cible])) {
            return false; // dépend d'un champ lui-même masqué
        }
        $v = $valeurs[$cible] ?? '';
        if ($champs[$cible]['type'] === 'case') {
            $choisis = [$v === ins_valeur_case(true) ? '1' : '0'];
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
        $visible = ins_visible($c, $valeurs, $visibles, $form['champs']);
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
                $v = ins_valeur_case($cochee);
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

/**
 * Ajoute une réponse au fichier CSV (sous verrou).
 * @return array{0: ?string, 1: array<string,string>, 2: string} numéro, erreurs, message global
 */
function ins_enregistrer(array $form, array $valeurs): array
{
    $dir = ins_dossier_donnees();
    $fichier = ins_fichier_donnees($form);
    $cols = ins_colonnes($form);
    $sep = (string)ins_config()['separateur'];

    $verrou = @fopen($dir . '/.verrou', 'c');
    if (!$verrou || !flock($verrou, LOCK_EX)) {
        throw new RuntimeException(t('err_verrou', ['dossier' => $dir]));
    }
    try {
        $lignes = is_file($fichier) ? ins_lire_csv($fichier) : [];

        // Si les champs du formulaire ont changé, l'ancien fichier est mis de côté.
        if ($lignes && $lignes[0] !== $cols) {
            rename($fichier, $dir . '/' . $form['fichier'] . '-ancien-' . date('Ymd-His') . '.csv');
            $lignes = [];
        }
        $existantes = array_slice($lignes, 1);

        if ($form['places'] > 0 && count($existantes) >= $form['places']) {
            return [null, [], t('etat_complet')];
        }

        // Champs « unique = oui » : refus des doublons (casse ignorée)
        $erreurs = [];
        foreach ($form['champs'] as $nom => $c) {
            if (!$c['unique'] || ($valeurs[$nom] ?? '') === '') {
                continue;
            }
            $i = array_search($nom, $cols, true);
            $cherche = ins_minuscules(ins_csv_sur($valeurs[$nom]));
            foreach ($existantes as $l) {
                if (ins_minuscules((string)($l[$i] ?? '')) === $cherche) {
                    $erreurs[$nom] = t('v_deja_utilise');
                    break;
                }
            }
        }
        if ($erreurs) {
            return [null, $erreurs, ''];
        }

        $pris = [];
        foreach ($existantes as $l) {
            $pris[$l[0] ?? ''] = true;
        }
        do {
            $numero = ins_nouveau_numero();
        } while (isset($pris[$numero]));

        $ligne = [$numero, date('Y-m-d H:i:s')];
        foreach (array_slice($cols, count(INS_COLONNES_FIXES)) as $nom) {
            $ligne[] = ins_csv_sur((string)($valeurs[$nom] ?? ''));
        }

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
        return [$numero, [], ''];
    } finally {
        flock($verrou, LOCK_UN);
        fclose($verrou);
    }
}

/** Envoi facultatif d'un courriel au gestionnaire (« notifier = » dans le descripteur). */
function ins_notifier(array $form, string $numero, array $valeurs): void
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
    $corps = t('mail_corps', ['numero' => $numero, 'titre' => $form['titre']]) . "\n\n";
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
.langues{text-align:right;font-size:.9rem}
@media (max-width:30rem){.echelle .notes{gap:0}.echelle .opt{min-width:1.6rem}main{padding-left:.6rem;padding-right:.6rem}.champ{padding:.7rem .7rem}}
CSS;
}
