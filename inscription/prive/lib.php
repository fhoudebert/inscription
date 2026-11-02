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
    'choix', 'liste', 'cases', 'case', 'titre', 'info',
];
// Types qui ne produisent pas de colonne dans le fichier de données
const INS_TYPES_DECORATIFS = ['titre', 'info'];
// Écritures alternatives acceptées dans les descripteurs
const INS_ALIAS_TYPES = [
    'text' => 'texte', 'textarea' => 'texte_long', 'zone' => 'texte_long', 'paragraphe' => 'texte_long',
    'mail' => 'email', 'courriel' => 'email', 'tel' => 'telephone', 'téléphone' => 'telephone',
    'number' => 'nombre', 'radio' => 'choix', 'select' => 'liste', 'checkbox' => 'cases',
    'consentement' => 'case', 'case_unique' => 'case', 'section' => 'titre', 'texte_info' => 'info',
];

/* ------------------------------------------------------------------ */
/* Configuration                                                      */
/* ------------------------------------------------------------------ */

function ins_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $defauts = [
            'dossier_formulaires' => INS_PRIVE . '/formulaires',
            'dossier_donnees'     => INS_PRIVE . '/donnees',
            'separateur'          => ';',
            'fuseau'              => 'Europe/Paris',
            'liste_publique'      => true,
            'longueur_max'        => 2000,
            'admin_mot_de_passe'  => '',
            'expediteur'          => '',
            'debug'               => false,
        ];
        $fichier = INS_PRIVE . '/config.php';
        $c = is_file($fichier) ? require $fichier : [];
        $cfg = array_merge($defauts, is_array($c) ? $c : []);
        @date_default_timezone_set((string)$cfg['fuseau']);
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
        throw new RuntimeException("Impossible d'écrire $f (droits d'écriture sur le dossier des données ?)");
    }
    return $secret = $s;
}

