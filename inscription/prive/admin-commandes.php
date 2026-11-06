<?php
/*
 * Administration des commandes et des catalogues — inclus par admin.php (connecté).
 *   ?a=catalogue&nom=X        éditeur de catalogue (tableau : produit, unité, prix, pas, disponible)
 *   ?a=catalogue_csv&nom=X    téléchargement du catalogue
 *   ?a=commandes&id=F         commandes d'un formulaire + quantités à préparer par produit
 *   ?a=commande&id=F&n=NUM    préparation d'une commande : prix définitif, statut, message à copier
 *   ?a=commandes_csv&id=F     export des commandes (totaux estimés et définitifs)
 * Variables reçues d'admin.php : $action, $gestion, $jetonAdmin, $cfg.
 */
declare(strict_types=1);

if (!defined('INS_PRIVE') || !isset($jetonAdmin)) {
    http_response_code(403);
    exit;
}

/** Vérifie le jeton anti-CSRF et le droit de modifier (admin_gestion). */
function ins_cmd_verifier_post(string $jeton, bool $gestion): void
{
    if (!hash_equals($jeton, (string)($_POST['_jeton'] ?? ''))) {
        throw new RuntimeException(t('adm_jeton'));
    }
    if (!$gestion) {
        throw new RuntimeException(t('adm_gestion_off'));
    }
}

/** Formulaire (avec champ commande) demandé par ?id= ; sinon page introuvable. */
function ins_cmd_formulaire(): array
{
    $id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
    try {
        $form = ins_charger_formulaire($id);
    } catch (Throwable $ex) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    if (ins_champ_commande($form) === null) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    return $form;
}

/** Réponses du formulaire indexées par numéro (valeurs par colonne). */
function ins_cmd_reponses(array $form): array
{
    $res = [];
    $f = ins_fichier_donnees($form);
    if (!is_file($f)) {
        return $res;
    }
    $lignes = ins_lire_csv($f);
    $cols = $lignes[0] ?? [];
    foreach (array_slice($lignes, 1) as $l) {
        $r = [];
        foreach ($cols as $k => $c) {
            $r[$c] = ins_csv_brut((string)($l[$k] ?? ''));
        }
        if (($r['numero'] ?? '') !== '') {
            $res[$r['numero']] = $r;
        }
    }
    return $res;
}

/** Résumé du client : valeurs des deux premiers champs remplis (hors commande). */
function ins_cmd_client(array $form, array $rep): string
{
    $vals = [];
    foreach ($form['champs'] as $c) {
        if (in_array($c['type'], ['titre', 'info', 'commande'], true) || ($rep[$c['nom']] ?? '') === '') {
            continue;
        }
        $vals[] = $rep[$c['nom']];
        if (count($vals) === 2) {
            break;
        }
    }
    return implode(' · ', $vals);
}

/** Lignes définitives d'une commande (ou celles du client tant qu'elle n'est pas préparée). */
function ins_cmd_lignes(array $cmd): array
{
    return !empty($cmd['final']) ? ($cmd['final']['lignes'] ?? []) : $cmd['lignes'];
}

function ins_cmd_total(array $cmd): float
{
    return !empty($cmd['final']) ? (float)$cmd['final']['total'] : (float)$cmd['total'];
}

/** Message prêt à copier (SMS, e-mail) d'après le modèle « message » du formulaire. */
function ins_cmd_message(array $form, array $cmd, array $rep): string
{
    $avant = ins_langue();
    $lg = ($rep['langue'] ?? '') !== '' && in_array($rep['langue'], $form['langues'], true) ? $rep['langue'] : $form['langue'];
    ins_langue($lg);
    $f = ins_localiser($form, $lg);
    $modele = $f['message'] !== '' ? $f['message'] : t('cmd_message_defaut');
    $lignes = ins_cmd_lignes($cmd);
    $detail = ins_texte_lignes(array_values(array_filter($lignes, function ($l) { return (float)$l['qte'] > 0; })));
    if (!empty($cmd['final']['ajustement']['montant'])) {
        $a = $cmd['final']['ajustement'];
        $detail .= "\n" . t('cmd_ligne_ajustement', ['libelle' => $a['libelle'] !== '' ? $a['libelle'] : t('cmd_ajustement'), 'montant' => ins_prix((float)$a['montant'])]);
    }
    // Dans le message, une ligne par produit précédée d'un tiret (lisible dans un SMS comme dans un e-mail)
    $detail = $detail === '' ? '' : '- ' . str_replace("\n", "\n- ", $detail);
    $rempl = ['{numero}' => (string)($rep['numero'] ?? ''), '{date}' => ins_date_lisible((int)strtotime((string)($rep['date'] ?? 'now'))),
        '{detail}' => $detail, '{total}' => ins_prix(ins_cmd_total($cmd)), '{note}' => (string)($cmd['note'] ?? '')];
    foreach ($form['champs'] as $c) {
        if ($c['type'] !== 'commande') {
            $rempl['{' . $c['nom'] . '}'] = (string)($rep[$c['nom']] ?? '');
        }
    }
    $msg = strtr($modele, $rempl);
    ins_langue($avant);
    // lignes vides en trop (note absente…)
    return trim((string)preg_replace("/\n{3,}/", "\n\n", (string)preg_replace("/[ \t]+\n/", "\n", $msg)));
}

