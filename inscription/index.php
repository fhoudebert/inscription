<?php
/*
 * Formulaire d'inscription générique — page publique.
 *   index.php                  → liste des formulaires diffusés (ou le formulaire, s'il n'y en a qu'un)
 *   index.php?f=<nom>          → formulaire diffusé décrit par formulaires/<nom>.ini
 *   index.php?f=<nom>&k=<clé>  → formulaire non diffusé, ouvert par son lien de partage
 *   index.php?f=<nom>&i=<id>   → formulaire à accès restreint, lien personnel (+ mot de passe,
 *                                ou &t=<jeton> pour le mode « lien seul »)
 *   &lang=en                   → choix de la langue (page d'accueil, formulaires multilingues)
 */
declare(strict_types=1);

define('INS_PRIVE', rtrim((string)require __DIR__ . '/emplacement.php', '/'));

try {
    if (!is_file(INS_PRIVE . '/lib.php')) {
        throw new RuntimeException('lib.php introuvable dans ' . INS_PRIVE . ' (voir emplacement.php)');
    }
    require INS_PRIVE . '/lib.php';
    $cfg = ins_config();
    if (isset($_GET['image']) && is_string($_GET['image'])) {
        ins_servir_image($_GET['image']); // logos rangés dans la zone privée
    }
} catch (Throwable $ex) {
    error_log('inscription : ' . $ex->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Formulaire indisponible : installation incomplète (voir emplacement.php et le dossier prive).\n"
        . "Form unavailable: incomplete installation (see emplacement.php and the prive folder).\n";
    exit;
}

function ins_erreur_page(int $code, string $titre, string $detail = ''): void
{
    http_response_code($code);
    ins_entete($titre);
    echo ins_tete($titre);
    if ($detail !== '' && ins_config()['debug']) {
        echo '<pre class="alerte">' . e($detail) . '</pre>';
    }
    ins_pied();
    exit;
}

$fid = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';
$cle = isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '';

/* ---------- Liste des formulaires diffusés ---------- */
if ($fid === '') {
    // Page d'accueil : langue du navigateur, ou celle choisie par lien, parmi les langues installées
    $lgAccueil = ins_langue_visiteur(ins_langues(), (string)$cfg['langue']);
    ins_langue($lgAccueil);
    $liste = ins_liste_formulaires($lgAccueil);
    if (count($liste) === 1) {
        $fid = (string)array_key_first($liste);
    } elseif (!$cfg['liste_publique']) {
        ins_erreur_page(404, t('introuvable'));
    } else {
        ins_entete(t('liste_titre'));
        echo ins_liens_langues(ins_langues(), $lgAccueil);
        echo ins_tete(t('liste_titre'));
        if (!$liste) {
            echo '<p>' . e(t('liste_vide')) . '</p>';
        } else {
            echo '<ul>';
            foreach ($liste as $id => $titre) {
                echo '<li><a href="?f=' . e(rawurlencode($id)) . '">' . e($titre) . '</a></li>';
            }
            echo '</ul>';
        }
        ins_pied();
        exit;
    }
}

/* ---------- Un formulaire ---------- */
try {
    $form = ins_charger_formulaire($fid);
} catch (Throwable $ex) {
    error_log('inscription : ' . $ex->getMessage());
    // Formulaire inexistant : même réponse qu'un formulaire non diffusé ouvert sans sa clé.
    $existe = ins_id_valide($fid) && is_file(ins_dossier_formulaires() . '/' . $fid . '.ini');
    ins_erreur_page(404, t($existe ? 'introuvable_ou_errone' : 'introuvable'), $ex->getMessage());
}
// Accès restreint : il faut un lien personnel (identifiant, + jeton en mode « lien seul »).
$paramsAcces = [];
if ($form['acces'] !== '') {
    $aid = isset($_GET['i']) && is_string($_GET['i']) ? strtoupper(trim($_GET['i'])) : '';
    $jetonLien = isset($_GET['t']) && is_string($_GET['t']) ? $_GET['t'] : '';
    if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $aid)) {
        ins_erreur_page(404, t('introuvable'));
    }
    $paramsAcces = ['i' => $aid] + ($form['acces'] === 'lien' ? ['t' => $jetonLien] : []);
} elseif (!ins_acces_autorise($form, $cle)) {
    // Non diffusé et sans clé de partage valide : on ne révèle pas son existence.
    ins_erreur_page(404, t('introuvable'));
}
// Langue d'affichage : fixe pour un formulaire unilingue ; pour un formulaire multilingue,
// lien « &lang= », sinon choix mémorisé, sinon langue du navigateur, sinon langue de base.
$formBase = $form;
$lg = ins_multilingue($form) ? ins_langue_visiteur($form['langues'], $form['langue']) : $form['langue'];
ins_langue($lg);
$form = ins_localiser($form, $lg);
$url = '?' . http_build_query(ins_params_formulaire($form, $lg) + $paramsAcces);
$liensLangues = ins_liens_langues($form['langues'], $lg, ins_params_formulaire($form) + $paramsAcces);

