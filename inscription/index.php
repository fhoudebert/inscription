<?php
/*
 * Formulaire d'inscription générique — page publique.
 *   index.php            → liste des formulaires (ou le formulaire, s'il n'y en a qu'un)
 *   index.php?f=<nom>    → le formulaire décrit par formulaires/<nom>.ini
 */
declare(strict_types=1);

define('INS_PRIVE', rtrim((string)require __DIR__ . '/emplacement.php', '/'));

try {
    if (!is_file(INS_PRIVE . '/lib.php')) {
        throw new RuntimeException('lib.php introuvable dans ' . INS_PRIVE . ' (voir emplacement.php)');
    }
    require INS_PRIVE . '/lib.php';
    $cfg = ins_config();
} catch (Throwable $ex) {
    error_log('inscription : ' . $ex->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Formulaire indisponible : installation incomplète (voir emplacement.php et le dossier prive).\n";
    exit;
}

function ins_erreur_page(int $code, string $titre, string $detail = ''): void
{
    http_response_code($code);
    ins_entete($titre);
    echo '<h1>' . e($titre) . '</h1>';
    if ($detail !== '' && ins_config()['debug']) {
        echo '<pre class="alerte">' . e($detail) . '</pre>';
    }
    ins_pied();
    exit;
}

$fid = isset($_GET['f']) && is_string($_GET['f']) ? $_GET['f'] : '';

/* ---------- Liste des formulaires ---------- */
if ($fid === '') {
    $liste = ins_liste_formulaires();
    if (count($liste) === 1) {
        $fid = (string)array_key_first($liste);
    } elseif (!$cfg['liste_publique']) {
        ins_erreur_page(404, 'Formulaire introuvable');
    } else {
        ins_entete('Inscriptions');
        echo '<h1>Inscriptions</h1>';
        if (!$liste) {
            echo '<p>Aucun formulaire disponible pour le moment.</p>';
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
    ins_erreur_page(404, 'Formulaire introuvable ou mal décrit', $ex->getMessage());
}
$url = '?f=' . rawurlencode($fid);

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
    [$valeurs, $erreurs, $brut] = ins_valider($form, $_POST);
    $etat = ins_etat($form);
    if ($etat !== '') {
        $alerte = $etat;
    } elseif ($motif === 'trop_rapide') {
        $alerte = 'Envoi trop rapide : vérifiez votre saisie puis renvoyez le formulaire.';
    } elseif ($motif !== '') {
        $alerte = 'La page a expiré : vérifiez votre saisie puis renvoyez le formulaire.';
    } elseif (!$erreurs) {
        try {
            [$numero, $erreurs, $alerte] = ins_enregistrer($form, $valeurs);
        } catch (Throwable $ex) {
            error_log('inscription : ' . $ex->getMessage());
            ins_erreur_page(500, "L'inscription n'a pas pu être enregistrée", $ex->getMessage());
        }
        if ($numero !== null) {
            ins_notifier($form, $numero, $valeurs);
            $sig = ins_signer("merci|$fid|$numero");
            header('Location: ' . $url . '&merci=' . rawurlencode($numero) . '&s=' . $sig, true, 303);
            exit;
        }
    }
    if ($erreurs && $alerte === '') {
        $alerte = 'Le formulaire contient des erreurs : merci de les corriger.';
    }
}

ins_entete($form['titre']);
echo '<h1>' . e($form['titre']) . '</h1>';

/* ---------- Page de remerciement ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['merci'])) {
    $numero = is_string($_GET['merci']) ? $_GET['merci'] : '';
    $sig = isset($_GET['s']) && is_string($_GET['s']) ? $_GET['s'] : '';
    echo '<div class="ok"><p>' . nl2br(e($form['merci'])) . '</p>';
    if ($form['afficher_numero'] && $numero !== '' && $sig !== '' && hash_equals(ins_signer("merci|$fid|$numero"), $sig)) {
        echo '<p>Numéro d\'inscription : <code>' . e($numero) . '</code><br>'
            . '<span class="petit">Notez-le : il vous sera demandé pour toute modification ou annulation.</span></p>';
    }
    echo '</div><p><a class="btn sec" href="' . e($url) . '">Faire une autre inscription</a></p>';
    ins_pied();
    exit;
}

/* ---------- Formulaire fermé ? ---------- */
$etat = ins_etat($form);
if ($etat !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="info">' . e($etat) . '</div>';
    ins_pied();
    exit;
}

if ($form['intro'] !== '') {
    echo '<div class="info">' . nl2br(e($form['intro'])) . '</div>';
}
if ($alerte !== '') {
    echo '<div class="alerte" role="alert">' . e($alerte) . '</div>';
}

echo '<form method="post" action="' . e($url) . '" novalidate>';
echo '<input type="hidden" name="_jeton" value="' . e(ins_jeton($fid)) . '">';
echo '<div class="pot" aria-hidden="true"><label>Ne pas remplir <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></div>';

$premier = !$_POST;
foreach ($form['champs'] as $nom => $c) {
    $si = $c['si'] ? ' data-si="' . e(json_encode($c['si'], JSON_UNESCAPED_UNICODE)) . '"' : '';

    if ($c['type'] === 'titre') {
        echo '<h2' . $si . '>' . e($c['libelle']) . '</h2>';
        continue;
    }
    if ($c['type'] === 'info') {
        echo '<div class="info"' . $si . '>' . nl2br(e($c['libelle'])) . '</div>';
        continue;
    }

    $b = $premier ? ($c['defaut'] !== '' ? $c['defaut'] : null) : ($brut[$nom] ?? null);
    $err = $erreurs[$nom] ?? '';
    $idh = 'c_' . $nom;
    $req = $c['obligatoire'] ? ' <span class="req" title="obligatoire">*</span>' : '';
    $aria = $err !== '' ? ' aria-invalid="true" aria-describedby="' . $idh . '_e"' : '';
    $groupe = in_array($c['type'], ['choix', 'cases', 'echelle'], true);

    echo '<div class="champ' . ($err !== '' ? ' erreur' : '') . '" data-nom="' . e($nom) . '" data-type="' . e($c['type']) . '"' . $si . '>';
    if ($c['type'] !== 'case') {
        echo $groupe
            ? '<fieldset style="border:0;margin:0;padding:0"><legend class="lib">' . e($c['libelle']) . $req . '</legend>'
            : '<label class="lib" for="' . $idh . '">' . e($c['libelle']) . $req . '</label>';
    }
    if ($c['aide'] !== '') {
        echo '<div class="aide">' . nl2br(e($c['aide'])) . '</div>';
    }

    $ph = $c['exemple'] !== '' ? ' placeholder="' . e($c['exemple']) . '"' : '';
    $val = is_string($b) ? $b : '';
    switch ($c['type']) {
        case 'texte_long':
            echo '<textarea id="' . $idh . '" name="' . e($nom) . '"' . $ph . $aria . '>' . e($val) . '</textarea>';
            break;
        case 'choix':
            foreach ($c['options'] as $i => $opt) {
                $ch = $val === $opt ? ' checked' : '';
                echo '<label class="opt"><input type="radio" name="' . e($nom) . '" value="' . e($opt) . '"' . $ch . '> <span>' . e($opt) . '</span></label>';
            }
            break;
        case 'echelle':
            echo '<div class="echelle"><div class="notes">';
            foreach ($c['options'] as $opt) {
                $ch = $val === $opt ? ' checked' : '';
                echo '<label class="opt"><input type="radio" name="' . e($nom) . '" value="' . e($opt) . '"' . $ch . '><span>' . e($opt) . '</span></label>';
            }
            echo '</div>';
            if ($c['gauche'] !== '' || $c['droite'] !== '') {
                echo '<div class="bouts"><span>' . e($c['gauche']) . '</span><span>' . e($c['droite']) . '</span></div>';
            }
            echo '</div>';
            break;
        case 'liste':
            echo '<select id="' . $idh . '" name="' . e($nom) . '"' . $aria . '><option value="">— choisir —</option>';
            foreach ($c['options'] as $opt) {
                echo '<option' . ($val === $opt ? ' selected' : '') . '>' . e($opt) . '</option>';
            }
            echo '</select>';
            break;
        case 'cases':
            $sel = is_array($b) ? $b : (is_string($b) && $b !== '' ? array_map('trim', explode('|', $b)) : []);
            foreach ($c['options'] as $opt) {
                $ch = in_array($opt, $sel, true) ? ' checked' : '';
                echo '<label class="opt"><input type="checkbox" name="' . e($nom) . '[]" value="' . e($opt) . '"' . $ch . '> <span>' . e($opt) . '</span></label>';
            }
            break;
        case 'case':
            $ch = ($premier ? ins_oui($val) : ($b !== null && $b !== '')) ? ' checked' : '';
            echo '<label class="opt lib"><input type="checkbox" id="' . $idh . '" name="' . e($nom) . '" value="oui"' . $ch . $aria . '> <span>' . e($c['libelle']) . $req . '</span></label>';
            break;
        default:
            $types = ['email' => 'email', 'telephone' => 'tel', 'nombre' => 'text', 'date' => 'date'];
            $t = $types[$c['type']] ?? 'text';
            $extra = $c['type'] === 'nombre' ? ' inputmode="decimal"' : '';
            if ($c['type'] === 'date') {
                $extra .= ($c['min'] !== null ? ' min="' . e($c['min']) . '"' : '') . ($c['max'] !== null ? ' max="' . e($c['max']) . '"' : '');
            }
            $auto = ['email' => ' autocomplete="email"', 'telephone' => ' autocomplete="tel"'][$c['type']] ?? '';
            echo '<input type="' . $t . '" id="' . $idh . '" name="' . e($nom) . '" value="' . e($val) . '"' . $ph . $extra . $auto . $aria . '>';
    }
    if ($groupe) {
        echo '</fieldset>';
    }
    if ($err !== '') {
        echo '<div class="msg" id="' . $idh . '_e">' . e($err) . '</div>';
    }
    echo '</div>';
}

echo '<p class="petit"><span class="req">*</span> champ obligatoire</p>';
echo '<p><button type="submit">' . e($form['bouton']) . '</button></p></form>';
?>
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
      if (type === 'case') res.push(el.checked ? 'oui' : 'non');
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
<?php
ins_pied();
