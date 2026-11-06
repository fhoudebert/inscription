<?php
/*
 * Formulaire d'inscription générique — administration.
 * Consultation et téléchargement des réponses, liens de partage, ajout et suppression
 * de formulaires ; protégé par mot de passe (« admin_mot_de_passe » dans prive/config.php).
 */
declare(strict_types=1);

define('INS_PRIVE', rtrim((string)require __DIR__ . '/emplacement.php', '/'));
require INS_PRIVE . '/lib.php';
$cfg = ins_config();

/* ---------- Administration désactivée (récupération par FTP uniquement) ---------- */
if (!$cfg['admin_actif']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo t('introuvable') . "\n";
    exit;
}

header('Cache-Control: no-store');
$cookie = 'ins_admin';
$duree = 4 * 3600;
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$hash = (string)$cfg['admin_mot_de_passe'];
$gestion = (bool)$cfg['admin_gestion'];

function ins_poser_cookie(string $nom, string $valeur, int $expire, bool $https): void
{
    setcookie($nom, $valeur, ['expires' => $expire, 'path' => '/', 'httponly' => true, 'secure' => $https, 'samesite' => 'Strict']);
}

/* ---------- Langue de l'interface d'administration ---------- */
if (isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'], ins_langues(), true)) {
    ins_poser_cookie('ins_lang', $_GET['lang'], time() + 365 * 86400, $https);
    $_COOKIE['ins_lang'] = $_GET['lang'];
}
if (isset($_COOKIE['ins_lang']) && is_string($_COOKIE['ins_lang'])) {
    ins_langue($_COOKIE['ins_lang']);
}

function ins_choix_langue(): string
{
    $liens = [];
    foreach (ins_langues() as $code) {
        $nom = ins_textes($code)['nom_langue'] ?? $code;
        $liens[] = $code === ins_langue() ? '<b>' . e($nom) . '</b>' : '<a href="?lang=' . e($code) . '">' . e($nom) . '</a>';
    }
    return count($liens) > 1 ? '<p class="langues">' . implode(' · ', $liens) . '</p>' : '';
}

/* ---------- Aucun mot de passe configuré : aide à la création ---------- */
if ($hash === '') {
    ins_entete(t('adm_mdp_titre'));
    echo ins_choix_langue();
    echo '<h1>' . e(t('adm_titre')) . '</h1><div class="info"><p>' . e(t('adm_mdp_absent')) . '</p>'
        . '<p>' . t('adm_mdp_explication') . '</p></div>';
    $mdp = $_POST['mdp'] ?? '';
    if (is_string($mdp) && $mdp !== '') {
        if (mb_strlen($mdp) < 10) {
            echo '<div class="alerte">' . e(t('adm_mdp_court')) . '</div>';
        } else {
            $h = password_hash($mdp, PASSWORD_DEFAULT);
            echo '<div class="ok"><p>' . t('adm_mdp_ligne') . '</p>'
                . '<pre style="white-space:pre-wrap;word-break:break-all">' . e("'admin_mot_de_passe' => '" . $h . "',") . '</pre>'
                . '<p>' . e(t('adm_mdp_ensuite')) . '</p></div>';
        }
    }
    echo '<form method="post"><div class="champ"><label class="lib" for="mdp">' . e(t('adm_mdp_souhaite')) . '</label>'
        . '<input type="password" id="mdp" name="mdp" autocomplete="new-password"></div><button>' . e(t('adm_generer')) . '</button></form>';
    ins_pied();
    exit;
}

/* ---------- Connexion / déconnexion ---------- */
function ins_admin_connecte(string $cookie): bool
{
    $v = $_COOKIE[$cookie] ?? '';
    if (!is_string($v) || !preg_match('/^(\d+)\.([0-9a-f]{32})$/', $v, $m)) {
        return false;
    }
    return (int)$m[1] > time() && hash_equals(ins_signer('admin|' . $m[1] . '|' . (string)ins_config()['admin_mot_de_passe']), $m[2]);
}

$action = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
if ($action === 'sortir') {
    ins_poser_cookie($cookie, '', time() - 3600, $https);
    header('Location: admin.php', true, 303);
    exit;
}
$echec = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mdp'])) {
    if (is_string($_POST['mdp']) && password_verify($_POST['mdp'], $hash)) {
        $exp = time() + $duree;
        ins_poser_cookie($cookie, $exp . '.' . ins_signer("admin|$exp|$hash"), $exp, $https);
        header('Location: admin.php', true, 303);
        exit;
    }
    sleep(2); // freine les essais en série
    $echec = true;
}
if (!ins_admin_connecte($cookie)) {
    ins_entete(t('adm_titre'));
    echo ins_choix_langue();
    echo '<h1>' . e(t('adm_titre_long')) . '</h1>';
    if ($echec) {
        echo '<div class="alerte">' . e(t('adm_mdp_incorrect')) . '</div>';
    }
    echo '<form method="post"><div class="champ"><label class="lib" for="mdp">' . e(t('adm_mdp')) . '</label>'
        . '<input type="password" id="mdp" name="mdp" autocomplete="current-password" autofocus></div>'
        . '<button>' . e(t('adm_connexion')) . '</button></form>';
    ins_pied();
    exit;
}

/* ================================================================== */
/* Connecté                                                           */
/* ================================================================== */
$dir = ins_dossier_donnees();
$dirForm = ins_dossier_formulaires();
$jetonAdmin = ins_signer('csrf-admin|' . (string)($_COOKIE[$cookie] ?? ''));

/** Fichier de données demandé, uniquement parmi ceux du dossier des données. */
function ins_fichier_demande(string $dir, $n): ?string
{
    $n = is_string($n) ? $n : '';
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,100}\.csv$/i', $n) || !is_file($dir . '/' . $n)) {
        return null;
    }
    return $n;
}

/** Adresse complète d'un formulaire (pour le lien de partage). */
function ins_url_absolue(array $form): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $hote = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $chemin = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . $hote . $chemin . '/index.php' . ins_url_formulaire($form);
}