$pagesCommandes = ['catalogue', 'catalogue_csv', 'commandes', 'commande', 'commandes_csv'];
$actionsCommandes = ['catalogue_enregistrer', 'catalogue_importer', 'catalogue_nouveau', 'commande_enregistrer'];

/* ---------- Actions (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', $actionsCommandes, true)) {
    $act = (string)$_POST['action'];
    $retour = 'admin.php';
    try {
        ins_cmd_verifier_post($jetonAdmin, $gestion);
        if ($act === 'catalogue_nouveau' || $act === 'catalogue_importer') {
            $nom = trim((string)($_POST['nom'] ?? ''));
            if (!ins_id_valide($nom)) {
                throw new RuntimeException(t('adm_up_nom', ['nom' => $nom]));
            }
            $existe = is_file(ins_fichier_catalogue($nom));
            $produits = [['id' => '', 'categorie' => '', 'produit' => t('adm_cat_produit_exemple'), 'unite' => 'kg', 'prix' => 1.0, 'pas' => 0.0, 'disponible' => true]];
            if ($act === 'catalogue_importer') {
                $up = $_FILES['csv'] ?? null;
                if (!is_array($up) || ($up['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$up['tmp_name'])) {
                    throw new RuntimeException(t('adm_up_aucun'));
                }
                if ((int)$up['size'] > 1024 * 1024) {
                    throw new RuntimeException(t('adm_cat_trop_gros'));
                }
                $produits = ins_importer_catalogue((string)file_get_contents((string)$up['tmp_name']));
                if (!$produits) {
                    throw new RuntimeException(t('adm_cat_import_vide'));
                }
            }
            if ($existe && empty($_POST['remplacer'])) {
                throw new RuntimeException(t('adm_cat_existe', ['nom' => $nom]));
            }
            ins_ecrire_catalogue($nom, $produits);
            header('Location: admin.php?' . http_build_query(['a' => 'catalogue', 'nom' => $nom, 'ok' => $act === 'catalogue_importer' ? count($produits) : 0]), true, 303);
            exit;
        }
        if ($act === 'catalogue_enregistrer') {
            $nom = (string)($_POST['nom'] ?? '');
            if (!ins_id_valide($nom) || !is_file(ins_fichier_catalogue($nom))) {
                throw new RuntimeException(t('introuvable'));
            }
            $retour = 'admin.php?' . http_build_query(['a' => 'catalogue', 'nom' => $nom]);
            $produits = [];
            foreach (is_array($_POST['p'] ?? null) ? $_POST['p'] : [] as $p) {
                if (!is_array($p) || trim((string)($p['produit'] ?? '')) === '' || !empty($p['retirer'])) {
                    continue;
                }
                $prix = ins_lire_nombre($p['prix'] ?? '');
                $pas = trim((string)($p['pas'] ?? '')) === '' ? 0.0 : ins_lire_nombre($p['pas']);
                if ($prix === null || $prix < 0 || $pas === null || $pas < 0) {
                    throw new RuntimeException(t('adm_cat_nombre', ['produit' => (string)$p['produit']]));
                }
                $produits[] = ['id' => (string)($p['id'] ?? ''), 'categorie' => mb_substr(trim((string)($p['categorie'] ?? '')), 0, 60),
                    'produit' => mb_substr(trim((string)$p['produit']), 0, 120), 'unite' => mb_substr(trim((string)($p['unite'] ?? '')), 0, 40),
                    'prix' => $prix, 'pas' => $pas, 'disponible' => !empty($p['disponible'])];
            }
            ins_ecrire_catalogue($nom, $produits);
            header('Location: ' . $retour . '&ok=enregistre', true, 303);
            exit;
        }
        if ($act === 'commande_enregistrer') {
            $id = (string)($_POST['id'] ?? '');
            $num = (string)($_POST['n'] ?? '');
            $form = ins_charger_formulaire($id);
            $retour = 'admin.php?' . http_build_query(['a' => 'commande', 'id' => $id, 'n' => $num]);
            $verrou = ins_verrouiller();
            try {
                $toutes = ins_lire_commandes($form);
                if (!isset($toutes[$num])) {
                    throw new RuntimeException(t('introuvable'));
                }
                $cmd = $toutes[$num];
                if (!empty($_POST['repartir'])) {
                    $cmd['final'] = null; // repartir de la commande du client
                } else {
                    $lignes = [];
                    foreach (is_array($_POST['l'] ?? null) ? $_POST['l'] : [] as $l) {
                        if (!is_array($l) || trim((string)($l['produit'] ?? '')) === '') {
                            continue;
                        }
                        $q = ins_lire_nombre($l['qte'] ?? '0');
                        $px = ins_lire_nombre($l['prix'] ?? '0');
                        if ($q === null || $q < 0 || $px === null || $px < 0) {
                            throw new RuntimeException(t('adm_cat_nombre', ['produit' => (string)$l['produit']]));
                        }
                        $lignes[] = ['id' => (string)($l['id'] ?? ''), 'produit' => mb_substr((string)$l['produit'], 0, 120),
                            'unite' => mb_substr((string)($l['unite'] ?? ''), 0, 40), 'prix' => round($px, 2), 'qte' => round($q, 3)];
                    }
                    $aj = ins_lire_nombre($_POST['ajust_montant'] ?? '') ?? 0.0;
                    $total = round(ins_total_lignes($lignes) + $aj, 2);
                    $cmd['final'] = ['lignes' => $lignes, 'ajustement' => ['libelle' => mb_substr(trim((string)($_POST['ajust_libelle'] ?? '')), 0, 80), 'montant' => round($aj, 2)],
                        'total' => $total, 'date' => date('Y-m-d H:i:s')];
                }
                $statut = (string)($_POST['statut'] ?? 'nouvelle');
                $cmd['statut'] = in_array($statut, INS_STATUTS_COMMANDE, true) ? $statut : 'nouvelle';
                $cmd['note'] = mb_substr(trim(str_replace("\r\n", "\n", (string)($_POST['note'] ?? ''))), 0, 1000);
                $cmd['modifiee'] = false;
                $toutes[$num] = $cmd;
                ins_ecrire_commandes($form, $toutes);
            } finally {
                ins_deverrouiller($verrou);
            }
            header('Location: ' . $retour . '&ok=enregistre', true, 303);
            exit;
        }
    } catch (Throwable $ex) {
        $erreurCommandes = $ex->getMessage();
        if ($act === 'catalogue_nouveau' || $act === 'catalogue_importer') {
            $alerteCatalogue = $erreurCommandes; // affichée au tableau de bord
            return;
        }
    }
}

if (!in_array($action, $pagesCommandes, true) && !isset($erreurCommandes)) {
    return; // rien à faire ici : admin.php continue
}

/* ---------- Téléchargements ---------- */
if ($action === 'catalogue_csv') {
    $nom = isset($_GET['nom']) && is_string($_GET['nom']) ? $_GET['nom'] : '';
    if (!ins_id_valide($nom) || !is_file(ins_fichier_catalogue($nom))) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="catalogue-' . $nom . '.csv"');
    readfile(ins_fichier_catalogue($nom));
    exit;
}
if ($action === 'commandes_csv') {
    $form = ins_cmd_formulaire();
    $reps = ins_cmd_reponses($form);
    $sep = (string)$cfg['separateur'];
    $h = fopen('php://temp', 'w+b');
    fwrite($h, "\xEF\xBB\xBF");
    $champs = array_filter($form['champs'], function ($c) { return !in_array($c['type'], ['titre', 'info', 'commande'], true); });
    fputcsv($h, array_merge(['numero', 'date', 'statut'], array_keys($champs), ['detail', 'total_estime', 'total_definitif']), $sep, '"', '');
    foreach (ins_lire_commandes($form) as $num => $cmd) {
        $rep = $reps[$num] ?? [];
        $l = [$num, (string)($rep['date'] ?? $cmd['date']), t('cmd_statut_' . $cmd['statut'])];
        foreach ($champs as $nom => $c) {
            $l[] = ins_csv_sur((string)($rep[$nom] ?? ''));
        }
        $detail = ins_texte_lignes(ins_cmd_lignes($cmd));
        if (!empty($cmd['final']['ajustement']['montant'])) {
            $a = $cmd['final']['ajustement'];
            $detail .= "\n" . t('cmd_ligne_ajustement', ['libelle' => $a['libelle'] !== '' ? $a['libelle'] : t('cmd_ajustement'), 'montant' => ins_prix((float)$a['montant'])]);
        }
        $l[] = ins_csv_sur($detail);
        $l[] = ins_nombre_csv((float)$cmd['total']);
        $l[] = !empty($cmd['final']) ? ins_nombre_csv((float)$cmd['final']['total']) : '';
        fputcsv($h, $l, $sep, '"', '');
    }
    rewind($h);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commandes-' . $form['id'] . '-' . date('Ymd') . '.csv"');
    fpassthru($h);
    exit;
}

