<?php
/*
 * Formulaire d'inscription générique — administration.
 * Consultation et téléchargement des inscriptions, protégés par mot de passe
 * (« admin_mot_de_passe » dans prive/config.php).
 */
declare(strict_types=1);

define('INS_PRIVE', rtrim((string)require __DIR__ . '/emplacement.php', '/'));
require INS_PRIVE . '/lib.php';
$cfg = ins_config();

header('Cache-Control: no-store');
$cookie = 'ins_admin';
$duree = 4 * 3600;
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$hash = (string)$cfg['admin_mot_de_passe'];

function ins_admin_connecte(string $cookie): bool
{
    $v = $_COOKIE[$cookie] ?? '';
    if (!is_string($v) || !preg_match('/^(\d+)\.([0-9a-f]{32})$/', $v, $m)) {
        return false;
    }
    return (int)$m[1] > time() && hash_equals(ins_signer('admin|' . $m[1] . '|' . (string)ins_config()['admin_mot_de_passe']), $m[2]);
}

function ins_poser_cookie(string $nom, string $valeur, int $expire, bool $https): void
{
    setcookie($nom, $valeur, ['expires' => $expire, 'path' => '/', 'httponly' => true, 'secure' => $https, 'samesite' => 'Strict']);
}

/* ---------- Aucun mot de passe configuré : aide à la création ---------- */
if ($hash === '') {
    ins_entete('Administration — mot de passe à définir');
    echo '<h1>Administration</h1><div class="info"><p>Aucun mot de passe d\'administration n\'est défini.</p>'
        . '<p>Choisissez-en un ci-dessous : la page affichera une ligne à copier dans <code>prive/config.php</code> '
        . '(le mot de passe lui-même n\'est enregistré nulle part).</p></div>';
    $mdp = $_POST['mdp'] ?? '';
    if (is_string($mdp) && $mdp !== '') {
        if (mb_strlen($mdp) < 10) {
            echo '<div class="alerte">Choisissez au moins 10 caractères.</div>';
        } else {
            $h = password_hash($mdp, PASSWORD_DEFAULT);
            echo '<div class="ok"><p>Dans <code>prive/config.php</code>, remplacez la ligne <code>\'admin_mot_de_passe\' =&gt; \'\',</code> par :</p>'
                . '<pre style="white-space:pre-wrap;word-break:break-all">' . e("'admin_mot_de_passe' => '" . $h . "',") . '</pre>'
                . '<p>puis renvoyez le fichier sur le serveur et rechargez cette page.</p></div>';
        }
    }
    echo '<form method="post"><div class="champ"><label class="lib" for="mdp">Mot de passe souhaité</label>'
        . '<input type="password" id="mdp" name="mdp" autocomplete="new-password"></div><button>Générer la ligne</button></form>';
    ins_pied();
    exit;
}

/* ---------- Connexion / déconnexion ---------- */
$action = isset($_GET['a']) && is_string($_GET['a']) ? $_GET['a'] : '';
if ($action === 'sortir') {
    ins_poser_cookie($cookie, '', time() - 3600, $https);
    header('Location: admin.php', true, 303);
    exit;
}
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
    ins_entete('Administration');
    echo '<h1>Administration des inscriptions</h1>';
    if (!empty($echec)) {
        echo '<div class="alerte">Mot de passe incorrect.</div>';
    }
    echo '<form method="post"><div class="champ"><label class="lib" for="mdp">Mot de passe</label>'
        . '<input type="password" id="mdp" name="mdp" autocomplete="current-password" autofocus></div>'
        . '<button>Se connecter</button></form>';
    ins_pied();
    exit;
}

/* ---------- Connecté ---------- */
$dir = ins_dossier_donnees();

/** Fichier de données demandé, uniquement parmi ceux du dossier des données. */
function ins_fichier_demande(string $dir): ?string
{
    $n = isset($_GET['fichier']) && is_string($_GET['fichier']) ? $_GET['fichier'] : '';
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,100}\.csv$/i', $n) || !is_file($dir . '/' . $n)) {
        return null;
    }
    return $n;
}

if ($action === 'telecharger') {
    $n = ins_fichier_demande($dir);
    if ($n === null) {
        http_response_code(404);
        exit('Fichier introuvable');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $n . '"');
    header('Content-Length: ' . filesize($dir . '/' . $n));
    readfile($dir . '/' . $n);
    exit;
}