/** Met un descripteur de côté dans formulaires/supprimes/ (récupérable par FTP). */
function ins_mettre_de_cote(string $dirForm, string $id, string $motif, bool $copier): void
{
    $dest = $dirForm . '/supprimes';
    ins_preparer_dossier($dest);
    $cible = $dest . '/' . $id . '-' . $motif . '-' . date('Ymd-His') . '.ini';
    $ok = $copier ? @copy($dirForm . '/' . $id . '.ini', $cible) : @rename($dirForm . '/' . $id . '.ini', $cible);
    if (!$ok) {
        throw new RuntimeException(t('err_ecriture', ['fichier' => $cible]));
    }
}

/* ---------- Éditeur de formulaires ---------- */
function ins_json(array $r): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Texte .ini et formulaire analysé à partir de ce qu'envoie l'éditeur (modèle JSON ou texte brut). */
function ins_editeur_analyser(string $id): array
{
    if (($_POST['source'] ?? '') === 'texte') {
        $texte = str_replace("\r\n", "\n", (string)($_POST['ini'] ?? ''));
    } else {
        $m = json_decode((string)($_POST['modele'] ?? ''), true);
        if (!is_array($m)) {
            throw new RuntimeException(t('edi_err_modele'));
        }
        $texte = ins_modele_vers_ini($id, $m);
    }
    if (strlen($texte) > 200 * 1024) {
        throw new RuntimeException(t('adm_up_taille'));
    }
    return [$texte, ins_analyser_formulaire($id, $texte)];
}

/** Champ cité dans un message d'erreur (« [nom] »), pour le signaler dans l'éditeur. */
function ins_champ_en_erreur(string $msg): string
{
    return preg_match('/\[([a-z][a-z0-9_]*)\]/i', $msg, $m) ? $m[1] : '';
}

/** Effet sur le fichier de réponses : colonnes ajoutées / retirées, archivage à la prochaine réponse. */
function ins_effet_colonnes(array $form): array
{
    $csv = ins_fichier_donnees($form);
    $avant = is_file($csv) ? (ins_lire_csv($csv)[0] ?? []) : [];
    $apres = ins_colonnes($form);
    return [
        'reponses'  => $avant ? max(0, count(ins_lire_csv($csv)) - 1) : 0,
        'ajoutees'  => $avant ? array_values(array_diff($apres, $avant)) : [],
        'retirees'  => $avant ? array_values(array_diff($avant, $apres)) : [],
        'archivage' => $avant && $avant !== $apres,
    ];
}