/* ---------- Accès restreint : vérification ---------- */
$acces = null;
$reponseExistante = null;
if ($form['acces'] !== '') {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookieAcces = ins_cookie_acces($fid);
    if ($form['acces'] === 'lien') {
        $acces = ins_verifier_lien($form, $aid, $jetonLien);
        if ($acces === null) {
            ins_erreur_page(404, t('introuvable'));
        }
    } else {
        if (isset($_GET['sortir'])) {
            setcookie($cookieAcces, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'secure' => $https, 'samesite' => 'Lax']);
            header('Location: ' . $url, true, 303);
            exit;
        }
        if (!isset(ins_lire_acces($form)[$aid])) {
            ins_erreur_page(404, t('introuvable')); // identifiant inconnu : on ne révèle rien
        }
        $acces = ins_acces_par_cookie($form, $aid);
        if ($acces === null) {
            $erreurConnexion = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_mdp']) && ($_POST['site_web'] ?? '') === '') {
                [$a, $code] = ins_verifier_mot_de_passe($form, $aid, is_string($_POST['_mdp']) ? $_POST['_mdp'] : '');
                if ($a !== null) {
                    setcookie($cookieAcces, ins_jeton_acces($fid, $a), ['expires' => time() + INS_ACCES_DUREE, 'path' => '/',
                        'httponly' => true, 'secure' => $https, 'samesite' => 'Lax']);
                    header('Location: ' . $url, true, 303);
                    exit;
                }
                sleep(1); // freine les essais en série
                $erreurConnexion = t($code, ['minutes' => (int)(INS_ACCES_BLOCAGE / 60)]);
            }
            ins_entete($form['titre']);
            echo $liensLangues;
            echo ins_tete($form['titre'], $form);
            echo '<div class="info">' . e(t('acces_connexion_intro')) . '</div>';
            if ($erreurConnexion !== '') {
                echo '<div class="alerte" role="alert">' . e($erreurConnexion) . '</div>';
            }
            echo '<form method="post" action="' . e($url) . '" class="champ">'
                . '<div class="pot" aria-hidden="true"><input type="text" name="site_web" tabindex="-1" autocomplete="off"></div>'
                . '<label class="lib" for="ident">' . e(t('acces_identifiant')) . '</label>'
                . '<input type="text" id="ident" value="' . e($aid) . '" readonly autocomplete="username">'
                . '<label class="lib" for="mdp" style="margin-top:.6rem">' . e(t('acces_mot_de_passe')) . '</label>'
                . '<input type="password" id="mdp" name="_mdp" autocomplete="current-password" autofocus required>'
                . '<p><button>' . e(t('acces_entrer')) . '</button></p></form>';
            ins_pied();
            exit;
        }
    }
    if ($acces['etat'] !== 'actif') {
        ins_erreur_page(403, t('acces_revoque'));
    }
    if ($acces['repondu'] !== '') {
        $reponseExistante = ins_reponse_acces($form, $aid);
        if (!$form['modifiable'] && !isset($_GET['merci'])) {
            ins_erreur_page(200, t('acces_deja_repondu'));
        }
    }
    // Nom, e-mail, téléphone donnés avec l'accès : champs pré-remplis et facultatifs pour cette personne
    $connus = ins_champs_connus($form, $acces);
    $form = ins_appliquer_acces($form, $connus);
}