if ($action === 'voir') {
    $n = ins_fichier_demande($dir);
    if ($n === null) {
        http_response_code(404);
        exit('Fichier introuvable');
    }
    $lignes = ins_lire_csv($dir . '/' . $n);
    ins_entete($n);
    echo '<script>document.querySelector("main").className="large"</script>';
    echo '<p><a href="admin.php">← retour</a> · <a href="?a=telecharger&amp;fichier=' . e(rawurlencode($n)) . '">télécharger</a></p>';
    echo '<h1>' . e($n) . ' — ' . max(0, count($lignes) - 1) . ' inscription(s)</h1>';
    if ($lignes) {
        echo '<div class="defile"><table><thead><tr>';
        foreach ($lignes[0] as $t) {
            echo '<th>' . e($t) . '</th>';
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
ins_entete('Administration des inscriptions');
echo '<p style="text-align:right"><a href="?a=sortir">Se déconnecter</a></p>';
echo '<h1>Inscriptions</h1>';

// Auto-test : la zone privée est-elle lisible depuis le web ?
$urlPublique = ins_url_si_public($dir);
if ($urlPublique !== null) {
    $temoin = '.temoin-' . substr(ins_signer('temoin'), 0, 12) . '.txt';
    @file_put_contents($dir . '/' . $temoin, 'TEMOIN-PRIVE');
    $u = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $urlPublique), '/') . '/' . $temoin;
    echo '<div id="test-prive" class="info">Vérification de la protection du dossier des données…</div>';
    echo '<script>fetch(' . json_encode($u) . ',{cache:"no-store"}).then(function(r){return r.ok?r.text():""})'
        . '.catch(function(){return ""}).then(function(t){var d=document.getElementById("test-prive");'
        . 'if(t.indexOf("TEMOIN-PRIVE")>=0){d.className="alerte";d.innerHTML="<b>Attention :</b> le dossier des données est lisible depuis le web ! '
        . 'Votre hébergeur ignore le fichier .htaccess. Déplacez le dossier « prive » hors de la partie publique (voir le README).";}'
        . 'else{d.className="ok";d.textContent="Dossier des données protégé : il n\'est pas accessible depuis le web.";}});</script>';
} else {
    echo '<div class="ok">Le dossier des données est hors de la partie publique du site.</div>';
}

$formulaires = [];
foreach (glob(rtrim((string)$cfg['dossier_formulaires'], '/') . '/*.ini') ?: [] as $f) {
    $id = basename($f, '.ini');
    try {
        $formulaires[$id] = ins_charger_formulaire($id);
    } catch (Throwable $ex) {
        $formulaires[$id] = $ex->getMessage();
    }
}

echo '<h2>Formulaires</h2>';
if (!$formulaires) {
    echo '<p>Aucun descripteur dans le dossier des formulaires.</p>';
}
$fichiersVus = [];
foreach ($formulaires as $id => $form) {
    echo '<div class="champ">';
    if (is_string($form)) {
        echo '<b>' . e($id) . '.ini</b><div class="alerte">' . e($form) . '</div></div>';
        continue;
    }
    $n = $form['fichier'] . '.csv';
    $fichiersVus[$n] = true;
    $nb = ins_nombre_inscrits($form);
    $etat = ins_etat($form);
    echo '<b>' . e($form['titre']) . '</b> <span class="petit">(' . e($id) . '.ini'
        . ($form['cache'] ? ', caché de la liste' : '') . ')</span><br>';
    echo '<a href="index.php?f=' . e(rawurlencode($id)) . '" target="_blank">ouvrir le formulaire</a> · ';
    echo $nb . ' inscription(s)' . ($form['places'] ? ' sur ' . $form['places'] . ' places' : '');
    echo ' · ' . ($etat === '' ? 'ouvert' : e($etat));
    if ($nb > 0 || is_file($dir . '/' . $n)) {
        echo '<br><a class="btn" href="?a=telecharger&amp;fichier=' . e(rawurlencode($n)) . '">Télécharger ' . e($n) . '</a> '
            . '<a class="btn sec" href="?a=voir&amp;fichier=' . e(rawurlencode($n)) . '">Voir</a>';
    }
    echo '</div>';
}

$autres = [];
foreach (glob($dir . '/*.csv') ?: [] as $f) {
    if (!isset($fichiersVus[basename($f)])) {
        $autres[] = basename($f);
    }
}
if ($autres) {
    sort($autres);
    echo '<h2>Autres fichiers (anciennes versions, formulaires retirés)</h2><ul>';
    foreach ($autres as $n) {
        echo '<li>' . e($n) . ' — <a href="?a=telecharger&amp;fichier=' . e(rawurlencode($n)) . '">télécharger</a> · '
            . '<a href="?a=voir&amp;fichier=' . e(rawurlencode($n)) . '">voir</a></li>';
    }
    echo '</ul>';
}
echo '<p class="petit">Fichiers CSV en UTF-8, séparateur « ' . e($cfg['separateur']) . ' » : ils s\'ouvrent directement dans Excel ou LibreOffice Calc.</p>';
ins_pied();