if ($action === 'editeur' || (isset($_POST['action']) && in_array($_POST['action'], ['editeur_apercu', 'editeur_enregistrer'], true))) {
    $ajax = $_SERVER['REQUEST_METHOD'] === 'POST';
    if (!$gestion) {
        $ajax ? ins_json(['ok' => false, 'erreur' => t('adm_gestion_off')]) : exit(t('adm_gestion_off'));
    }
    if ($ajax) {
        if (!hash_equals($jetonAdmin, (string)($_POST['_jeton'] ?? ''))) {
            // Connecté, mais jeton d'une session précédente (reconnexion dans un autre onglet) : on renvoie le jeton
            // actuel pour que l'éditeur recommence. Une page tierce ne peut pas lire cette réponse (même origine).
            ins_json(['ok' => false, 'erreur' => t('adm_jeton'), 'jeton' => $jetonAdmin]);
        }
        $id = (string)($_POST['id'] ?? '');
        $nouveau = !empty($_POST['nouveau']);
        $idAnalyse = ins_id_valide($id) ? $id : 'nouveau';
        try {
            [$texte, $form] = ins_editeur_analyser($idAnalyse);
            if ($idAnalyse !== 'nouveau' || ins_id_valide($id)) {
                ins_verifier_fichier_unique($form); // vérifié dès l'aperçu, donc aussi à l'enregistrement
            }
        } catch (Throwable $ex) {
            $r = ['ok' => false, 'erreur' => $ex->getMessage(), 'champ' => ins_champ_en_erreur($ex->getMessage())];
            if (($_POST['source'] ?? '') !== 'texte') {
                try {
                    $r['ini'] = ins_modele_vers_ini($idAnalyse, (array)json_decode((string)($_POST['modele'] ?? ''), true));
                } catch (Throwable $ex2) {
                }
            }
            ins_json($r);
        }
        if ($_POST['action'] === 'editeur_apercu') {
            ins_json(['ok' => true, 'ini' => $texte, 'modele' => ins_modele($form),
                'apercu' => ins_apercu_document($form, (string)($_POST['langue'] ?? ''), (string)($_POST['choisi'] ?? '')),
                'colonnes' => ins_effet_colonnes($form)]);
        }
        // Enregistrement
        if (!ins_id_valide($id)) {
            ins_json(['ok' => false, 'erreur' => t('adm_up_nom', ['nom' => $id]), 'champ' => '']);
        }
        try {
            ins_preparer_dossier($dirForm);
            if (!is_writable($dirForm)) {
                throw new RuntimeException(t('adm_up_droits', ['dossier' => $dirForm]));
            }
            $chemin = $dirForm . '/' . $id . '.ini';
            $existe = is_file($chemin);
            if ($nouveau && $existe) {
                throw new RuntimeException(t('adm_up_existe', ['nom' => $id]));
            }
            if (!$nouveau) {
                if (!$existe) {
                    throw new RuntimeException(t('introuvable'));
                }
                if (!hash_equals(sha1((string)file_get_contents($chemin)), (string)($_POST['version'] ?? ''))) {
                    throw new RuntimeException(t('edi_err_version'));
                }
                ins_mettre_de_cote($dirForm, $id, 'remplace', true);
            }
            $tmp = $dirForm . '/.editeur-' . bin2hex(random_bytes(6)) . '.tmp';
            if (@file_put_contents($tmp, $texte, LOCK_EX) === false || !@rename($tmp, $chemin)) {
                @unlink($tmp);
                throw new RuntimeException(t('err_ecriture', ['fichier' => $chemin]));
            }
        } catch (Throwable $ex) {
            ins_json(['ok' => false, 'erreur' => $ex->getMessage(), 'champ' => '']);
        }
        ins_json(['ok' => true, 'version' => sha1($texte), 'message' => t($nouveau ? 'adm_ok_ajoute' : 'edi_ok_enregistre', ['id' => $id]),
            'url' => 'admin.php?' . http_build_query(['a' => 'editeur', 'id' => $id])]);
    }

    // Page de l'éditeur : formulaire existant (?id=), nouveau à partir d'un modèle (?nouveau=) ou d'une copie (?copie=)
    $id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
    $modeles = ins_modeles_depart();
    $nouveau = $id === '';
    $erreurInitiale = '';
    if (!$nouveau) {
        if (!ins_id_valide($id) || !is_file($dirForm . '/' . $id . '.ini')) {
            http_response_code(404);
            exit(t('introuvable'));
        }
        $texte = (string)file_get_contents($dirForm . '/' . $id . '.ini');
        $version = sha1($texte);
    } else {
        $copie = isset($_GET['copie']) && is_string($_GET['copie']) ? $_GET['copie'] : '';
        $choix = isset($_GET['nouveau']) && is_string($_GET['nouveau']) ? $_GET['nouveau'] : 'vide';
        $copieValide = $copie !== '' && ins_id_valide($copie) && is_file($dirForm . '/' . $copie . '.ini');
        $texte = $copieValide
            ? (string)file_get_contents($dirForm . '/' . $copie . '.ini')
            : ($modeles[$choix] ?? $modeles['vide']);
        $version = '';
    }
    $texte = preg_replace('/^\xEF\xBB\xBF/', '', str_replace("\r\n", "\n", $texte)) ?? $texte;
    $modele = null;
    try {
        $modele = ins_modele(ins_analyser_formulaire($nouveau ? 'nouveau' : $id, $texte));
        if ($nouveau) {
            // Une copie ne doit jamais partager le fichier de réponses (ni les accès) de l'original
            $modele['formulaire']['fichier'] = '';
        }
    } catch (Throwable $ex) {
        $erreurInitiale = $ex->getMessage();
    }
    $textes = [];
    foreach (ins_textes('fr') as $k => $v) {
        if (strpos($k, 'edi_') === 0 || strpos($k, 'type_') === 0) {
            $textes[$k] = t($k);
        }
    }
    $donnees = [
        'id' => $id, 'nouveau' => $nouveau, 'version' => $version, 'ini' => $texte, 'modele' => $modele,
        'erreur' => $erreurInitiale, 'jeton' => $jetonAdmin, 'types' => INS_TYPES, 'proprietes' => INS_PROPRIETES_TYPE,
        'reserves' => INS_NOMS_RESERVES, 'langue_config' => (string)$cfg['langue'],
        'langues' => array_map(function ($c) { return ['code' => $c, 'nom' => ins_textes($c)['nom_langue'] ?? $c]; }, ins_langues()),
        'existants' => array_values(array_map(function ($f) { return basename($f, '.ini'); }, glob($dirForm . '/*.ini') ?: [])),
        'catalogues' => ins_catalogues(),
        'textes' => $textes,
    ];
    ins_entete($nouveau ? t('edi_titre_nouveau') : t('edi_titre', ['id' => $id]));
    echo '<script>document.querySelector("main").className="large"</script>';
    echo '<style>' . (string)@file_get_contents(INS_PRIVE . '/editeur.css') . '</style>';
    echo '<div id="editeur"><noscript>' . e(t('edi_sans_js')) . '</noscript></div>';
    echo '<script>window.INS_EDITEUR=' . json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
    echo '<script>' . (string)@file_get_contents(INS_PRIVE . '/editeur.js') . '</script>';
    ins_pied();
    exit;
}

/* ---------- Téléchargements ---------- */
if ($action === 'telecharger') {
    $n = ins_fichier_demande($dir, $_GET['fichier'] ?? '');
    if ($n === null) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $n . '"');
    header('Content-Length: ' . filesize($dir . '/' . $n));
    readfile($dir . '/' . $n);
    exit;
}
if ($action === 'ini') {
    $id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
    if (!ins_id_valide($id) || !is_file($dirForm . '/' . $id . '.ini')) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $id . '.ini"');
    readfile($dirForm . '/' . $id . '.ini');
    exit;
}

/* ---------- Commandes et catalogues (pages et actions dans prive/admin-commandes.php) ---------- */
$alerteCatalogue = '';
require INS_PRIVE . '/admin-commandes.php';