$brut = [];
$erreurs = [];
$alerte = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motif = ins_verifier_jeton($fid, $_POST['_jeton'] ?? null);
    if (($_POST['site_web'] ?? '') !== '') {
        // Pot de miel rempli par un robot : on fait comme si de rien n'était.
        header('Location: ' . $url . '&merci=1', true, 303);
        exit;
    }
    $saisie = $_POST;
    foreach ($connus ?? [] as $champ => $v) {
        if (!isset($saisie[$champ]) || (is_string($saisie[$champ]) && trim($saisie[$champ]) === '')) {
            $saisie[$champ] = $v; // laissé vide : la valeur connue grâce à l'accès
        }
    }
    [$valeurs, $erreurs, $brut] = ins_valider($form, $saisie);
    $etat = ins_etat($form, $reponseExistante !== null);
    if ($etat !== '') {
        $alerte = $etat;
    } elseif ($motif === 'trop_rapide') {
        $alerte = t('envoi_trop_rapide');
    } elseif ($motif !== '') {
        $alerte = t('page_expiree');
    } elseif (!$erreurs) {
        try {
            [$numero, $erreurs, $alerte] = ins_enregistrer($form, $valeurs, $lg, $acces);
        } catch (Throwable $ex) {
            error_log('inscription : ' . $ex->getMessage());
            ins_erreur_page(500, t('echec_enregistrement'), $ex->getMessage());
        }
        if ($numero !== null) {
            ins_notifier($formBase, $numero, $valeurs, $acces); // libellés dans la langue de base
            $conf = ins_confirmer($form, $numero, $valeurs, $reponseExistante !== null) ? '1' : ''; // au répondant, dans sa langue
            $sig = ins_signer("merci|$fid|$numero|$conf");
            header('Location: ' . $url . '&merci=' . rawurlencode($numero) . ($conf ? '&conf=1' : '') . '&s=' . $sig, true, 303);
            exit;
        }
    }
    if ($erreurs && $alerte === '') {
        $alerte = t('erreurs_a_corriger');
    }
}

ins_entete($form['titre']);
echo $liensLangues;
echo ins_tete($form['titre'], $form);

/* ---------- Page de remerciement ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['merci'])) {
    $numero = is_string($_GET['merci']) ? $_GET['merci'] : '';
    $sig = isset($_GET['s']) && is_string($_GET['s']) ? $_GET['s'] : '';
    echo '<div class="ok"><p>' . ins_texte($form['merci'] !== '' ? $form['merci'] : t('merci_defaut')) . '</p>';
    $conf = isset($_GET['conf']) ? '1' : '';
    $sigOk = $numero !== '' && $sig !== '' && hash_equals(ins_signer("merci|$fid|$numero|$conf"), $sig);
    if ($sigOk && $conf) {
        echo '<p>' . e(t('conf_envoyee')) . '</p>';
    }
    if ($form['afficher_numero'] && $sigOk) {
        echo '<p>' . e(t('numero_reponse')) . ' <code>' . e($numero) . '</code><br>'
            . '<span class="petit">' . e(t('numero_conserver')) . '</span></p>';
    }
    echo '</div>';
    if ($acces === null) {
        echo '<p><a class="btn sec" href="' . e($url) . '">' . e(t('autre_reponse')) . '</a></p>';
    } elseif ($form['modifiable']) {
        echo '<p><a class="btn sec" href="' . e($url) . '">' . e(t('acces_modifier')) . '</a></p>'
            . '<p class="petit">' . e(t($form['acces'] === 'lien' ? 'acces_modifier_lien' : 'acces_modifier_mdp')) . '</p>';
    }
    ins_pied();
    exit;
}

/* ---------- Formulaire fermé ? ---------- */
$etat = ins_etat($form, $reponseExistante !== null);
if ($etat !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="info">' . e($etat) . '</div>';
    ins_pied();
    exit;
}

if ($acces !== null) {
    $qui = ins_nom_acces($acces) !== '' ? ins_nom_acces($acces) . ' (' . $acces['identifiant'] . ')' : $acces['identifiant'];
    echo '<p class="petit">' . e(t('acces_connecte', ['qui' => $qui]));
    if ($form['acces'] === 'personnel') {
        echo ' · <a href="' . e($url . '&sortir=1') . '">' . e(t('acces_sortir')) . '</a>';
    }
    echo '</p>';
    if ($reponseExistante !== null) {
        echo '<div class="ok">' . e(t('acces_deja_modifiable', ['date' => ins_date_lisible((int)strtotime($acces['modifie'] ?: $acces['repondu']))])) . '</div>';
    }
}
if ($form['intro'] !== '') {
    echo '<div class="info">' . ins_texte($form['intro']) . '</div>';
}
if ($alerte !== '') {
    echo '<div class="alerte" role="alert">' . e($alerte) . '</div>';
}

echo '<form method="post" action="' . e($url) . '" novalidate>';
echo '<input type="hidden" name="_jeton" value="' . e(ins_jeton($fid)) . '">';
echo '<div class="pot" aria-hidden="true"><label>' . e(t('pot_de_miel')) . ' <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></div>';

// Premier affichage : réponse précédente, sinon valeurs connues grâce à l'accès
echo ins_rendu_champs($form, $brut, $erreurs, !$_POST, $reponseExistante ?? (!empty($connus) ? $connus : null));

echo '<p class="petit"><span class="req">*</span> ' . e(t('champ_obligatoire')) . '</p>';
echo '<p><button type="submit">' . e($form['bouton'] !== '' ? $form['bouton'] : t('bouton_defaut')) . '</button></p></form>';
echo ins_script_conditions();
ins_pied();