function ins_dossier_donnees(): string
{
    $dir = rtrim((string)ins_config()['dossier_donnees'], '/');
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException("Dossier des données introuvable et impossible à créer : $dir");
    }
    // Protections pour le cas où le dossier se trouve dans la partie publique du site
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', ins_htaccess_interdit());
    }
    if (!is_file($dir . '/index.html')) {
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function ins_htaccess_interdit(): string
{
    return "# Accès web interdit\n"
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
    return in_array($v, ['1', 'oui', 'o', 'yes', 'y', 'true', 'vrai', 'x'], true);
}

function ins_id_valide(string $id): bool
{
    return (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/i', $id);
}

function ins_minuscules(string $s): string
{
    return mb_strtolower(trim($s), 'UTF-8');
}

/** Empêche l'interprétation d'une valeur comme formule par Excel / LibreOffice. */
function ins_csv_sur(string $s): string
{
    return ($s !== '' && strpos("=+-@\t\r", $s[0]) !== false) ? "'" . $s : $s;
}

/* ------------------------------------------------------------------ */
/* Descripteurs de formulaires                                        */
/* ------------------------------------------------------------------ */

/** @return array<string,string> identifiant => titre */
function ins_liste_formulaires(): array
{
    $dir = rtrim((string)ins_config()['dossier_formulaires'], '/');
    $liste = [];
    foreach (glob($dir . '/*.ini') ?: [] as $f) {
        $id = basename($f, '.ini');
        if (!ins_id_valide($id)) {
            continue;
        }
        try {
            $form = ins_charger_formulaire($id);
            if (!$form['cache']) {
                $liste[$id] = $form['titre'];
            }
        } catch (Throwable $ex) {
            error_log('inscription : ' . $ex->getMessage());
        }
    }
    asort($liste, SORT_NATURAL | SORT_FLAG_CASE);
    return $liste;
}

function ins_charger_formulaire(string $id): array
{
    if (!ins_id_valide($id)) {
        throw new InvalidArgumentException("Identifiant de formulaire invalide : $id");
    }
    $chemin = rtrim((string)ins_config()['dossier_formulaires'], '/') . '/' . $id . '.ini';
    if (!is_file($chemin)) {
        throw new RuntimeException("Formulaire introuvable : $id");
    }
    error_clear_last();
    $ini = @parse_ini_file($chemin, true, INI_SCANNER_NORMAL);
    if ($ini === false) {
        $err = error_get_last();
        throw new RuntimeException("Erreur de syntaxe dans $id.ini : " . ($err['message'] ?? 'inconnue')
            . ' (pensez à mettre les textes entre guillemets doubles)');
    }

    $g = $ini['formulaire'] ?? [];
    unset($ini['formulaire']);

    $form = [
        'id'         => $id,
        'titre'      => trim((string)($g['titre'] ?? $id)),
        'intro'      => trim((string)($g['intro'] ?? '')),
        'merci'      => trim((string)($g['merci'] ?? 'Merci, votre inscription est enregistrée.')),
        'bouton'     => trim((string)($g['bouton'] ?? "Envoyer l'inscription")),
        'fichier'    => trim((string)($g['fichier'] ?? $id)),
        'ouverture'  => ins_date_ini($g['ouverture'] ?? '', $id, 'ouverture'),
        'fermeture'  => ins_date_ini($g['fermeture'] ?? '', $id, 'fermeture'),
        'places'     => max(0, (int)($g['places'] ?? 0)),
        'notifier'   => trim((string)($g['notifier'] ?? '')),
        'cache'      => ins_oui($g['cache'] ?? ''),
        'champs'     => [],
    ];
    if (!ins_id_valide($form['fichier'])) {
        throw new RuntimeException("$id.ini : « fichier » ne doit contenir que lettres, chiffres, - et _");
    }

    $vus = [];
    foreach ($ini as $nom => $p) {
        $nom = (string)$nom;
        if (!is_array($p)) {
            throw new RuntimeException("$id.ini : la ligne « $nom » doit être placée dans une section [ ]");
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/i', $nom) || in_array(strtolower($nom), INS_COLONNES_FIXES, true)) {
            throw new RuntimeException("$id.ini : nom de champ [$nom] invalide (lettres, chiffres, _ ; « numero » et « date » sont réservés)");
        }
        $type = ins_minuscules((string)($p['type'] ?? 'texte'));
        $type = INS_ALIAS_TYPES[$type] ?? $type;
        if (!in_array($type, INS_TYPES, true)) {
            throw new RuntimeException("$id.ini : type « $type » inconnu pour [$nom]. Types possibles : " . implode(', ', INS_TYPES));
        }
        $options = [];
        if (isset($p['options']) && trim((string)$p['options']) !== '') {
            $options = array_values(array_filter(array_map('trim', explode('|', (string)$p['options'])), 'strlen'));
        }
        if (in_array($type, ['choix', 'liste', 'cases'], true) && count($options) < 1) {
            throw new RuntimeException("$id.ini : [$nom] de type $type demande « options = \"A | B | C\" »");
        }
        $champ = [
            'nom'         => $nom,
            'type'        => $type,
            'libelle'     => trim((string)($p['libelle'] ?? ($p['texte'] ?? $nom))),
            'aide'        => trim((string)($p['aide'] ?? '')),
            'obligatoire' => ins_oui($p['obligatoire'] ?? ''),
            'options'     => $options,
            'defaut'      => trim((string)($p['defaut'] ?? '')),
            'min'         => isset($p['min']) && $p['min'] !== '' ? (string)$p['min'] : null,
            'max'         => isset($p['max']) && $p['max'] !== '' ? (string)$p['max'] : null,
            'unique'      => ins_oui($p['unique'] ?? ''),
            'exemple'     => trim((string)($p['exemple'] ?? '')),
            'si'          => [],
        ];
        if (isset($p['si']) && trim((string)$p['si']) !== '') {
            $champ['si'] = ins_conditions((string)$p['si'], $vus, $id, $nom);
        }
        $form['champs'][$nom] = $champ;
        if (!in_array($type, INS_TYPES_DECORATIFS, true)) {
            $vus[$nom] = $champ;
        }
    }
    if (!$vus) {
        throw new RuntimeException("$id.ini ne décrit aucun champ à remplir");
    }
    return $form;
}

function ins_date_ini($v, string $id, string $cle): ?int
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
        throw new RuntimeException("$id.ini : date « $cle » illisible (format AAAA-MM-JJ ou AAAA-MM-JJ HH:MM)");
    }
    return $t;
}

/**
 * « si » : conditions d'affichage, séparées par « & » (toutes doivent être vraies).
 *   profil = Bénévole | Média    → l'une de ces réponses est choisie
 *   profil != Bénévole           → aucune de ces réponses n'est choisie
 *   accord = oui                 → pour un champ de type « case »
 */