/* ---------- Actions de gestion (POST) ---------- */
$message = '';
$alerte = '';
$detail = '';
$nouveauxAcces = null;   // accès qui viennent d'être créés ou renouvelés (secrets affichés une seule fois)
$ouvrirAcces = '';       // formulaire dont la rubrique « Accès » reste ouverte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !in_array($_POST['action'], $actionsCommandes, true)) {
    $act = (string)$_POST['action'];
    try {
        if (!hash_equals($jetonAdmin, (string)($_POST['_jeton'] ?? ''))) {
            throw new RuntimeException(t('adm_jeton'));
        }
        if (!$gestion) {
            throw new RuntimeException(t('adm_gestion_off'));
        }
        if ($act === 'televerser') {
            $up = $_FILES['ini'] ?? null;
            if (!is_array($up) || ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$up['tmp_name'])) {
                throw new RuntimeException(t('adm_up_aucun'));
            }
            if ((int)$up['size'] > 200 * 1024) {
                throw new RuntimeException(t('adm_up_taille'));
            }
            $nom = trim((string)($_POST['nom'] ?? ''));
            if ($nom === '') {
                $nom = preg_replace('/\.(ini|txt)$/i', '', basename((string)$up['name'])) ?? '';
            }
            if (!ins_id_valide($nom)) {
                throw new RuntimeException(t('adm_up_nom', ['nom' => $nom]));
            }
            ins_preparer_dossier($dirForm);
            if (!is_writable($dirForm)) {
                throw new RuntimeException(t('adm_up_droits', ['dossier' => $dirForm]));
            }
            $existe = is_file($dirForm . '/' . $nom . '.ini');
            if ($existe && empty($_POST['remplacer'])) {
                throw new RuntimeException(t('adm_up_existe', ['nom' => $nom]));
            }
            $tmp = $dirForm . '/.televersement-' . bin2hex(random_bytes(6)) . '.tmp';
            if (!move_uploaded_file((string)$up['tmp_name'], $tmp)) {
                throw new RuntimeException(t('err_ecriture', ['fichier' => $tmp]));
            }
            try {
                $nouveau = ins_charger_formulaire($nom, $tmp); // vérification complète AVANT installation
                ins_verifier_fichier_unique($nouveau);
            } catch (Throwable $ex) {
                @unlink($tmp);
                $detail = $ex->getMessage();
                throw new RuntimeException(t('adm_up_invalide'));
            }
            if ($existe) {
                ins_mettre_de_cote($dirForm, $nom, 'remplace', true);
            }
            if (!@rename($tmp, $dirForm . '/' . $nom . '.ini')) {
                @unlink($tmp);
                throw new RuntimeException(t('err_ecriture', ['fichier' => $dirForm . '/' . $nom . '.ini']));
            }
            $q = ['ok' => $existe ? 'remplace' : 'ajoute', 'id' => $nom];
            // Les colonnes ont changé alors que des réponses existent : prévenir de l'archivage
            $csv = ins_fichier_donnees($nouveau);
            if (is_file($csv) && (ins_lire_csv($csv)[0] ?? []) !== ins_colonnes($nouveau)) {
                $q['archive'] = 1;
            }
            header('Location: admin.php?' . http_build_query($q), true, 303);
            exit;
        }
        if ($act === 'supprimer') {
            $id = (string)($_POST['id'] ?? '');
            if (!ins_id_valide($id) || !is_file($dirForm . '/' . $id . '.ini')) {
                throw new RuntimeException(t('introuvable'));
            }
            if (empty($_POST['confirmer'])) {
                throw new RuntimeException(t('adm_confirmer_requis'));
            }
            ins_mettre_de_cote($dirForm, $id, 'supprime', false);
            header('Location: admin.php?' . http_build_query(['ok' => 'supprime', 'id' => $id]), true, 303);
            exit;
        }
        if ($act === 'supprimer_csv') {
            $n = ins_fichier_demande($dir, $_POST['fichier'] ?? '');
            if ($n === null) {
                throw new RuntimeException(t('introuvable'));
            }
            if (empty($_POST['confirmer'])) {
                throw new RuntimeException(t('adm_confirmer_requis'));
            }
            if (!@unlink($dir . '/' . $n)) {
                throw new RuntimeException(t('err_ecriture', ['fichier' => $n]));
            }
            // le détail des commandes va avec le fichier de réponses
            $json = $dir . '/commandes/' . basename($n, '.csv') . '.json';
            if (is_file($json)) {
                @unlink($json);
            }
            header('Location: admin.php?' . http_build_query(['ok' => 'csv_supprime', 'id' => $n]), true, 303);
            exit;
        }
        if ($act === 'acces_generer' || $act === 'acces_modifier') {
            $id = (string)($_POST['id'] ?? '');
            $formAcces = ins_charger_formulaire($id);
            if ($formAcces['acces'] === '') {
                throw new RuntimeException(t('introuvable'));
            }
            if ($act === 'acces_generer') {
                // Un accès par ligne (nom, adresse…), sinon un nombre d'accès sans nom
                $noms = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['noms'] ?? '')) ?: []), 'strlen'));
                if (!$noms) {
                    $noms = array_fill(0, max(0, min(500, (int)($_POST['nombre'] ?? 0))), '');
                }
                if (!$noms || count($noms) > 500) {
                    throw new RuntimeException(t('adm_acces_nombre'));
                }
                $noms = array_map(function ($n) { return mb_substr($n, 0, 200); }, $noms);
                $nouveauxAcces = ['form' => $formAcces, 'liste' => ins_creer_acces($formAcces, $noms)];
            } else {
                $op = (string)($_POST['op'] ?? '');
                $aid = (string)($_POST['acces'] ?? '');
                $clair = ins_modifier_acces($formAcces, $aid, $op);
                if ($clair !== null) {
                    $a = ins_lire_acces($formAcces)[$aid];
                    $nouveauxAcces = ['form' => $formAcces, 'liste' => [['identifiant' => $aid, 'nom' => $a['nom'], 'secret' => $clair]]];
                } else {
                    $message = t($op === 'revoquer' ? 'adm_acces_revoque' : 'adm_acces_reactive', ['id' => $aid]);
                }
            }
            $ouvrirAcces = $id;
        } else {
            throw new RuntimeException(t('introuvable'));
        }
    } catch (Throwable $ex) {
        $alerte = $ex->getMessage();
    }
}
if (isset($_GET['ok']) && is_string($_GET['ok'])) {
    $idOk = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
    $codes = ['ajoute' => 'adm_ok_ajoute', 'remplace' => 'adm_ok_remplace', 'supprime' => 'adm_ok_supprime', 'csv_supprime' => 'adm_ok_csv_supprime'];
    if (isset($codes[$_GET['ok']])) {
        $message = t($codes[$_GET['ok']], ['id' => $idOk]);
        if (!empty($_GET['archive'])) {
            $message .= ' ' . t('adm_ok_archive');
        }
    }
}

/** Petit formulaire POST de suppression, avec case de confirmation. */
function ins_bouton_suppression(string $action, string $champ, string $valeur, string $libelle, string $jeton): string
{
    return '<details><summary>' . e($libelle) . '</summary><form method="post" class="champ">'
        . '<input type="hidden" name="_jeton" value="' . e($jeton) . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="' . e($champ) . '" value="' . e($valeur) . '">'
        . '<p class="petit">' . e(t($action === 'supprimer' ? 'adm_suppr_form_expl' : 'adm_suppr_csv_expl')) . '</p>'
        . '<label class="opt"><input type="checkbox" name="confirmer" value="1"> <span>' . e(t('adm_confirmer')) . '</span></label>'
        . '<button class="dang">' . e($libelle) . '</button></form></details>';
}