$okMsg = isset($_GET['ok']) ? (string)$_GET['ok'] : '';

/* ---------- Éditeur de catalogue ---------- */
if ($action === 'catalogue') {
    $nom = isset($_GET['nom']) && is_string($_GET['nom']) ? $_GET['nom'] : '';
    if (!ins_id_valide($nom) || !is_file(ins_fichier_catalogue($nom))) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    $produits = array_values(ins_lire_catalogue($nom));
    $utilise = [];
    foreach (glob(ins_dossier_formulaires() . '/*.ini') ?: [] as $f) {
        try {
            $fo = ins_charger_formulaire(basename($f, '.ini'));
            $c = ins_champ_commande($fo);
            if ($c && $c['catalogue'] === $nom) {
                $utilise[] = $fo['id'];
            }
        } catch (Throwable $ex) {
        }
    }
    ins_entete(t('adm_cat_titre', ['nom' => $nom]));
    echo '<script>document.querySelector("main").className="large"</script>';
    echo '<p><a href="admin.php">← ' . e(t('edi_retour')) . '</a> · <a href="?a=catalogue_csv&amp;nom=' . e(rawurlencode($nom)) . '">' . e(t('adm_cat_telecharger')) . '</a></p>';
    echo '<h1>' . e(t('adm_cat_titre', ['nom' => $nom])) . '</h1>';
    if ($okMsg === 'enregistre') {
        echo '<div class="ok">' . e(t('adm_cat_ok')) . '</div>';
    } elseif ((int)$okMsg > 0) {
        echo '<div class="ok">' . e(t('adm_cat_ok_import', ['n' => (int)$okMsg])) . '</div>';
    }
    if (isset($erreurCommandes)) {
        echo '<div class="alerte">' . e($erreurCommandes) . '</div>';
    }
    echo '<p class="petit">' . e($utilise ? t('adm_cat_utilise', ['liste' => implode(', ', $utilise)]) : t('adm_cat_non_utilise')) . ' ' . e(t('adm_cat_aide')) . '</p>';
    echo '<form method="post" id="catalogue"><input type="hidden" name="_jeton" value="' . e($jetonAdmin) . '">'
        . '<input type="hidden" name="action" value="catalogue_enregistrer"><input type="hidden" name="nom" value="' . e($nom) . '">';
    echo '<p><button type="button" class="sec" data-tout="1">' . e(t('adm_cat_tout_dispo')) . '</button> '
        . '<button type="button" class="sec" data-tout="0">' . e(t('adm_cat_rien_dispo')) . '</button> '
        . '<input type="search" id="filtre" placeholder="' . e(t('adm_cat_filtrer')) . '" style="width:14rem;display:inline-block"></p>';
    echo '<div class="defile"><table class="catalogue"><thead><tr><th>' . e(t('adm_cat_dispo')) . '</th><th>' . e(t('adm_cat_categorie')) . '</th><th>'
        . e(t('cmd_produit')) . '</th><th>' . e(t('adm_cat_unite')) . '</th><th>' . e(t('cmd_prix')) . ' (' . e((string)$cfg['devise']) . ')</th><th>'
        . e(t('adm_cat_pas')) . '</th><th></th></tr></thead><tbody id="lignes">';
    $ligneCat = function ($i, array $p) {
        return '<tr' . (empty($p['disponible']) ? ' class="indispo"' : '') . '><td><input type="hidden" name="p[' . $i . '][id]" value="' . e($p['id']) . '">'
            . '<input type="checkbox" class="dispo" name="p[' . $i . '][disponible]" value="1"' . (!empty($p['disponible']) ? ' checked' : '') . ' aria-label="' . e(t('adm_cat_dispo')) . '"></td>'
            . '<td><input type="text" name="p[' . $i . '][categorie]" value="' . e($p['categorie']) . '" list="categories"></td>'
            . '<td><input type="text" name="p[' . $i . '][produit]" value="' . e($p['produit']) . '" required></td>'
            . '<td><input type="text" name="p[' . $i . '][unite]" value="' . e($p['unite']) . '" list="unites" style="width:8rem"></td>'
            . '<td><input type="text" inputmode="decimal" name="p[' . $i . '][prix]" value="' . e(ins_quantite((float)$p['prix'])) . '" style="width:6rem;text-align:right"></td>'
            . '<td><input type="text" inputmode="decimal" name="p[' . $i . '][pas]" value="' . e($p['pas'] > 0 ? ins_quantite((float)$p['pas']) : '') . '" style="width:5rem;text-align:right" placeholder="—"></td>'
            . '<td><label class="opt petit"><input type="checkbox" name="p[' . $i . '][retirer]" value="1"> ' . e(t('adm_cat_retirer')) . '</label></td></tr>';
    };
    foreach ($produits as $i => $p) {
        echo $ligneCat($i, $p);
    }
    echo '</tbody></table></div>';
    $cats = array_values(array_unique(array_filter(array_column($produits, 'categorie'))));
    $unites = array_values(array_unique(array_merge(['kg', 'pièce', 'botte', 'barquette'], array_filter(array_column($produits, 'unite')))));
    echo '<datalist id="categories">' . implode('', array_map(function ($c) { return '<option value="' . e($c) . '">'; }, $cats)) . '</datalist>';
    echo '<datalist id="unites">' . implode('', array_map(function ($c) { return '<option value="' . e($c) . '">'; }, $unites)) . '</datalist>';
    echo '<template id="modele-ligne">' . $ligneCat('__I__', ['id' => '', 'categorie' => '', 'produit' => '', 'unite' => '', 'prix' => 0, 'pas' => 0, 'disponible' => true]) . '</template>';
    echo '<p><button type="button" class="sec" id="ajouter">+ ' . e(t('adm_cat_ajouter')) . '</button></p>';
    echo '<p class="petit">' . e(t('adm_cat_pas_aide')) . '</p>';
    echo '<p><button>' . e(t('edi_enregistrer')) . '</button></p></form>';
    echo '<details class="champ"><summary>' . e(t('adm_cat_importer_ici')) . '</summary><form method="post" enctype="multipart/form-data">'
        . '<input type="hidden" name="_jeton" value="' . e($jetonAdmin) . '"><input type="hidden" name="action" value="catalogue_importer">'
        . '<input type="hidden" name="nom" value="' . e($nom) . '"><input type="hidden" name="remplacer" value="1">'
        . '<p class="petit">' . e(t('adm_cat_import_aide')) . '</p><input type="file" name="csv" accept=".csv,text/csv,text/plain"> '
        . '<button class="dang">' . e(t('adm_cat_importer_remplacer')) . '</button></form></details>';
    ?>
<style>table.catalogue input[type=text]{padding:.25rem .4rem}table.catalogue tr.indispo td{background:#f6f6f6;color:#888}table.catalogue tr.cache{display:none}</style>
<script>
(function () {
  var corps = document.getElementById('lignes'), n = corps.rows.length;
  function maj(tr) { tr.classList.toggle('indispo', !tr.querySelector('.dispo').checked); }
  corps.addEventListener('change', function (ev) { if (ev.target.classList.contains('dispo')) maj(ev.target.closest('tr')); });
  document.getElementById('ajouter').addEventListener('click', function () {
    var t = document.getElementById('modele-ligne').innerHTML.split('__I__').join(String(n++));
    corps.insertAdjacentHTML('beforeend', t);
    corps.lastElementChild.querySelector('input[name$="[produit]"]').focus();
  });
  document.querySelectorAll('[data-tout]').forEach(function (b) {
    b.addEventListener('click', function () {
      corps.querySelectorAll('tr:not(.cache) .dispo').forEach(function (c) { c.checked = b.getAttribute('data-tout') === '1'; maj(c.closest('tr')); });
    });
  });
  document.getElementById('filtre').addEventListener('input', function () {
    var f = this.value.trim().toLowerCase();
    Array.prototype.forEach.call(corps.rows, function (tr) {
      var txt = Array.prototype.map.call(tr.querySelectorAll('input[type=text]'), function (i) { return i.value; }).join(' ').toLowerCase();
      tr.classList.toggle('cache', f !== '' && txt.indexOf(f) < 0);
    });
  });
})();
</script>
<?php
    ins_pied();
    exit;
}

/* ---------- Liste des commandes d'un formulaire ---------- */
if ($action === 'commandes') {
    $form = ins_cmd_formulaire();
    $reps = ins_cmd_reponses($form);
    $toutes = ins_lire_commandes($form);
    $filtre = isset($_GET['statut']) && is_string($_GET['statut']) ? $_GET['statut'] : 'encours';
    $choisies = array_filter($toutes, function ($c) use ($filtre) {
        return $filtre === 'toutes' ? true : ($filtre === 'encours' ? in_array($c['statut'], ['nouvelle', 'preparee'], true) : $c['statut'] === $filtre);
    });
    uasort($choisies, function ($a, $b) { return strcmp((string)$a['date'], (string)$b['date']); });
    ins_entete(t('adm_cmd_titre', ['titre' => $form['titre']]));
    echo '<script>document.querySelector("main").className="large"</script>';
    echo '<p><a href="admin.php">← ' . e(t('edi_retour')) . '</a> · <a href="?a=commandes_csv&amp;id=' . e(rawurlencode($form['id'])) . '">' . e(t('adm_cmd_exporter')) . '</a>'
        . ' · <a href="?a=catalogue&amp;nom=' . e(rawurlencode(ins_champ_commande($form)['catalogue'])) . '">' . e(t('adm_cmd_catalogue')) . '</a></p>';
    echo '<h1>' . e(t('adm_cmd_titre', ['titre' => $form['titre']])) . '</h1>';
    $filtres = ['encours' => t('adm_cmd_encours'), 'nouvelle' => t('cmd_statut_nouvelle'), 'preparee' => t('cmd_statut_preparee'),
        'remise' => t('cmd_statut_remise'), 'annulee' => t('cmd_statut_annulee'), 'toutes' => t('adm_cmd_toutes')];
    $liens = [];
    foreach ($filtres as $k => $lib) {
        $nb = count(array_filter($toutes, function ($c) use ($k) {
            return $k === 'toutes' ? true : ($k === 'encours' ? in_array($c['statut'], ['nouvelle', 'preparee'], true) : $c['statut'] === $k);
        }));
        $liens[] = $k === $filtre ? '<b>' . e($lib) . ' (' . $nb . ')</b>' : '<a href="?' . e(http_build_query(['a' => 'commandes', 'id' => $form['id'], 'statut' => $k])) . '">' . e($lib) . ' (' . $nb . ')</a>';
    }
    echo '<p>' . implode(' · ', $liens) . '</p>';
    if (!$choisies) {
        echo '<p>' . e(t('adm_cmd_aucune')) . '</p>';
    } else {
        $somme = 0.0;
        echo '<div class="defile"><table><thead><tr><th>' . e(t('adm_cmd_numero')) . '</th><th>' . e(t('adm_cmd_date')) . '</th><th>' . e(t('adm_cmd_client')) . '</th><th>'
            . e(t('adm_cmd_articles')) . '</th><th class="nb">' . e(t('adm_cmd_total')) . '</th><th>' . e(t('adm_cmd_statut')) . '</th><th></th></tr></thead><tbody>';
        foreach ($choisies as $num => $cmd) {
            $rep = $reps[$num] ?? [];
            $somme += ins_cmd_total($cmd);
            echo '<tr><td><code>' . e($num) . '</code></td><td>' . e(ins_date_lisible((int)strtotime((string)($rep['date'] ?? $cmd['date'])))) . '</td><td>'
                . e(ins_cmd_client($form, $rep)) . '</td><td>' . count(ins_cmd_lignes($cmd)) . '</td><td class="nb">' . e(ins_prix(ins_cmd_total($cmd)))
                . (!empty($cmd['final']) ? '' : ' <span class="petit">(' . e(t('adm_cmd_estime')) . ')</span>') . '</td><td><span class="etiq statut-' . e($cmd['statut']) . '">'
                . e(t('cmd_statut_' . $cmd['statut'])) . '</span>' . (!empty($cmd['modifiee']) ? ' <span class="etiq prive">' . e(t('adm_cmd_modifiee_court')) . '</span>' : '')
                . '</td><td><a class="btn" href="?' . e(http_build_query(['a' => 'commande', 'id' => $form['id'], 'n' => $num])) . '">' . e(t('adm_cmd_preparer')) . '</a></td></tr>';
        }
        echo '</tbody><tfoot><tr><th colspan="4" class="nb">' . e(t('adm_cmd_somme')) . '</th><th class="nb">' . e(ins_prix($somme)) . '</th><th colspan="2"></th></tr></tfoot></table></div>';

        // Bilan : quantités à préparer par produit (hors commandes annulées)
        $bilan = [];
        foreach ($choisies as $cmd) {
            if ($cmd['statut'] === 'annulee') {
                continue;
            }
            foreach (ins_cmd_lignes($cmd) as $l) {
                $k = $l['produit'] . '|' . $l['unite'];
                $bilan[$k] = $bilan[$k] ?? ['produit' => $l['produit'], 'unite' => $l['unite'], 'qte' => 0.0, 'n' => 0];
                $bilan[$k]['qte'] += (float)$l['qte'];
                $bilan[$k]['n']++;
            }
        }
        uasort($bilan, function ($a, $b) { return strcasecmp($a['produit'] . $a['unite'], $b['produit'] . $b['unite']); });
        echo '<h2>' . e(t('adm_cmd_bilan')) . '</h2><p class="petit">' . e(t('adm_cmd_bilan_aide')) . '</p>';
        echo '<div class="defile"><table style="width:auto"><thead><tr><th>' . e(t('cmd_produit')) . '</th><th class="nb">' . e(t('cmd_quantite')) . '</th><th>'
            . e(t('adm_cat_unite')) . '</th><th class="nb">' . e(t('adm_cmd_nb_commandes')) . '</th></tr></thead><tbody>';
        foreach ($bilan as $b) {
            echo '<tr><td>' . e($b['produit']) . '</td><td class="nb"><b>' . e(ins_quantite($b['qte'])) . '</b></td><td>' . e($b['unite']) . '</td><td class="nb">' . $b['n'] . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '<style>td.nb,th.nb{text-align:right;white-space:nowrap}.statut-preparee{background:#e8f5e9}.statut-remise{background:#d0e8ff}.statut-annulee{background:#eee;color:#777}</style>';
    ins_pied();
    exit;
}

/* ---------- Préparation d'une commande ---------- */
if ($action === 'commande') {
    $form = ins_cmd_formulaire();
    $num = isset($_GET['n']) && is_string($_GET['n']) ? $_GET['n'] : '';
    $toutes = ins_lire_commandes($form);
    if (!isset($toutes[$num])) {
        http_response_code(404);
        exit(t('introuvable'));
    }
    $cmd = $toutes[$num];
    $rep = ins_cmd_reponses($form)[$num] ?? ['numero' => $num, 'date' => $cmd['date']];
    $catalogue = ins_lire_catalogue(ins_champ_commande($form)['catalogue']);
    $commandees = [];
    foreach ($cmd['lignes'] as $l) {
        $commandees[$l['produit'] . '|' . $l['unite']] = (float)$l['qte'];
    }
    $lignes = !empty($cmd['final']) ? $cmd['final']['lignes'] : $cmd['lignes'];
    $fmt = ['decimal' => t('nombre_decimal'), 'milliers' => t('nombre_milliers'), 'format' => t('format_prix'), 'devise' => (string)$cfg['devise']];

    ins_entete(t('adm_cmd_fiche', ['numero' => $num]));
    echo '<p><a href="?' . e(http_build_query(['a' => 'commandes', 'id' => $form['id']])) . '">← ' . e(t('adm_cmd_titre', ['titre' => $form['titre']])) . '</a></p>';
    echo '<h1>' . e(t('adm_cmd_fiche', ['numero' => $num])) . '</h1>';
    if ($okMsg === 'enregistre') {
        echo '<div class="ok">' . e(t('adm_cmd_ok')) . '</div>';
    }
    if (isset($erreurCommandes)) {
        echo '<div class="alerte">' . e($erreurCommandes) . '</div>';
    }
    if (!empty($cmd['modifiee'])) {
        echo '<div class="alerte">' . e(t('adm_cmd_modifiee')) . '</div>';
    }

    // Client
    echo '<div class="champ"><table class="fiche" style="width:auto">';
    echo '<tr><th>' . e(t('adm_cmd_date')) . '</th><td>' . e(ins_date_lisible((int)strtotime((string)$rep['date']))) . '</td></tr>';
    foreach ($form['champs'] as $c) {
        if (in_array($c['type'], ['titre', 'info', 'commande'], true) || ($rep[$c['nom']] ?? '') === '') {
            continue;
        }
        echo '<tr><th>' . e($c['libelle']) . '</th><td>' . nl2br(e($rep[$c['nom']])) . '</td></tr>';
    }
    echo '</table></div>';

    // Préparation : quantités servies et prix
    echo '<form method="post" class="champ" id="preparation"><input type="hidden" name="_jeton" value="' . e($jetonAdmin) . '">'
        . '<input type="hidden" name="action" value="commande_enregistrer"><input type="hidden" name="id" value="' . e($form['id']) . '"><input type="hidden" name="n" value="' . e($num) . '">';
    echo '<h2 style="margin-top:0">' . e(t('adm_cmd_preparation')) . '</h2><p class="petit">' . e(t('adm_cmd_preparation_aide')) . '</p>';
    echo '<div class="defile-x"><table class="cmd" data-format="' . e(json_encode($fmt, JSON_UNESCAPED_UNICODE)) . '"><thead><tr><th>' . e(t('cmd_produit')) . '</th><th class="nb">'
        . e(t('adm_cmd_commande')) . '</th><th class="nb">' . e(t('adm_cmd_servi')) . '</th><th class="nb">' . e(t('adm_cmd_prix_unitaire')) . '</th><th class="nb">' . e(t('cmd_montant')) . '</th></tr></thead><tbody id="lignes">';
    $ligneCmd = function ($i, array $l, ?float $demande) {
        return '<tr><td><input type="hidden" name="l[' . $i . '][id]" value="' . e($l['id'] ?? '') . '"><input type="hidden" name="l[' . $i . '][produit]" value="' . e($l['produit']) . '">'
            . '<input type="hidden" name="l[' . $i . '][unite]" value="' . e($l['unite']) . '">' . e($l['produit']) . ' <span class="petit">(' . e($l['unite']) . ')</span></td>'
            . '<td class="nb">' . ($demande !== null ? e(ins_quantite($demande)) : '—') . '</td>'
            . '<td class="nb"><input type="text" inputmode="decimal" class="cmd-q" name="l[' . $i . '][qte]" value="' . e(ins_quantite((float)$l['qte'])) . '" style="width:5.5rem;text-align:right"></td>'
            . '<td class="nb"><input type="text" inputmode="decimal" class="cmd-p" name="l[' . $i . '][prix]" value="' . e(ins_quantite((float)$l['prix'])) . '" style="width:5.5rem;text-align:right"></td>'
            . '<td class="nb cmd-st"></td></tr>';
    };
    foreach (array_values($lignes) as $i => $l) {
        echo $ligneCmd($i, $l, $commandees[$l['produit'] . '|' . $l['unite']] ?? null);
    }
    $aj = $cmd['final']['ajustement'] ?? ['libelle' => '', 'montant' => 0];
    echo '</tbody><tbody><tr><td colspan="4"><input type="text" name="ajust_libelle" value="' . e($aj['libelle']) . '" placeholder="' . e(t('adm_cmd_ajustement_ex')) . '"></td>'
        . '<td class="nb"><input type="text" inputmode="decimal" class="cmd-aj" name="ajust_montant" value="' . e((float)$aj['montant'] != 0 ? ins_quantite((float)$aj['montant']) : '') . '" placeholder="0" style="width:5.5rem;text-align:right"></td></tr></tbody>'
        . '<tfoot><tr><th colspan="4" class="nb">' . e(t('adm_cmd_total_definitif')) . '</th><th class="nb cmd-total"></th></tr></tfoot></table></div>';
    // Ajouter un produit du catalogue (remplacement, oubli…)
    echo '<p><select id="ajout-produit" style="width:auto"><option value="">' . e(t('adm_cmd_ajouter_produit')) . '</option>';
    foreach ($catalogue as $p) {
        echo '<option value="' . e(json_encode(['id' => $p['id'], 'produit' => $p['produit'], 'unite' => $p['unite'], 'prix' => $p['prix']], JSON_UNESCAPED_UNICODE)) . '">'
            . e($p['produit'] . ' (' . $p['unite'] . ') — ' . ins_prix($p['prix'])) . ($p['disponible'] ? '' : ' · ' . e(t('adm_cat_indispo_court'))) . '</option>';
    }
    echo '</select></p>';
    echo '<template id="modele-ligne">' . $ligneCmd('__I__', ['id' => '__ID__', 'produit' => '__P__', 'unite' => '__U__', 'prix' => 0, 'qte' => 1], null) . '</template>';
    echo '<div class="edi-deux" style="display:grid;grid-template-columns:1fr 2fr;gap:1rem"><div><label class="lib" for="statut">' . e(t('adm_cmd_statut')) . '</label><select id="statut" name="statut">';
    foreach (INS_STATUTS_COMMANDE as $s) {
        echo '<option value="' . $s . '"' . ($cmd['statut'] === $s ? ' selected' : '') . '>' . e(t('cmd_statut_' . $s)) . '</option>';
    }
    echo '</select></div><div><label class="lib" for="note">' . e(t('adm_cmd_note')) . '</label><textarea id="note" name="note" rows="2" placeholder="' . e(t('adm_cmd_note_ex')) . '">'
        . e((string)($cmd['note'] ?? '')) . '</textarea></div></div>';
    echo '<p><button>' . e(t('adm_cmd_enregistrer')) . '</button>';
    if (!empty($cmd['final'])) {
        echo ' <button class="sec" name="repartir" value="1">' . e(t('adm_cmd_repartir')) . '</button>';
    }
    echo '</p></form>';

    // Message prêt à copier
    $message = ins_cmd_message($form, $cmd, $rep);
    echo '<div class="champ"><h2 style="margin-top:0">' . e(t('adm_cmd_message')) . '</h2><p class="petit">' . e(t('adm_cmd_message_aide')) . '</p>'
        . '<textarea id="message" rows="' . min(18, 3 + substr_count($message, "\n")) . '">' . e($message) . '</textarea>'
        . '<p><button type="button" id="copier">' . e(t('adm_copier')) . '</button> <span id="copie" class="petit"></span></p></div>';
    ?>
<style>td.nb,th.nb{text-align:right;white-space:nowrap}table.fiche th{position:static;text-align:left;vertical-align:top}</style>
<script>
(function () {
  var table = document.querySelector('#preparation table.cmd'), corps = document.getElementById('lignes'), n = corps.rows.length;
  var f = JSON.parse(table.getAttribute('data-format'));
  function nb(v) { var x = parseFloat(String(v).replace(/\s/g, '').replace(',', '.')); return isNaN(x) ? 0 : x; }
  function prix(m) {
    var p = Math.round(m * 100) / 100, ent = Math.floor(Math.abs(p)).toString(), dec = Math.round((Math.abs(p) % 1) * 100).toString();
    while (dec.length < 2) dec = '0' + dec;
    ent = ent.replace(/\B(?=(\d{3})+(?!\d))/g, f.milliers);
    return f.format.split('{n}').join((p < 0 ? '-' : '') + ent + f.decimal + dec).split('{devise}').join(f.devise);
  }
  function calcul() {
    var t = 0;
    Array.prototype.forEach.call(corps.rows, function (tr) {
      var m = Math.round(nb(tr.querySelector('.cmd-q').value) * nb(tr.querySelector('.cmd-p').value) * 100) / 100;
      t += m;
      tr.querySelector('.cmd-st').textContent = prix(m);
    });
    t += nb(table.querySelector('.cmd-aj').value);
    table.querySelector('.cmd-total').textContent = prix(t);
  }
  table.addEventListener('input', calcul);
  calcul();
  document.getElementById('ajout-produit').addEventListener('change', function () {
    if (!this.value) return;
    var p = JSON.parse(this.value), esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML.replace(/"/g, '&quot;'); };
    var html = document.getElementById('modele-ligne').innerHTML.split('__I__').join(String(n++))
      .split('__ID__').join(esc(p.id)).split('__P__').join(esc(p.produit)).split('__U__').join(esc(p.unite));
    corps.insertAdjacentHTML('beforeend', html);
    corps.lastElementChild.querySelector('.cmd-p').value = String(p.prix).replace('.', f.decimal);
    this.value = '';
    calcul();
  });
  document.getElementById('copier').addEventListener('click', function () {
    var z = document.getElementById('message'), ok = function () { document.getElementById('copie').textContent = <?= json_encode(t('adm_copie')) ?>; };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(z.value).then(ok);
    else { z.select(); try { document.execCommand('copy'); ok(); } catch (e) {} }
  });
})();
</script>
<?php
    ins_pied();
    exit;
}