function ins_conditions(string $expr, array $connus, string $id, string $nom): array
{
    $res = [];
    foreach (explode('&', $expr) as $morceau) {
        $morceau = trim($morceau);
        if ($morceau === '') {
            continue;
        }
        if (!preg_match('/^([a-z][a-z0-9_]*)\s*(!=|=)\s*(.+)$/is', $morceau, $m)) {
            throw new RuntimeException("$id.ini : condition « si » illisible pour [$nom] : $morceau");
        }
        $cible = $m[1];
        if (!isset($connus[$cible])) {
            throw new RuntimeException("$id.ini : [$nom] dépend de [$cible], qui doit être décrit AVANT lui");
        }
        $valeurs = array_values(array_filter(array_map('trim', explode('|', $m[3])), 'strlen'));
        $c = $connus[$cible];
        if ($c['type'] === 'case') {
            $valeurs = array_map(function ($v) { return ins_oui($v) ? 'oui' : 'non'; }, $valeurs);
        } elseif ($c['options']) {
            $permis = array_map('ins_minuscules', $c['options']);
            foreach ($valeurs as $v) {
                if (!in_array(ins_minuscules($v), $permis, true)) {
                    throw new RuntimeException("$id.ini : [$nom] teste « $v », qui n'est pas une option de [$cible]");
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
/* État du formulaire                                                 */
/* ------------------------------------------------------------------ */

/** @return string '' si ouvert, sinon le message à afficher */
function ins_etat(array $form): string
{
    $t = time();
    if ($form['ouverture'] !== null && $t < $form['ouverture']) {
        return 'Les inscriptions ouvriront le ' . date('d/m/Y à H:i', $form['ouverture']) . '.';
    }
    if ($form['fermeture'] !== null && $t > $form['fermeture']) {
        return 'Les inscriptions sont closes.';
    }
    if ($form['places'] > 0 && ins_nombre_inscrits($form) >= $form['places']) {
        return 'Désolé, toutes les places ont été prises.';
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

function ins_visible(array $c, array $valeurs, array $visibles): bool
{
    foreach ($c['si'] as $cond) {
        $cible = $cond['champ'];
        if (empty($visibles[$cible])) {
            return false; // dépend d'un champ lui-même masqué
        }
        $v = $valeurs[$cible] ?? '';
        $choisis = $v === '' ? [] : array_map('ins_minuscules', array_map('trim', explode('|', $v)));
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
        $visible = ins_visible($c, $valeurs, $visibles);
        $visibles[$nom] = $visible;
        if (!$visible) {
            $valeurs[$nom] = '';
            continue;
        }

        $err = '';
        switch ($c['type']) {
            case 'choix':
            case 'liste':
                $sel = ins_choisis($c, is_array($b) ? null : $b);
                $v = $sel[0] ?? '';
                if ($v === '' && is_string($b) && $b !== '') {
                    $err = 'Choix invalide.';
                }
                break;
            case 'cases':
                $sel = ins_choisis($c, $b);
                $v = implode(' | ', $sel);
                break;
            case 'case':
                $v = ($b !== null && $b !== '' && !is_array($b)) ? 'oui' : 'non';
                if ($c['obligatoire'] && $v === 'non') {
                    $err = 'Cette case doit être cochée.';
                }
                break;
            default:
                $v = is_string($b) ? trim(str_replace("\r\n", "\n", $b)) : '';
                if ($c['type'] !== 'texte_long') {
                    $v = preg_replace('/\s+/u', ' ', $v) ?? '';
                }
                if (!mb_check_encoding($v, 'UTF-8')) {
                    $v = '';
                    $err = 'Texte invalide.';
                } elseif (mb_strlen($v) > $max) {
                    $err = "Texte trop long ($max caractères au plus).";
                } elseif ($v !== '') {
                    $err = ins_verifier_format($c, $v);
                }
        }
        if ($err === '' && $c['obligatoire'] && ($v === '' || ($c['type'] === 'case' && $v === 'non'))) {
            $err = $c['type'] === 'case' ? 'Cette case doit être cochée.' : 'Ce champ est obligatoire.';
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
            return filter_var($v, FILTER_VALIDATE_EMAIL) ? '' : 'Adresse électronique invalide.';
        case 'telephone':
            return preg_match('/^\+?[0-9 .\-()]{6,25}$/', $v) ? '' : 'Numéro de téléphone invalide.';
        case 'nombre':
            if (!preg_match('/^-?\d+([.,]\d+)?$/', $v)) {
                return 'Nombre attendu.';
            }
            $n = (float)str_replace(',', '.', $v);
            if ($c['min'] !== null && $n < (float)$c['min']) {
                return 'Au moins ' . $c['min'] . '.';
            }
            if ($c['max'] !== null && $n > (float)$c['max']) {
                return 'Au plus ' . $c['max'] . '.';
            }
            return '';
        case 'date':
            $d = DateTime::createFromFormat('!Y-m-d', $v);
            if (!$d || $d->format('Y-m-d') !== $v) {
                return 'Date invalide (AAAA-MM-JJ).';
            }
            if ($c['min'] !== null && $v < $c['min']) {
                return 'Date trop ancienne.';
            }
            if ($c['max'] !== null && $v > $c['max']) {
                return 'Date trop tardive.';
            }
            return '';
        case 'texte':
        case 'texte_long':
            if ($c['min'] !== null && mb_strlen($v) < (int)$c['min']) {
                return 'Au moins ' . (int)$c['min'] . ' caractères.';
            }
            if ($c['max'] !== null && mb_strlen($v) > (int)$c['max']) {
                return 'Au plus ' . (int)$c['max'] . ' caractères.';
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
 * Ajoute une inscription au fichier CSV (sous verrou).
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
        throw new RuntimeException("Impossible de verrouiller $dir");
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
            return [null, [], 'Désolé, toutes les places ont été prises.'];
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
                    $erreurs[$nom] = 'Cette valeur a déjà été utilisée pour une inscription.';
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
            throw new RuntimeException("Impossible d'écrire $fichier (droits d'écriture ?)");
        }
        if ($nouveau) {
            fwrite($h, "\xEF\xBB\xBF"); // BOM : Excel reconnaît l'UTF-8
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
    $corps = "Nouvelle inscription n° $numero — " . $form['titre'] . "\n\n";
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
    $sujet = '=?UTF-8?B?' . base64_encode('Inscription : ' . $form['titre']) . '?=';
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
    header("X-Frame-Options: SAMEORIGIN");
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
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
form .champ{background:#fff;border:1px solid var(--bord);border-radius:8px;padding:.8rem 1rem;margin:.7rem 0}
form .champ.erreur{border-color:var(--err)}
label.lib,.lib{display:block;font-weight:600;margin-bottom:.35rem}
.req{color:var(--err)}
.aide{font-size:.9rem;color:#57606a;margin:.1rem 0 .4rem}
.msg{color:var(--err);font-size:.92rem;margin-top:.3rem}
input[type=text],input[type=email],input[type=tel],input[type=number],input[type=date],input[type=password],select,textarea{width:100%;padding:.5rem;border:1px solid var(--bord);border-radius:6px;font:inherit;background:#fff}
textarea{min-height:6rem;resize:vertical}
.opt{display:flex;gap:.5rem;align-items:flex-start;margin:.25rem 0;font-weight:normal}
.opt input{margin-top:.3rem}
.btn,button{display:inline-block;background:var(--c);color:#fff;border:0;border-radius:6px;padding:.6rem 1.2rem;font:inherit;cursor:pointer;text-decoration:none}
.btn.sec{background:#57606a}
.alerte{background:#fdecee;border:1px solid var(--err);color:var(--err);border-radius:6px;padding:.7rem 1rem;margin:1rem 0}
.ok{background:#e8f5e9;border:1px solid #2e7d32;border-radius:6px;padding:1rem;margin:1rem 0}
.ok code{font-size:1.3rem;font-weight:700}
.info{background:#eef4fa;border-radius:6px;padding:.7rem 1rem}
.pot{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}
.cache{display:none}
table{border-collapse:collapse;background:#fff;font-size:.9rem;width:100%}
th,td{border:1px solid var(--bord);padding:.3rem .5rem;text-align:left;vertical-align:top}
th{background:#e9eef3;position:sticky;top:0}
.defile{overflow:auto;max-height:75vh}
.petit{font-size:.88rem;color:#57606a}
CSS;
}
