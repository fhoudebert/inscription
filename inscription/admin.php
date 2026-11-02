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

/* ---------- Actions de gestion (POST) ---------- */
$message = '';
$alerte = '';
$detail = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
            header('Location: admin.php?' . http_build_query(['ok' => 'csv_supprime', 'id' => $n]), true, 303);
            exit;
        }
        throw new RuntimeException(t('introuvable'));
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
foreach ($formulaires as $id => $form) {
    echo '<div class="champ">';
    if (is_string($form)) {
        echo '<b>' . e($id) . '.ini</b><div class="alerte">' . e($form) . '</div>';
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
    echo '<b>' . e($form['titre']) . '</b> <span class="petit">(' . e($id) . '.ini)</span> '
        . ($form['diffusion']
            ? '<span class="etiq">' . e(t('adm_diffuse')) . '</span>'
            : '<span class="etiq prive">' . e(t('adm_non_diffuse')) . '</span>') . '<br>';
    echo e(t('adm_nb_reponses', ['n' => $nb])) . ($form['places'] ? ' / ' . e(t('adm_places', ['n' => $form['places']])) : '');
    echo ' · ' . ($etat === '' ? e(t('adm_ouvert')) : e($etat));
    if (ins_multilingue($form)) {
        $noms = array_map(function ($lg) { return ins_textes($lg)['nom_langue'] ?? $lg; }, $form['langues']);
        echo ' · ' . e(t('adm_langues', ['langues' => implode(', ', $noms)]));
    }
    echo '<div class="lien"><input type="text" readonly value="' . e($lien) . '" aria-label="' . e(t('adm_lien_partage')) . '">'
        . '<button type="button" class="sec copier">' . e(t('adm_copier')) . '</button>'
        . '<a class="btn sec" href="' . e($lien) . '" target="_blank" rel="noopener">' . e(t('adm_ouvrir')) . '</a></div>';
    if (!$form['diffusion']) {
        echo '<div class="petit">' . e(t('adm_lien_explication')) . '</div>';
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