/** CSV (identifiant, nom, mot de passe, lien) des accès qui viennent d'être créés, en lien de téléchargement. */
function ins_csv_nouveaux_acces(array $form, array $liste): string
{
    $sep = (string)ins_config()['separateur'];
    $h = fopen('php://temp', 'w+b');
    fwrite($h, "\xEF\xBB\xBF");
    $lien = $form['acces'] === 'lien';
    fputcsv($h, $lien ? ['identifiant', 'nom', 'email', 'telephone', 'lien'] : ['identifiant', 'nom', 'email', 'telephone', 'mot_de_passe', 'lien'], $sep, '"', '');
    foreach ($liste as $a) {
        $url = ins_lien_acces($form, $a['identifiant'], $a['secret']);
        $debut = [$a['identifiant'], ins_csv_sur($a['nom']), ins_csv_sur($a['email'] ?? ''), ins_csv_sur($a['telephone'] ?? '')];
        fputcsv($h, $lien ? array_merge($debut, [$url]) : array_merge($debut, [$a['secret'], $url]), $sep, '"', '');
    }
    rewind($h);
    $csv = (string)stream_get_contents($h);
    fclose($h);
    return 'data:text/csv;charset=utf-8;base64,' . base64_encode($csv);
}

/** E-mail et téléphone d'un accès, sous son nom. */
function ins_contact_html(array $a): string
{
    $c = array_filter([(string)($a['email'] ?? ''), (string)($a['telephone'] ?? '')], 'strlen');
    return $c ? ($a['nom'] !== '' ? '<br>' : '') . '<span class="petit">' . e(implode(' · ', $c)) . '</span>' : '';
}

/** Petit bouton POST d'action sur un accès. */
function ins_bouton_acces(string $fid, string $aid, string $op, string $libelle, string $jeton, string $classe = 'sec'): string
{
    return '<form method="post" style="display:inline">'
        . '<input type="hidden" name="_jeton" value="' . e($jeton) . '">'
        . '<input type="hidden" name="action" value="acces_modifier">'
        . '<input type="hidden" name="id" value="' . e($fid) . '">'
        . '<input type="hidden" name="acces" value="' . e($aid) . '">'
        . '<input type="hidden" name="op" value="' . e($op) . '">'
        . '<button class="' . e($classe) . '" style="padding:.2rem .6rem;font-size:.85rem">' . e($libelle) . '</button></form>';
}

/* ---------- Affichage d'un fichier de réponses ---------- */
if ($action === 'voir') {
    $n = ins_fichier_demande($dir, $_GET['fichier'] ?? '');
    if ($n === null) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    $lignes = ins_lire_csv($dir . '/' . $n);
    ins_entete($n);
    echo '<script>document.querySelector("main").className="large"</script>';
    echo '<p><a href="admin.php">← ' . e(t('adm_retour')) . '</a> · <a href="?a=telecharger&amp;fichier=' . e(rawurlencode($n)) . '">' . e(t('adm_telecharger')) . '</a></p>';
    echo '<h1>' . e($n) . ' — ' . e(t('adm_nb_reponses', ['n' => max(0, count($lignes) - 1)])) . '</h1>';
    if ($lignes) {
        echo '<div class="defile"><table><thead><tr>';
        foreach ($lignes[0] as $tt) {
            echo '<th>' . e($tt) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach (array_slice($lignes, 1) as $l) {
            echo '<tr>';
            foreach ($l as $v) {
                echo '<td>' . nl2br(e($v)) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    ins_pied();
    exit;
}

/** Rubrique « Accès » d'un formulaire à diffusion restreinte. */
function ins_rubrique_acces(array $form, bool $gestion, string $jeton, bool $ouverte): string
{
    $tous = ins_lire_acces($form);
    $repondus = count(array_filter($tous, function ($a) { return $a['repondu'] !== ''; }));
    $actifs = count(array_filter($tous, function ($a) { return $a['etat'] === 'actif'; }));
    $h = '<div class="petit" style="margin:.4rem 0">' . e(t('adm_acces_resume', ['n' => count($tous), 'actifs' => $actifs, 'repondus' => $repondus]))
        . (count($tous) === 0 ? ' <b>' . e(t('adm_acces_aucun')) . '</b>' : '') . '</div>';
    $h .= '<details' . ($ouverte ? ' open' : '') . '><summary>' . e(t('adm_acces_gerer')) . '</summary>';
    if ($tous) {
        $h .= '<div class="defile" style="max-height:24rem;margin:.5rem 0"><table><thead><tr><th>' . e(t('acces_identifiant')) . '</th><th>'
            . e(t('adm_acces_nom')) . '</th><th>' . e(t('adm_acces_etat')) . '</th><th>' . e(t('adm_acces_reponse')) . '</th>'
            . ($gestion ? '<th></th>' : '') . '</tr></thead><tbody>';
        foreach ($tous as $a) {
            if ($a['etat'] !== 'actif') {
                $etat = t('adm_acces_etat_revoque');
            } elseif ($a['bloque'] !== '' && (int)$a['bloque'] > time()) {
                $etat = t('adm_acces_etat_bloque', ['date' => ins_date_lisible((int)$a['bloque'])]);
            } else {
                $etat = t('adm_acces_etat_actif');
            }
            $rep = $a['repondu'] === '' ? '—' : t('adm_acces_repondu', ['date' => ins_date_lisible((int)strtotime($a['repondu']))])
                . ($a['modifie'] !== '' ? ' · ' . t('adm_acces_modifie', ['date' => ins_date_lisible((int)strtotime($a['modifie']))]) : '');
            $h .= '<tr><td><code>' . e($a['identifiant']) . '</code></td><td>' . e($a['nom']) . ins_contact_html($a) . '</td><td>' . e($etat) . '</td><td>' . e($rep) . '</td>';
            if ($gestion) {
                $bloque = $a['bloque'] !== '' && (int)$a['bloque'] > time();
                $h .= '<td class="actions">'
                    . ($a['etat'] === 'actif' ? ins_bouton_acces($form['id'], $a['identifiant'], 'revoquer', t('adm_acces_revoquer'), $jeton, 'dang') : '')
                    . ($a['etat'] !== 'actif' || $bloque ? ins_bouton_acces($form['id'], $a['identifiant'], 'reactiver', t('adm_acces_reactiver'), $jeton) : '')
                    . ' ' . ins_bouton_acces($form['id'], $a['identifiant'], 'renouveler',
                        t($form['acces'] === 'lien' ? 'adm_acces_nouveau_lien' : 'adm_acces_nouveau_mdp'), $jeton)
                    . '</td>';
            }
            $h .= '</tr>';
        }
        $h .= '</tbody></table></div>';
    }
    if ($gestion) {
        $h .= '<form method="post" class="champ">'
            . '<input type="hidden" name="_jeton" value="' . e($jeton) . '">'
            . '<input type="hidden" name="action" value="acces_generer">'
            . '<input type="hidden" name="id" value="' . e($form['id']) . '">'
            . '<label class="lib" for="noms_' . e($form['id']) . '">' . e(t('adm_acces_noms')) . '</label>'
            . '<textarea id="noms_' . e($form['id']) . '" name="noms" rows="4" placeholder="' . e(t('adm_acces_noms_exemple')) . '"></textarea>'
            . '<p class="petit">' . e(t('adm_acces_noms_aide')) . '</p>'
            . '<label class="lib" for="nb_' . e($form['id']) . '" style="margin-top:.6rem">' . e(t('adm_acces_ou_nombre')) . '</label>'
            . '<input type="number" id="nb_' . e($form['id']) . '" name="nombre" min="1" max="500" style="max-width:8rem">'
            . '<p class="petit">' . e(t($form['acces'] === 'lien' ? 'adm_acces_aide_lien' : 'adm_acces_aide')) . '</p>'
            . '<button>' . e(t('adm_acces_generer')) . '</button></form>';
    }
    return $h . '</details>';
}

/* ---------- Tableau de bord ---------- */
ins_entete(t('adm_titre_long'));
$langs = strip_tags(ins_choix_langue(), '<a><b>');
echo '<p class="langues">' . ($langs !== '' ? $langs . ' · ' : '') . '<a href="?a=sortir">' . e(t('adm_deconnexion')) . '</a></p>';
echo '<h1>' . e(t('adm_titre_long')) . '</h1>';
if ($message !== '') {
    echo '<div class="ok">' . e($message) . '</div>';
}
if ($alerte !== '') {
    echo '<div class="alerte">' . e($alerte) . ($detail !== '' ? '<br><b>' . e($detail) . '</b>' : '') . '</div>';
}
if ($alerteCatalogue !== '') {
    echo '<div class="alerte">' . e($alerteCatalogue) . '</div>';
}
if ($nouveauxAcces !== null) {
    $fa = $nouveauxAcces['form'];
    $modeLien = $fa['acces'] === 'lien';
    echo '<div class="ok"><b>' . e(t('adm_acces_crees', ['n' => count($nouveauxAcces['liste']), 'titre' => $fa['titre']])) . '</b>'
        . '<p>' . e(t($modeLien ? 'adm_acces_une_fois_lien' : 'adm_acces_une_fois')) . '</p>'
        . '<p><a class="btn" download="acces-' . e($fa['id']) . '-' . date('Ymd-His') . '.csv" href="'
        . e(ins_csv_nouveaux_acces($fa, $nouveauxAcces['liste'])) . '">' . e(t('adm_acces_telecharger')) . '</a></p>'
        . '<div class="defile" style="max-height:20rem"><table><thead><tr><th>' . e(t('acces_identifiant')) . '</th><th>' . e(t('adm_acces_nom')) . '</th>'
        . ($modeLien ? '' : '<th>' . e(t('acces_mot_de_passe')) . '</th>') . '<th>' . e(t('adm_acces_lien')) . '</th></tr></thead><tbody>';
    foreach ($nouveauxAcces['liste'] as $a) {
        echo '<tr><td><code>' . e($a['identifiant']) . '</code></td><td>' . e($a['nom']) . ins_contact_html($a) . '</td>'
            . ($modeLien ? '' : '<td><code>' . e($a['secret']) . '</code></td>')
            . '<td style="word-break:break-all">' . e(ins_lien_acces($fa, $a['identifiant'], $a['secret'])) . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
}

// Auto-test : la zone privée est-elle lisible depuis le web ?
$urlPublique = ins_url_si_public($dir);
if ($urlPublique !== null) {
    $temoin = '.temoin-' . substr(ins_signer('temoin'), 0, 12) . '.txt';
    @file_put_contents($dir . '/' . $temoin, 'TEMOIN-PRIVE');
    $u = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $urlPublique), '/') . '/' . $temoin;
    echo '<div id="test-prive" class="info">' . e(t('adm_test_encours')) . '</div>';
    echo '<script>fetch(' . json_encode($u) . ',{cache:"no-store"}).then(function(r){return r.ok?r.text():""})'
        . '.catch(function(){return ""}).then(function(t){var d=document.getElementById("test-prive");'
        . 'if(t.indexOf("TEMOIN-PRIVE")>=0){d.className="alerte";d.textContent=' . json_encode(t('adm_test_expose')) . ';}'
        . 'else{d.className="ok";d.textContent=' . json_encode(t('adm_test_protege')) . ';}});</script>';
} else {
    echo '<div class="ok">' . e(t('adm_test_hors_site')) . '</div>';
}

$formulaires = [];
foreach (glob($dirForm . '/*.ini') ?: [] as $f) {
    $id = basename($f, '.ini');
    if (!ins_id_valide($id)) {
        continue;
    }
    try {
        $formulaires[$id] = ins_charger_formulaire($id);
    } catch (Throwable $ex) {
        $formulaires[$id] = $ex->getMessage();
    }
}

echo '<h2>' . e(t('adm_formulaires')) . '</h2>';
if (!$formulaires) {
    echo '<p>' . e(t('adm_aucun_formulaire')) . '</p>';
}
$fichiersVus = [];
$parFichier = [];
foreach ($formulaires as $id => $form) {
    if (is_array($form)) {
        $parFichier[$form['fichier']][] = $id;
    }
}
foreach ($formulaires as $id => $form) {
    echo '<div class="champ">';
    if (is_string($form)) {
        echo '<b>' . e($id) . '.ini</b><div class="alerte">' . e($form) . '</div>';
        if ($gestion) {
            echo '<a class="btn" href="?a=editeur&amp;id=' . e(rawurlencode($id)) . '">' . e(t('adm_modifier_texte')) . '</a> ';
        }
        echo '<a class="btn sec" href="?a=ini&amp;id=' . e(rawurlencode($id)) . '">' . e(t('adm_telecharger_ini')) . '</a>';
        if ($gestion) {
            echo ins_bouton_suppression('supprimer', 'id', $id, t('adm_supprimer_form'), $jetonAdmin);
        }
        echo '</div>';
        continue;
    }
    $n = $form['fichier'] . '.csv';
    $fichiersVus[$n] = true;
    $nb = ins_nombre_inscrits($form);
    $etat = ins_etat($form);
    $lien = ins_url_absolue($form);
    if ($form['acces'] !== '') {
        $etiquette = '<span class="etiq prive">' . e(t($form['acces'] === 'lien' ? 'adm_acces_lien_seul' : 'adm_acces_personnel')) . '</span>';
    } elseif ($form['diffusion']) {
        $etiquette = '<span class="etiq">' . e(t('adm_diffuse')) . '</span>';
    } else {
        $etiquette = '<span class="etiq prive">' . e(t('adm_non_diffuse')) . '</span>';
    }
    echo '<b>' . e($form['titre']) . '</b> <span class="petit">(' . e($id) . '.ini)</span> ' . $etiquette . '<br>';
    if (count($parFichier[$form['fichier']]) > 1) {
        echo '<div class="alerte">' . e(t('adm_fichier_partage', ['fichier' => $form['fichier'],
            'autres' => implode(', ', array_diff($parFichier[$form['fichier']], [$id]))])) . '</div>';
    }
    echo e(t('adm_nb_reponses', ['n' => $nb])) . ($form['places'] ? ' / ' . e(t('adm_places', ['n' => $form['places']])) : '');
    echo ' · ' . ($etat === '' ? e(t('adm_ouvert')) : e($etat));
    if (ins_multilingue($form)) {
        $noms = array_map(function ($lg) { return ins_textes($lg)['nom_langue'] ?? $lg; }, $form['langues']);
        echo ' · ' . e(t('adm_langues', ['langues' => implode(', ', $noms)]));
    }
    if ($form['acces'] !== '') {
        echo ins_rubrique_acces($form, $gestion, $jetonAdmin, $ouvrirAcces === $id);
    } else {
        echo '<div class="lien"><input type="text" readonly value="' . e($lien) . '" aria-label="' . e(t('adm_lien_partage')) . '">'
            . '<button type="button" class="sec copier">' . e(t('adm_copier')) . '</button>'
            . '<a class="btn sec" href="' . e($lien) . '" target="_blank" rel="noopener">' . e(t('adm_ouvrir')) . '</a></div>';
        if (!$form['diffusion']) {
            echo '<div class="petit">' . e(t('adm_lien_explication')) . '</div>';
        }
    }
    if ($gestion) {
        echo '<a class="btn" href="?a=editeur&amp;id=' . e(rawurlencode($id)) . '">' . e(t('adm_modifier')) . '</a> ';
    }
    $champCmd = ins_champ_commande($form);
    if ($champCmd !== null) {
        $cmds = ins_lire_commandes($form);
        $aPreparer = count(array_filter($cmds, function ($c) { return in_array($c['statut'], ['nouvelle', 'preparee'], true); }));
        echo '<a class="btn" href="?a=commandes&amp;id=' . e(rawurlencode($id)) . '">' . e(t('adm_cmd_bouton', ['n' => $aPreparer])) . '</a> '
            . '<a class="btn sec" href="?a=catalogue&amp;nom=' . e(rawurlencode($champCmd['catalogue'])) . '">' . e(t('adm_cmd_catalogue')) . '</a> ';
    }
    if ($nb > 0 || is_file($dir . '/' . $n)) {
        echo '<a class="btn" href="?a=telecharger&amp;fichier=' . e(rawurlencode($n)) . '">' . e(t('adm_telecharger')) . ' ' . e($n) . '</a> '
            . '<a class="btn sec" href="?a=voir&amp;fichier=' . e(rawurlencode($n)) . '">' . e(t('adm_voir')) . '</a> ';
    }
    echo '<a class="btn sec" href="?a=ini&amp;id=' . e(rawurlencode($id)) . '">' . e(t('adm_telecharger_ini')) . '</a>';
    if ($gestion) {
        echo ins_bouton_suppression('supprimer', 'id', $id, t('adm_supprimer_form'), $jetonAdmin);
        if (is_file($dir . '/' . $n)) {
            echo ins_bouton_suppression('supprimer_csv', 'fichier', $n, t('adm_supprimer_csv'), $jetonAdmin);
        }
    }
    echo '</div>';
}

/* ---------- Catalogues de produits (formulaires de commande) ---------- */
echo '<h2>' . e(t('adm_cat_rubrique')) . '</h2><div class="champ"><p class="petit">' . e(t('adm_cat_rubrique_aide')) . '</p>';
$cats = ins_catalogues();
if ($cats) {
    echo '<ul>';
    foreach ($cats as $cat) {
        $prods = ins_lire_catalogue($cat);
        $dispo = count(array_filter($prods, function ($p) { return $p['disponible']; }));
        echo '<li><b>' . e($cat) . '</b> — ' . e(t('adm_cat_resume', ['n' => count($prods), 'dispo' => $dispo])) . ' · '
            . ($gestion ? '<a href="?a=catalogue&amp;nom=' . e(rawurlencode($cat)) . '">' . e(t('adm_modifier')) . '</a> · ' : '')
            . '<a href="?a=catalogue_csv&amp;nom=' . e(rawurlencode($cat)) . '">' . e(t('adm_telecharger')) . '</a></li>';
    }
    echo '</ul>';
}
if ($gestion) {
    echo '<form method="post" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap"><input type="hidden" name="_jeton" value="' . e($jetonAdmin) . '">'
        . '<input type="hidden" name="action" value="catalogue_nouveau"><input type="text" name="nom" placeholder="' . e(t('adm_cat_nom_ex')) . '" pattern="[A-Za-z0-9][A-Za-z0-9_\-]*" required style="width:14rem" aria-label="' . e(t('adm_cat_nom')) . '">'
        . '<button class="sec">' . e(t('adm_cat_nouveau')) . '</button></form>';
    echo '<details><summary>' . e(t('adm_cat_importer')) . '</summary><form method="post" enctype="multipart/form-data" class="champ">'
        . '<input type="hidden" name="_jeton" value="' . e($jetonAdmin) . '"><input type="hidden" name="action" value="catalogue_importer">'
        . '<p class="petit">' . e(t('adm_cat_import_aide')) . '</p>'
        . '<label class="lib">' . e(t('adm_cat_nom')) . '</label><input type="text" name="nom" pattern="[A-Za-z0-9][A-Za-z0-9_\-]*" required placeholder="' . e(t('adm_cat_nom_ex')) . '">'
        . '<p><input type="file" name="csv" accept=".csv,text/csv,text/plain"></p>'
        . '<label class="opt"><input type="checkbox" name="remplacer" value="1"> <span>' . e(t('adm_cat_remplacer')) . '</span></label>'
        . '<button>' . e(t('adm_cat_importer')) . '</button></form></details>';
}
echo '</div>';

/* ---------- Création dans l'éditeur ---------- */
if ($gestion) {
    echo '<h2>' . e(t('adm_creer')) . '</h2><div class="champ"><p class="petit">' . e(t('adm_creer_aide')) . '</p>';
    foreach (['vide' => 'adm_creer_vide', 'inscription' => 'adm_creer_inscription', 'sondage' => 'adm_creer_sondage'] as $m => $cle) {
        echo '<a class="btn" href="?a=editeur&amp;nouveau=' . $m . '">' . e(t($cle)) . '</a> ';
    }
    $valides = array_keys(array_filter($formulaires, 'is_array'));
    if ($valides) {
        echo '<form method="get" style="display:inline-flex;gap:.3rem;align-items:center;margin-left:.4rem"><input type="hidden" name="a" value="editeur">'
            . '<select name="copie" aria-label="' . e(t('adm_creer_copie')) . '" style="width:auto">';
        foreach ($valides as $v) {
            echo '<option value="' . e($v) . '">' . e($v) . '</option>';
        }
        echo '</select><button class="sec">' . e(t('adm_creer_copie')) . '</button></form>';
    }
    echo '</div>';
}

/* ---------- Ajout / remplacement d'un formulaire ---------- */
if ($gestion) {
    echo '<h2>' . e(t('adm_ajouter')) . '</h2>';
    echo '<form method="post" enctype="multipart/form-data" class="champ">'
        . '<input type="hidden" name="_jeton" value="' . e($jetonAdmin) . '">'
        . '<input type="hidden" name="action" value="televerser">'
        . '<label class="lib" for="ini">' . e(t('adm_up_fichier')) . '</label>'
        . '<input type="file" id="ini" name="ini" accept=".ini,.txt,text/plain">'
        . '<label class="lib" for="nom" style="margin-top:.6rem">' . e(t('adm_up_nom_libelle')) . '</label>'
        . '<input type="text" id="nom" name="nom" pattern="[A-Za-z0-9][A-Za-z0-9_\-]*" placeholder="' . e(t('adm_up_nom_exemple')) . '">'
        . '<label class="opt"><input type="checkbox" name="remplacer" value="1"> <span>' . e(t('adm_up_remplacer')) . '</span></label>'
        . '<p class="petit">' . e(t('adm_up_aide')) . '</p>'
        . '<button>' . e(t('adm_up_bouton')) . '</button></form>';
}

$autres = [];
foreach (glob($dir . '/*.csv') ?: [] as $f) {
    if (!isset($fichiersVus[basename($f)])) {
        $autres[] = basename($f);
    }
}
if ($autres) {
    sort($autres);
    echo '<h2>' . e(t('adm_autres_fichiers')) . '</h2>';
    foreach ($autres as $n) {
        echo '<div class="champ">' . e($n) . ' — <a href="?a=telecharger&amp;fichier=' . e(rawurlencode($n)) . '">' . e(t('adm_telecharger')) . '</a> · '
            . '<a href="?a=voir&amp;fichier=' . e(rawurlencode($n)) . '">' . e(t('adm_voir')) . '</a>';
        if ($gestion) {
            echo ins_bouton_suppression('supprimer_csv', 'fichier', $n, t('adm_supprimer_csv'), $jetonAdmin);
        }
        echo '</div>';
    }
}
echo '<p class="petit">' . e(t('adm_format_csv', ['sep' => $cfg['separateur']])) . '</p>';
?>
<script>
document.querySelectorAll('button.copier').forEach(function (b) {
  b.addEventListener('click', function () {
    var champ = b.parentNode.querySelector('input'), txt = b.textContent;
    function fait() { b.textContent = <?= json_encode(t('adm_copie')) ?>; setTimeout(function () { b.textContent = txt; }, 1500); }
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(champ.value).then(fait);
    } else {
      champ.select(); try { document.execCommand('copy'); fait(); } catch (e) {}
    }
  });
});
</script>
<?php
ins_pied();
