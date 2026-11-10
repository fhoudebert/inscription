/*
 * Éditeur de formulaires (admin.php?a=editeur) — JavaScript sans dépendance.
 *
 * Le navigateur ne manipule qu'un « modèle » (formulaire + liste de champs). Le serveur
 * l'écrit en .ini, le vérifie avec le même code que la page publique et renvoie l'aperçu :
 * ce que montre l'aperçu est exactement ce que verra le public.
 */
(function () {
  'use strict';
  var D = window.INS_EDITEUR;
  var racine = document.getElementById('editeur');
  if (!D || !racine) return;

  /* ---------- Outils ---------- */
  function T(cle, params) {
    var s = D.textes[cle] || cle;
    if (params) Object.keys(params).forEach(function (k) { s = s.split('{' + k + '}').join(String(params[k])); });
    return s;
  }
  function el(tag, attrs) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'texte') n.textContent = v;
      else if (k === 'classe') n.className = v;
      else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), v);
      else if (v === true) n.setAttribute(k, '');
      else n.setAttribute(k, v);
    });
    for (var i = 2; i < arguments.length; i++) {
      var c = arguments[i];
      if (c === null || c === undefined || c === false) continue;
      (Array.isArray(c) ? c : [c]).forEach(function (x) {
        if (x !== null && x !== undefined && x !== false) n.appendChild(typeof x === 'string' ? document.createTextNode(x) : x);
      });
    }
    return n;
  }
  function vider(n) { while (n.firstChild) n.removeChild(n.firstChild); }
  function copie(o) { return JSON.parse(JSON.stringify(o)); }
  function lignes(txt) { return String(txt || '').split(/\r?\n/).map(function (x) { return x.trim(); }).filter(Boolean); }
  function nomLangue(code) {
    for (var i = 0; i < D.langues.length; i++) if (D.langues[i].code === code) return D.langues[i].nom;
    return code;
  }
  /** Nom de colonne à partir d'un libellé : « Nom et prénom » → nom_et_prenom */
  function versNom(txt) {
    var s = String(txt || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
      .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40).replace(/_+$/, '');
    if (!s) s = 'champ';
    if (!/^[a-z]/.test(s)) s = 'c_' + s;
    return s;
  }
  function nomUnique(base, sauf) {
    var noms = S.modele.champs.filter(function (c) { return c !== sauf; }).map(function (c) { return c.nom.toLowerCase(); });
    if (D.reserves.indexOf(base) >= 0) base = base + '_1';
    var n = base, i = 2;
    while (noms.indexOf(n.toLowerCase()) >= 0) n = base + '_' + (i++);
    return n;
  }
  var DECORATIFS = ['titre', 'info'];
  var A_OPTIONS = ['choix', 'liste', 'cases', 'echelle'];
  var CONDITIONNABLES = ['choix', 'liste', 'cases', 'echelle', 'case', 'nombre', 'montant', 'total', 'date', 'heure'];
  var COMPARAISONS = ['<', '<=', '>', '>='];

  /* ---------- État ---------- */
  var S = {
    id: D.id, nouveau: D.nouveau, version: D.version,
    modele: D.modele, ini: D.ini, mode: D.modele ? 'visuel' : 'texte',
    sel: -1,                    // -1 = réglages du formulaire, sinon index du champ
    langueEd: null,             // langue des textes en cours d'édition (null = langue de base)
    langueAp: null,             // langue de l'aperçu
    modifie: false, enCours: 0, valide: !D.erreur, erreur: D.erreur || '', champErreur: '',
    colonnes: null
  };
  if (S.modele) S.modele.champs.forEach(function (c) { c._auto = false; });

  function base() { return S.modele.formulaire.langue || D.langue_config; }
  function autresLangues() { return (S.modele.formulaire.langues || []).filter(function (l) { return l !== base(); }); }
  function trad(obj, lg) {
    if (!obj.trad || Array.isArray(obj.trad)) obj.trad = {};
    if (!obj.trad[lg] || Array.isArray(obj.trad[lg])) obj.trad[lg] = {};
    return obj.trad[lg];
  }
  function champ() { return S.sel >= 0 ? S.modele.champs[S.sel] : null; }

  /* ---------- Squelette de la page ---------- */
  var zoneNom, statut, btnMode, btnEnregistrer, liste, props, apercuEtat, apercuColonnes, iframe, choixApercu, zoneTexte, grille;

  function construire() {
    vider(racine);
    zoneNom = S.nouveau
      ? el('input', { type: 'text', id: 'edi-id', value: S.id, placeholder: T('edi_nom_exemple'), pattern: '[A-Za-z0-9][A-Za-z0-9_\\-]*',
          'aria-label': T('edi_nom_formulaire'), oninput: function () { S.id = zoneNom.value.trim(); verifierNomFormulaire(); } })
      : el('code', { texte: S.id + '.ini' });
    statut = el('span', { classe: 'edi-statut', role: 'status' });
    btnMode = el('button', { type: 'button', classe: 'sec', onclick: basculerMode });
    btnEnregistrer = el('button', { type: 'button', onclick: enregistrer, texte: T('edi_enregistrer') });
    racine.appendChild(el('div', { classe: 'edi-barre' },
      el('a', { href: 'admin.php', texte: '← ' + T('edi_retour'), onclick: function (ev) { if (S.modifie && !confirm(T('edi_quitter'))) ev.preventDefault(); } }),
      el('span', { classe: 'edi-nom' }, S.nouveau ? el('label', { 'for': 'edi-id', texte: T('edi_nom_formulaire') + ' ' }) : null, zoneNom),
      statut, el('span', { classe: 'edi-espace' }), btnMode, btnEnregistrer));
    racine.appendChild(el('div', { classe: 'edi-avert', id: 'edi-nom-err' }));

    liste = el('div', { classe: 'edi-liste' });
    props = el('div', { classe: 'edi-props' });
    zoneTexte = el('textarea', { classe: 'edi-texte', spellcheck: 'false', 'aria-label': T('edi_mode_texte'),
      oninput: function () { S.ini = zoneTexte.value; marquerModifie(); planifierApercu(); } });
    choixApercu = el('select', { classe: 'edi-langue-ap', 'aria-label': T('edi_langue_apercu'),
      onchange: function () { S.langueAp = choixApercu.value; planifierApercu(0); } });
    apercuEtat = el('div', { classe: 'edi-etat' });
    apercuColonnes = el('div', { classe: 'edi-colonnes' });
    iframe = el('iframe', { classe: 'edi-iframe', sandbox: 'allow-scripts', title: T('edi_apercu') });
    grille = el('div', { classe: 'edi-grille' },
      el('section', { classe: 'edi-col edi-col-liste' }, liste),
      el('section', { classe: 'edi-col edi-col-props' }, props, zoneTexte),
      el('section', { classe: 'edi-col edi-col-apercu' },
        el('div', { classe: 'edi-apercu-tete' }, el('b', { texte: T('edi_apercu') }), choixApercu), apercuEtat, apercuColonnes, iframe));
    racine.appendChild(grille);
    afficherMode();
  }

  function afficherMode() {
    grille.classList.toggle('mode-texte', S.mode === 'texte');
    btnMode.textContent = S.mode === 'texte' ? T('edi_mode_visuel') : T('edi_mode_texte');
    if (S.mode === 'texte') {
      zoneTexte.value = S.ini;
    } else {
      dessinerListe();
      dessinerProps();
    }
    dessinerChoixApercu();
  }

  function basculerMode() {
    if (S.mode === 'visuel') {
      S.mode = 'texte';
      afficherMode();
      zoneTexte.focus();
      return;
    }
    // Retour au mode visuel : il faut un texte valide, relu par le serveur
    demanderApercu(function (r) {
      if (!r.ok) { alert(T('edi_texte_invalide')); return; }
      S.modele = r.modele;
      S.modele.champs.forEach(function (c) { c._auto = false; });
      if (S.sel >= S.modele.champs.length) S.sel = -1;
      S.mode = 'visuel';
      afficherMode();
    });
  }

  function marquerModifie() {
    S.modifie = true;
    dessinerStatut();
  }

  function dessinerStatut(msg, classe) {
    statut.className = 'edi-statut ' + (classe || '');
    statut.textContent = msg || (S.modifie ? T('edi_non_enregistre') : '');
  }

  function verifierNomFormulaire() {
    var z = document.getElementById('edi-nom-err');
    var msg = '';
    if (S.nouveau) {
      if (!/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/.test(S.id)) msg = T('edi_nom_invalide');
      else if (D.existants.indexOf(S.id) >= 0) msg = T('edi_nom_existe', { nom: S.id });
    }
    z.textContent = msg;
    return !msg;
  }

  /* ---------- Liste des champs ---------- */
  function problemesConditions() {
    // Une condition ne peut viser qu'un champ placé avant, d'un type à choix
    var vus = {}, res = {};
    S.modele.champs.forEach(function (c, i) {
      (c.si || []).forEach(function (cond) {
        var cible = vus[cond.champ];
        if (!cible) res[i] = T('edi_cond_ordre', { cible: cond.champ });
        else if (CONDITIONNABLES.indexOf(cible.type) < 0 && !(cible.type === 'grille' && cond.ref)) res[i] = T('edi_cond_type', { cible: cond.champ });
      });
      if (DECORATIFS.indexOf(c.type) < 0) vus[c.nom] = c;
    });
    return res;
  }

  function dessinerListe() {
    // Si la liste avait le focus (clavier), on le rend à l'élément choisi après l'avoir redessinée
    var avaitFocus = liste.contains(document.activeElement) && document.activeElement.classList.contains('edi-item');
    dessinerListeInterne();
    if (avaitFocus) focaliserSelection();
  }
  function dessinerListeInterne() {
    vider(liste);
    var pb = problemesConditions();
    liste.appendChild(el('div', { classe: 'edi-item' + (S.sel === -1 ? ' actif' : ''), tabindex: '0', role: 'button',
      onclick: function () { choisir(-1); }, onkeydown: entree(function () { choisir(-1); }) },
      el('span', { classe: 'edi-item-titre', texte: '⚙ ' + T('edi_reglages') })));
    S.modele.champs.forEach(function (c, i) {
      var titre = c.libelle || c.nom;
      var item = el('div', { classe: 'edi-item' + (S.sel === i ? ' actif' : '') + (S.champErreur === c.nom || pb[i] ? ' erreur' : '')
        + (DECORATIFS.indexOf(c.type) >= 0 ? ' deco' : ''), tabindex: '0', role: 'button',
        onclick: function () { choisir(i); }, onkeydown: entree(function () { choisir(i); }) },
        el('span', { classe: 'edi-poignee', title: T('edi_glisser'), 'aria-hidden': 'true', texte: '⠿' }),
        el('span', { classe: 'edi-item-titre', texte: (c.type === 'titre' ? '§ ' : '') + titre }),
        el('span', { classe: 'edi-item-meta', texte: T('type_' + c.type) + (c.obligatoire ? ' *' : '') + ((c.si || []).length ? ' · ' + T('edi_conditionnel') : '') }));
      glisserDeposer(item, i);
      if (S.sel === i) {
        item.appendChild(el('span', { classe: 'edi-item-actions' },
          bouton('↑', T('edi_monter'), function () { deplacer(i, -1); }, i === 0),
          bouton('↓', T('edi_descendre'), function () { deplacer(i, 1); }, i === S.modele.champs.length - 1),
          bouton('⧉', T('edi_dupliquer'), function () { dupliquer(i); }),
          bouton('✕', T('edi_supprimer'), function () { supprimer(i); })));
      }
      liste.appendChild(item);
    });
    // « Réglages » accepte un dépôt : le champ passe en tête
    var reglages = liste.firstChild;
    reglages.addEventListener('dragover', function (ev) { if (glisse === null) return; ev.preventDefault(); reglages.classList.add('cible-apres'); });
    reglages.addEventListener('dragleave', function () { reglages.classList.remove('cible-apres'); });
    reglages.addEventListener('drop', function (ev) { ev.preventDefault(); deposer(0); });
    var choixType = el('select', { 'aria-label': T('edi_type') },
      D.types.map(function (t) { return el('option', { value: t, texte: T('type_' + t) }); }));
    liste.appendChild(el('div', { classe: 'edi-ajout' }, choixType,
      el('button', { type: 'button', texte: '+ ' + T('edi_ajouter'), onclick: function () { ajouter(choixType.value); } })));
    liste.appendChild(el('p', { classe: 'edi-aide edi-raccourcis', texte: T('edi_raccourcis') }));
  }

  /* Glisser-déposer : on attrape un champ et on le lâche au-dessus ou au-dessous d'un autre */
  var glisse = null;
  function nettoyerCibles() {
    liste.querySelectorAll('.cible-avant,.cible-apres,.glisse').forEach(function (x) { x.classList.remove('cible-avant', 'cible-apres', 'glisse'); });
  }
  function glisserDeposer(item, i) {
    item.draggable = true;
    item.addEventListener('dragstart', function (ev) {
      glisse = i;
      ev.dataTransfer.effectAllowed = 'move';
      ev.dataTransfer.setData('text/plain', String(i)); // nécessaire pour Firefox
      item.classList.add('glisse');
    });
    item.addEventListener('dragend', function () { glisse = null; nettoyerCibles(); });
    item.addEventListener('dragover', function (ev) {
      if (glisse === null) return;
      ev.preventDefault();
      ev.dataTransfer.dropEffect = 'move';
      var r = item.getBoundingClientRect(), avant = ev.clientY < r.top + r.height / 2;
      item.classList.toggle('cible-avant', avant);
      item.classList.toggle('cible-apres', !avant);
    });
    item.addEventListener('dragleave', function () { item.classList.remove('cible-avant', 'cible-apres'); });
    item.addEventListener('drop', function (ev) {
      ev.preventDefault();
      var r = item.getBoundingClientRect();
      deposer(ev.clientY < r.top + r.height / 2 ? i : i + 1);
    });
  }
  /** Déplace le champ glissé pour qu'il se retrouve à la position « pos » (index avant déplacement). */
  function deposer(pos) {
    var de = glisse;
    glisse = null;
    nettoyerCibles();
    if (de === null) return;
    var ch = S.modele.champs, c = ch[de];
    ch.splice(de, 1);
    if (pos > de) pos--;
    ch.splice(pos, 0, c);
    S.sel = pos;
    if (pos !== de) changementStructure(); else { dessinerListe(); dessinerProps(); }
  }
  function focaliserSelection() {
    var actif = liste.querySelector('.edi-item.actif');
    if (actif) actif.focus();
  }
  function entree(f) { return function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); f(); } }; }
  function bouton(txt, titre, f, inactif) {
    return el('button', { type: 'button', classe: 'sec mini', title: titre, 'aria-label': titre, disabled: !!inactif,
      onclick: function (ev) { ev.stopPropagation(); f(); }, texte: txt });
  }

  function choisir(i) {
    S.sel = i;
    dessinerListe();
    dessinerProps();
    if (i >= 0 && iframe.contentWindow) iframe.contentWindow.postMessage({ ins: 'marquer', nom: S.modele.champs[i].nom }, '*');
  }

  function changementStructure() {
    marquerModifie();
    dessinerListe();
    dessinerProps();
    planifierApercu(0);
  }

  function nouveauChamp(type) {
    var lib = T('edi_nouveau_' + (DECORATIFS.indexOf(type) >= 0 ? type : 'champ'));
    var c = { nom: '', type: type, libelle: lib, aide: '', obligatoire: false, options: [], defaut: '', min: '', max: '',
      unique: false, exemple: '', gauche: '', droite: '', si: [], trad: {}, _auto: true };
    if (['choix', 'liste', 'cases'].indexOf(type) >= 0) c.options = [T('edi_option') + ' 1', T('edi_option') + ' 2'];
    if (type === 'echelle') c.options = ['1', '2', '3', '4', '5'];
    if (type === 'grille') { c.lignes = [T('edi_ligne') + ' 1', T('edi_ligne') + ' 2']; c.options = [T('edi_grille_oui'), T('edi_grille_non')]; c.multiple = false; }
    if (type === 'commande') { c.catalogue = D.catalogues[0] || ''; c.libelle = T('edi_nouveau_commande'); }
    c.nom = nomUnique(type === 'titre' ? 'section' : (type === 'info' ? 'info' : versNom(c.libelle)), c);
    return c;
  }
  function ajouter(type) {
    var c = nouveauChamp(type);
    var pos = S.sel >= 0 ? S.sel + 1 : S.modele.champs.length;
    S.modele.champs.splice(pos, 0, c);
    S.sel = pos;
    changementStructure();
    var z = props.querySelector('[data-cle="libelle"]');
    if (z) { z.focus(); z.select(); }
  }
  function deplacer(i, d) {
    var j = i + d, ch = S.modele.champs;
    if (j < 0 || j >= ch.length) return;
    var x = ch[i]; ch[i] = ch[j]; ch[j] = x;
    S.sel = j;
    changementStructure();
  }
  function dupliquer(i) {
    var c = copie(S.modele.champs[i]);
    c._auto = false;
    c.nom = nomUnique(c.nom, c);
    S.modele.champs.splice(i + 1, 0, c);
    S.sel = i + 1;
    changementStructure();
  }
  function supprimer(i) {
    var c = S.modele.champs[i];
    var dependants = S.modele.champs.filter(function (x) { return (x.si || []).some(function (k) { return k.champ === c.nom; }); });
    var msg = T('edi_confirmer_suppr', { nom: c.libelle || c.nom });
    if (dependants.length) msg += '\n' + T('edi_confirmer_suppr_cond', { n: dependants.length });
    if (!confirm(msg)) return;
    dependants.forEach(function (x) { x.si = x.si.filter(function (k) { return k.champ !== c.nom; }); });
    S.modele.champs.splice(i, 1);
    S.sel = Math.min(i, S.modele.champs.length - 1);
    changementStructure();
  }

  /* ---------- Panneau des propriétés ---------- */
  function ligne(libelle, controle, aide, id) {
    return el('div', { classe: 'edi-prop' }, libelle ? el('label', { 'for': id || null, texte: libelle }) : null, controle,
      aide ? el('div', { classe: 'edi-aide', texte: aide }) : null);
  }
  /** Zone de texte liée à obj[cle] ; mise à jour du modèle et de l'aperçu à la frappe. */
  function texte(obj, cle, opts) {
    opts = opts || {};
    var id = 'p-' + cle + '-' + Math.random().toString(36).slice(2, 7);
    var z = el(opts.long ? 'textarea' : 'input', { id: id, type: opts.long ? null : (opts.type || 'text'), 'data-cle': cle,
      placeholder: opts.placeholder || null, inputmode: opts.inputmode || null, min: opts.min, max: opts.max, rows: opts.long ? (opts.rows || 3) : null });
    var v = obj[cle];
    z.value = Array.isArray(v) ? v.join('\n') : (v === undefined || v === null ? '' : String(v));
    z.addEventListener('input', function () {
      obj[cle] = opts.liste ? lignes(z.value) : (opts.type === 'number' ? (z.value === '' ? 0 : parseInt(z.value, 10) || 0) : z.value);
      if (opts.apres) opts.apres(z);
      marquerModifie();
      planifierApercu();
    });
    return ligne(opts.libelle, z, opts.aide, id);
  }
  function caseCocher(obj, cle, libelle, apres) {
    var c = el('input', { type: 'checkbox' });
    c.checked = !!obj[cle];
    c.addEventListener('change', function () { obj[cle] = c.checked; marquerModifie(); if (apres) apres(); planifierApercu(0); });
    return el('label', { classe: 'opt edi-case' }, c, el('span', { texte: libelle }));
  }
  function choixListe(obj, cle, valeurs, libelle, apres) {
    var id = 'p-' + cle + '-' + Math.random().toString(36).slice(2, 7);
    var s = el('select', { id: id }, valeurs.map(function (v) { return el('option', { value: v[0], texte: v[1] }); }));
    s.value = obj[cle] || '';
    s.addEventListener('change', function () { obj[cle] = s.value; marquerModifie(); if (apres) apres(); planifierApercu(0); });
    return ligne(libelle, s, null, id);
  }
  function groupe(titre) { return el('h3', { classe: 'edi-groupe', texte: titre }); }

  /** Sélecteur de la langue des textes édités (formulaires multilingues). */
  function barreLangues() {
    var autres = autresLangues();
    if (!autres.length) { S.langueEd = null; return null; }
    if (S.langueEd && autres.indexOf(S.langueEd) < 0) S.langueEd = null;
    var b = el('div', { classe: 'edi-langues', role: 'tablist' }, el('span', { texte: T('edi_langue_textes') + ' ' }));
    [base()].concat(autres).forEach(function (lg) {
      var actif = (S.langueEd || base()) === lg;
      b.appendChild(el('button', { type: 'button', classe: actif ? '' : 'sec', role: 'tab', 'aria-selected': actif ? 'true' : 'false',
        texte: nomLangue(lg) + (lg === base() ? ' (' + T('edi_base') + ')' : ''),
        onclick: function () { S.langueEd = lg === base() ? null : lg; S.langueAp = lg; dessinerProps(); dessinerChoixApercu(); planifierApercu(0); } }));
    });
    return b;
  }

  /** Champ traduisible : en langue de base, le texte lui-même ; sinon sa traduction, avec le texte de base en aide. */
  function traduisible(obj, cle, opts) {
    if (!S.langueEd) return texte(obj, cle, opts);
    var t = trad(obj, S.langueEd);
    var o = Object.assign({}, opts);
    var orig = obj[cle];
    o.libelle = (opts.libelle || '') + ' — ' + nomLangue(S.langueEd);
    o.aide = T('edi_texte_base') + ' ' + (Array.isArray(orig) ? orig.join(' | ') : (orig || '—'));
    if (opts.liste) {
      o.apres = function (z) {
        var n = lignes(z.value).length;
        z.classList.toggle('invalide', n > 0 && n !== (obj[cle] || []).length);
      };
    }
    return texte(t, cle, o);
  }

  function dessinerProps() {
    vider(props);
    var bl = barreLangues();
    if (bl) props.appendChild(bl);
    if (S.sel < 0) dessinerReglages(); else dessinerChamp(champ());
    if (S.langueEd) props.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_trad_note') }));
  }

  function dessinerReglages() {
    var g = S.modele.formulaire;
    var cmd = S.modele.champs.some(function (c) { return c.type === 'commande'; });
    props.appendChild(el('h2', { texte: T('edi_reglages') }));
    function textes() {
      props.appendChild(traduisible(g, 'intro', { libelle: T('edi_intro'), long: true }));
      props.appendChild(traduisible(g, 'merci', { libelle: T('edi_merci'), long: true, rows: 2, placeholder: T('edi_par_defaut') }));
      props.appendChild(traduisible(g, 'bouton', { libelle: T('edi_bouton'), placeholder: T('edi_par_defaut') }));
      if (g.confirmation) {
        props.appendChild(traduisible(g, 'confirmation_texte', { libelle: T('edi_confirmation_texte'), long: true, rows: 3,
          placeholder: T('edi_confirmation_texte_defaut'), aide: T('edi_confirmation_texte_aide') }));
      }
      if (cmd) {
        props.appendChild(traduisible(g, 'message', { libelle: T('edi_message_commande'), long: true, rows: 6, placeholder: T('edi_par_defaut'),
          aide: T('edi_message_commande_aide', { champs: S.modele.champs.filter(function (c) { return DECORATIFS.indexOf(c.type) < 0 && c.type !== 'commande'; })
            .map(function (c) { return '{' + c.nom + '}'; }).join(' ') }) }));
      }
    }
    if (S.langueEd) {
      props.appendChild(traduisible(g, 'titre', { libelle: T('edi_titre_form') }));
      if (g.logo !== 'non' && (g.logo || D.logo_site)) props.appendChild(traduisible(g, 'logo_texte', { libelle: T('edi_logo_texte') }));
      textes();
      return;
    }
    // Sommaire : accès direct à chaque groupe de réglages (le panneau défile)
    var groupes = [['acces', T('edi_acces_groupe')], ['logo', T('edi_logo_groupe')], ['textes', T('edi_textes')], ['periode', T('edi_periode')], ['langues', T('edi_langues')]];
    props.appendChild(el('p', { classe: 'edi-sommaire' }, groupes.map(function (x, n) {
      return [n ? ' · ' : '', el('a', { href: '#', texte: x[1], onclick: function (ev) {
        ev.preventDefault();
        var cible = props.querySelector('[data-groupe="' + x[0] + '"]');
        if (cible) cible.scrollIntoView({ block: 'start', behavior: 'smooth' });
      } })];
    }).reduce(function (a, b) { return a.concat(b); }, [])));
    function titreGroupe(cle, texteGroupe) {
      var h = groupe(texteGroupe);
      h.setAttribute('data-groupe', cle);
      props.appendChild(h);
    }
    props.appendChild(traduisible(g, 'titre', { libelle: T('edi_titre_form') }));

    titreGroupe('acces', T('edi_acces_groupe'));
    props.appendChild(choixListe(g, 'acces', [['', T('edi_acces_libre')], ['personnel', T('edi_acces_personnel')], ['lien', T('edi_acces_lien')]],
      T('edi_acces'), dessinerProps));
    if (!g.acces) props.appendChild(caseCocher(g, 'diffusion', T('edi_diffusion')));
    else {
      props.appendChild(caseCocher(g, 'modifiable', T('edi_modifiable')));
      props.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_acces_aide') }));
    }
    props.appendChild(caseCocher(g, 'afficher_numero', T('edi_afficher_numero')));
    props.appendChild(texte(g, 'notifier', { libelle: T('edi_notifier'), placeholder: 'contact@exemple.fr', aide: T('edi_notifier_aide') }));
    // Courriel de confirmation au répondant : vers un champ e-mail du formulaire
    var mails = S.modele.champs.filter(function (c) { return c.type === 'email'; });
    var valsConf = [['', T('edi_confirmation_non')]].concat(mails.map(function (c, i) {
      return [i === 0 && g.confirmation === 'oui' ? 'oui' : c.nom, T('edi_confirmation_vers', { champ: c.libelle || c.nom })];
    }));
    if (g.confirmation && g.confirmation !== 'oui' && !mails.some(function (c) { return c.nom === g.confirmation; })) valsConf.push([g.confirmation, g.confirmation + ' ⚠']);
    props.appendChild(choixListe(g, 'confirmation', valsConf, T('edi_confirmation'), dessinerProps));
    if (!mails.length) props.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_confirmation_sans_mail') }));
    else if (g.confirmation && !D.confirmation_site) props.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_confirmation_site') }));

    titreGroupe('logo', T('edi_logo_groupe'));
    reglageLogo(g);

    titreGroupe('textes', T('edi_textes'));
    textes();

    titreGroupe('periode', T('edi_periode'));
    props.appendChild(texte(g, 'ouverture', { libelle: T('edi_ouverture'), placeholder: 'AAAA-MM-JJ HH:MM', aide: T('edi_date_aide') }));
    props.appendChild(texte(g, 'fermeture', { libelle: T('edi_fermeture'), placeholder: 'AAAA-MM-JJ', aide: T('edi_fermeture_aide') }));
    props.appendChild(texte(g, 'places', { libelle: T('edi_places'), type: 'number', min: '0', aide: T('edi_places_aide') }));

    titreGroupe('langues', T('edi_langues'));
    props.appendChild(choixListe(g, 'langue', D.langues.map(function (l) { return [l.code, l.nom]; }), T('edi_langue_base'), function () {
      g.langues = [g.langue].concat((g.langues || []).filter(function (l) { return l !== g.langue; }));
      dessinerProps(); dessinerChoixApercu();
    }));
    var autres = el('div', { classe: 'edi-prop' }, el('label', { texte: T('edi_autres_langues') }));
    D.langues.forEach(function (l) {
      if (l.code === base()) return;
      var c = el('input', { type: 'checkbox' });
      c.checked = (g.langues || []).indexOf(l.code) >= 0;
      c.addEventListener('change', function () {
        var ls = (g.langues || []).filter(function (x) { return x !== l.code && x !== base(); });
        if (c.checked) ls.push(l.code);
        g.langues = [base()].concat(ls);
        marquerModifie(); dessinerProps(); dessinerChoixApercu(); planifierApercu(0);
      });
      autres.appendChild(el('label', { classe: 'opt edi-case' }, c, el('span', { texte: l.nom })));
    });
    props.appendChild(autres);

    var avance = el('details', { classe: 'edi-avance' }, el('summary', { texte: T('edi_avance') }),
      texte(g, 'fichier', { libelle: T('edi_fichier'), placeholder: S.id || 'nom', aide: T('edi_fichier_aide') }));
    props.appendChild(avance);
  }

  /** Logo à gauche du titre : logo du site, aucun, ou une image de prive/images/ (envoi possible d'ici). */
  function reglageLogo(g) {
    var site = D.logo_site ? T('edi_logo_site', { nom: D.logo_site }) : T('edi_logo_site_aucun');
    var vals = [['', site], ['non', T('edi_logo_aucun')]].concat(D.images.map(function (n) { return [n, n]; }));
    if (g.logo && g.logo !== 'non' && D.images.indexOf(g.logo) < 0) vals.push([g.logo, g.logo + ' ⚠']);
    props.appendChild(choixListe(g, 'logo', vals, T('edi_logo'), dessinerProps));
    var fichier = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/gif,image/webp', id: 'p-logo-envoi' });
    var etat = el('div', { classe: 'msg' });
    fichier.addEventListener('change', function () {
      if (!fichier.files.length) return;
      var fd = new FormData();
      fd.append('action', 'editeur_image');
      fd.append('image', fichier.files[0]);
      etat.textContent = T('edi_logo_envoi');
      envoyer(fd).then(function (r) {
        if (!r.ok) { etat.textContent = r.erreur; return; }
        D.images = r.images;
        g.logo = r.nom;
        marquerModifie(); dessinerProps(); planifierApercu(0);
      }).catch(function () { etat.textContent = T('edi_err_reseau'); });
    });
    props.appendChild(ligne(T('edi_logo_ajouter'), fichier, T('edi_logo_aide'), 'p-logo-envoi'));
    props.appendChild(etat);
    if (g.logo !== 'non' && (g.logo || D.logo_site)) {
      props.appendChild(traduisible(g, 'logo_texte', { libelle: T('edi_logo_texte'), aide: T('edi_logo_texte_aide') }));
    }
  }

  function dessinerChamp(c) {
    var deco = DECORATIFS.indexOf(c.type) >= 0;
    var p = D.proprietes[c.type] || [];
    props.appendChild(el('h2', { texte: T('type_' + c.type) }));
    if (S.champErreur === c.nom && S.erreur) props.appendChild(el('div', { classe: 'alerte', texte: S.erreur }));

    // Libellé (et nom automatique tant qu'on ne l'a pas modifié à la main)
    props.appendChild(traduisible(c, 'libelle', { libelle: deco ? T('edi_texte') : T('edi_question'), long: c.type === 'info' || c.type === 'case',
      rows: 2, apres: function () { if (!S.langueEd && c._auto && !deco) { c.nom = nomUnique(versNom(c.libelle), c); zNom.value = c.nom; } dessinerListe(); } }));
    if (!S.langueEd) {
      var zNom = el('input', { type: 'text', value: c.nom, id: 'p-nom', spellcheck: 'false' });
      var errNom = el('div', { classe: 'msg' });
      zNom.addEventListener('input', function () {
        var ancien = c.nom, v = zNom.value.trim();
        var pb = !/^[a-z][a-z0-9_]{0,63}$/i.test(v) ? T('edi_nom_champ_invalide')
          : D.reserves.indexOf(v.toLowerCase()) >= 0 ? T('edi_nom_reserve')
          : S.modele.champs.some(function (x) { return x !== c && x.nom.toLowerCase() === v.toLowerCase(); }) ? T('edi_nom_double') : '';
        errNom.textContent = pb;
        if (pb) return;
        c.nom = v; c._auto = false;
        // Les conditions qui visaient l'ancien nom suivent le renommage
        S.modele.champs.forEach(function (x) { (x.si || []).forEach(function (k) { if (k.champ === ancien) k.champ = v; }); });
        marquerModifie(); dessinerListe(); planifierApercu();
      });
      props.appendChild(ligne(T('edi_nom_colonne'), zNom, deco ? T('edi_nom_deco') : T('edi_nom_aide'), 'p-nom'));
      props.appendChild(errNom);
      props.appendChild(choixListe(c, 'type', D.types.map(function (t) { return [t, T('type_' + t)]; }), T('edi_type'), function () {
        if (['choix', 'liste', 'cases'].indexOf(c.type) >= 0 && !(c.options || []).length) c.options = [T('edi_option') + ' 1', T('edi_option') + ' 2'];
        if (c.type === 'echelle' && !(c.options || []).length) c.options = ['1', '2', '3', '4', '5'];
        if (c.type === 'grille') {
          if (!(c.lignes || []).length) c.lignes = [T('edi_ligne') + ' 1', T('edi_ligne') + ' 2'];
          if (!(c.options || []).length) c.options = [T('edi_grille_oui'), T('edi_grille_non')];
        }
        dessinerListe(); dessinerProps();
      }));
    }
    if (p.indexOf('calcul') >= 0) {
      // Champs utilisables : nombres, montants et totaux placés avant ce champ
      var avant = [];
      for (var i = 0; i < S.modele.champs.length && S.modele.champs[i] !== c; i++) {
        if (['nombre', 'montant', 'total'].indexOf(S.modele.champs[i].type) >= 0) avant.push(S.modele.champs[i].nom);
      }
      props.appendChild(texte(c, 'calcul', { libelle: T('edi_calcul'), placeholder: avant.length > 1 ? avant[0] + ' * 15 + ' + avant[1] + ' * 8' : 'adultes * 15 + enfants * 8',
        aide: T('edi_calcul_aide', { champs: avant.length ? avant.join(', ') : '—' }) }));
    }
    if (p.indexOf('saisie') >= 0) {
      props.appendChild(choixListe(c, 'saisie', [['', T('edi_saisie_choix')], ['nombre', T('edi_saisie_nombre')],
        ['montant', T('edi_saisie_montant')], ['texte', T('edi_saisie_texte')], ['liste', T('edi_saisie_liste')]], T('edi_saisie'), function () {
          if (c.saisie === 'liste' && !(c.valeurs || []).length) c.valeurs = [T('edi_grille_oui'), T('edi_grille_non')];
          dessinerProps();
        }));
      if (c.saisie === 'liste') {
        props.appendChild(traduisible(c, 'valeurs', { libelle: T('edi_valeurs_liste'), long: true, rows: 3, liste: true, aide: T('edi_valeurs_liste_aide') }));
      }
    }
    if (p.indexOf('lignes') >= 0) {
      props.appendChild(traduisible(c, 'lignes', { libelle: T('edi_lignes'), long: true, rows: 4, liste: true, aide: T('edi_lignes_aide') }));
    }
    if (p.indexOf('options') >= 0 && c.type === 'grille') {
      props.appendChild(traduisible(c, 'options', { libelle: T(c.saisie ? 'edi_colonnes_saisie' : 'edi_colonnes'), long: true, rows: 3, liste: true,
        aide: T(c.saisie ? 'edi_colonnes_saisie_aide' : 'edi_options_aide') }));
    } else if (p.indexOf('options') >= 0) {
      props.appendChild(traduisible(c, 'options', { libelle: T('edi_options'), long: true, rows: 5, liste: true,
        aide: c.type === 'echelle' ? T('edi_options_echelle') : T('edi_options_aide'), apres: optionsModifiees(c) }));
    }
    if (p.indexOf('gauche') >= 0) {
      props.appendChild(traduisible(c, 'gauche', { libelle: T('edi_gauche'), placeholder: T('edi_gauche_ex') }));
      props.appendChild(traduisible(c, 'droite', { libelle: T('edi_droite'), placeholder: T('edi_droite_ex') }));
    }
    if (p.indexOf('catalogue') >= 0 && !S.langueEd) {
      var cats = D.catalogues.map(function (n) { return [n, n]; });
      if (c.catalogue && D.catalogues.indexOf(c.catalogue) < 0) cats.unshift([c.catalogue, c.catalogue + ' ⚠']);
      props.appendChild(choixListe(c, 'catalogue', cats.length ? cats : [['', '—']], T('edi_catalogue')));
      props.appendChild(el('p', { classe: 'edi-aide' }, T('edi_catalogue_aide') + ' ',
        c.catalogue ? el('a', { href: 'admin.php?a=catalogue&nom=' + encodeURIComponent(c.catalogue), target: '_blank', texte: T('edi_catalogue_ouvrir') }) : null));
    }
    if (p.indexOf('aide') >= 0) props.appendChild(traduisible(c, 'aide', { libelle: T('edi_aide'), long: true, rows: 2 }));
    if (p.indexOf('exemple') >= 0) props.appendChild(traduisible(c, 'exemple', { libelle: T('edi_exemple'), aide: T('edi_exemple_aide') }));
    if (S.langueEd) return;

    if (p.indexOf('obligatoire') >= 0) props.appendChild(caseCocher(c, 'obligatoire', c.type === 'case' ? T('edi_obligatoire_case') : T('edi_obligatoire'), dessinerListe));
    if (p.indexOf('unique') >= 0) props.appendChild(caseCocher(c, 'unique', T('edi_unique')));
    if (p.indexOf('multiple') >= 0 && !c.saisie) props.appendChild(caseCocher(c, 'multiple', T('edi_multiple')));
    var numGrille = c.type !== 'grille' || c.saisie === 'nombre' || c.saisie === 'montant';
    if (p.indexOf('min') >= 0 && numGrille) {
      var tc = c.type === 'grille' ? c.saisie : c.type; // grille de saisie : limites de chaque case
      var tp = { date: 'date', heure: 'time', nombre: 'text', montant: 'text' }[tc] || 'number';
      var lib = { nombre: ['edi_val_min', 'edi_val_max'], montant: ['edi_montant_min', 'edi_montant_max'], date: ['edi_date_min', 'edi_date_max'],
        heure: ['edi_heure_min', 'edi_heure_max'] }[tc] || ['edi_long_min', 'edi_long_max'];
      props.appendChild(el('div', { classe: 'edi-deux' }, texte(c, 'min', { libelle: T(lib[0]), type: tp, inputmode: tp === 'text' ? 'decimal' : null }),
        texte(c, 'max', { libelle: T(lib[1]), type: tp, inputmode: tp === 'text' ? 'decimal' : null })));
      if (['nombre', 'montant', 'heure'].indexOf(tc) >= 0) props.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_bornes_aide') }));
    }
    if (p.indexOf('entier') >= 0 && (c.type !== 'grille' || c.saisie === 'nombre')) props.appendChild(caseCocher(c, 'entier', T('edi_entier')));
    if (p.indexOf('defaut') >= 0) {
      if (c.type === 'case') {
        var obj = { v: c.defaut === 'oui' || c.defaut === '1' };
        props.appendChild(caseCocher(obj, 'v', T('edi_defaut_case'), function () { c.defaut = obj.v ? 'oui' : ''; }));
      } else if (c.type === 'choix' || c.type === 'liste' || c.type === 'echelle') {
        props.appendChild(choixListe(c, 'defaut', [['', '—']].concat((c.options || []).map(function (o) { return [o, o]; })), T('edi_defaut')));
      } else {
        props.appendChild(texte(c, 'defaut', { libelle: T('edi_defaut'), type: c.type === 'date' ? 'date' : 'text',
          aide: c.type === 'cases' ? T('edi_defaut_cases') : null }));
      }
    }
    props.appendChild(conditions(c));
  }

  /** Quand les options changent : les conditions suivent (renommage à nombre égal), le défaut aussi. */
  function optionsModifiees(c) {
    var avant = (c.options || []).slice();
    return function () {
      var apres = c.options || [];
      S.modele.champs.forEach(function (x) {
        (x.si || []).forEach(function (k) {
          if (k.champ !== c.nom) return;
          k.valeurs = k.valeurs.map(function (v) {
            var i = avant.indexOf(v);
            return apres.length === avant.length && i >= 0 ? apres[i] : v;
          }).filter(function (v) { return apres.indexOf(v) >= 0; });
        });
      });
      if (c.defaut && avant.length === apres.length && avant.indexOf(c.defaut) >= 0) c.defaut = apres[avant.indexOf(c.defaut)];
      avant = apres.slice();
      dessinerListe();
    };
  }

  /** Assistant « Afficher si… » */
  function conditions(c) {
    var bloc = el('div', { classe: 'edi-conditions' }, el('h3', { classe: 'edi-groupe', texte: T('edi_afficher_si') }));
    var cibles = [];
    for (var i = 0; i < S.modele.champs.length && S.modele.champs[i] !== c; i++) {
      if (CONDITIONNABLES.indexOf(S.modele.champs[i].type) >= 0) cibles.push(S.modele.champs[i]);
    }
    if (!(c.si || []).length) bloc.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_toujours') }));
    // Plusieurs conditions : toutes (ET), ou au moins une (OU) si config.php le permet
    if ((c.si || []).length > 1 && (D.conditions_ou || c.si_ou)) {
      var sMode = el('select', { 'aria-label': T('edi_cond_mode') },
        el('option', { value: '', texte: T('edi_cond_toutes') }), el('option', { value: 'ou', texte: T('edi_cond_une') }));
      sMode.value = c.si_ou ? 'ou' : '';
      sMode.addEventListener('change', function () { c.si_ou = sMode.value === 'ou'; marquerModifie(); dessinerProps(); planifierApercu(0); });
      bloc.appendChild(el('div', { classe: 'edi-prop' }, sMode));
      if (c.si_ou && !D.conditions_ou) bloc.appendChild(el('div', { classe: 'msg', texte: T('edi_cond_ou_desactive') }));
    }
    (c.si || []).forEach(function (k, n) {
      if (k.ref) { // condition sur une grille : affichée telle quelle, à modifier dans le texte .ini
        bloc.appendChild(el('div', { classe: 'edi-cond' }, el('div', { classe: 'edi-cond-ligne' },
          n > 0 ? el('span', { classe: 'edi-et', texte: T(c.si_ou ? 'edi_ou' : 'edi_et') }) : null,
          el('code', { texte: k.champ + k.ref + ' ' + k.op + ' ' + (k.valeurs || []).join(' | ') }),
          bouton('✕', T('edi_cond_retirer'), function () { c.si.splice(n, 1); marquerModifie(); dessinerProps(); dessinerListe(); planifierApercu(0); })),
          el('p', { classe: 'edi-aide', texte: T('edi_cond_grille') })));
        return;
      }
      var cible = cibles.filter(function (x) { return x.nom === k.champ; })[0];
      var sCible = el('select', { 'aria-label': T('edi_cond_champ') },
        (cible ? [] : [el('option', { value: k.champ, texte: k.champ + ' ⚠' })]).concat(cibles.map(function (x) {
          return el('option', { value: x.nom, texte: x.libelle || x.nom });
        })));
      sCible.value = k.champ;
      sCible.addEventListener('change', function () { k.champ = sCible.value; k.valeurs = []; k.op = '='; k.egal = true; marquerModifie(); dessinerProps(); dessinerListe(); planifierApercu(0); });
      // Opérateurs : « est parmi / n'est pas parmi » pour les choix ; =, ≠, <, ≤, >, ≥ pour les nombres et les dates
      var typeC = cible ? cible.type : '';
      var numerique = ['nombre', 'montant', 'total', 'date', 'heure'].indexOf(typeC) >= 0;
      var opsComp = [['<', T('edi_cond_inf')], ['<=', T('edi_cond_inf_egal')], ['>', T('edi_cond_sup')], ['>=', T('edi_cond_sup_egal')]];
      var ops = numerique ? [['=', T('edi_cond_egal')], ['!=', T('edi_cond_different')]].concat(opsComp)
        : [['est', T('edi_cond_est')], ['nest', T('edi_cond_nest_pas')]].concat(typeC === 'echelle' ? opsComp : []);
      var sOp = el('select', { 'aria-label': T('edi_cond_op') }, ops.map(function (o) { return el('option', { value: o[0], texte: o[1] }); }));
      var comparaison = COMPARAISONS.indexOf(k.op) >= 0;
      sOp.value = comparaison ? k.op : numerique ? (k.egal ? '=' : '!=') : (k.egal ? 'est' : 'nest');
      sOp.addEventListener('change', function () {
        var v = sOp.value, avant = COMPARAISONS.indexOf(k.op) >= 0 || numerique;
        k.op = v === 'est' ? '=' : v === 'nest' ? '!=' : v;
        k.egal = k.op !== '!=';
        if (avant !== (COMPARAISONS.indexOf(k.op) >= 0 || numerique)) k.valeurs = []; // liste de choix ⇄ valeur saisie
        marquerModifie(); dessinerProps(); planifierApercu(0);
      });
      var vals = el('div', { classe: 'edi-cond-valeurs' });
      if (cible && (numerique || comparaison)) {
        var zVal = el('input', { type: { date: 'date', heure: 'time' }[typeC] || 'text', inputmode: typeC === 'date' || typeC === 'heure' ? null : 'decimal',
          value: (k.valeurs || [])[0] || '', 'aria-label': T('edi_cond_valeur'), placeholder: typeC === 'date' || typeC === 'heure' ? '' : '4' });
        zVal.style.width = '10rem';
        zVal.addEventListener('input', function () { var v = zVal.value.trim(); k.valeurs = v ? [v] : []; marquerModifie(); planifierApercu(); });
        vals.appendChild(zVal);
        vals.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_cond_vide') }));
      }
      var choix = !cible || numerique || comparaison ? [] : cible.type === 'case' ? [['1', T('edi_coche')], ['0', T('edi_non_coche')]] : (cible.options || []).map(function (o) { return [o, o]; });
      choix.forEach(function (o) {
        var cc = el('input', { type: 'checkbox' });
        cc.checked = k.valeurs.indexOf(o[0]) >= 0;
        cc.addEventListener('change', function () {
          k.valeurs = k.valeurs.filter(function (v) { return v !== o[0]; });
          if (cc.checked) k.valeurs.push(o[0]);
          marquerModifie(); planifierApercu(0);
        });
        vals.appendChild(el('label', { classe: 'opt edi-case' }, cc, el('span', { texte: o[1] })));
      });
      if (!cible) vals.appendChild(el('div', { classe: 'msg', texte: T('edi_cond_ordre', { cible: k.champ }) }));
      bloc.appendChild(el('div', { classe: 'edi-cond' }, el('div', { classe: 'edi-cond-ligne' },
        n > 0 ? el('span', { classe: 'edi-et', texte: T(c.si_ou ? 'edi_ou' : 'edi_et') }) : null, sCible, sOp,
        bouton('✕', T('edi_cond_retirer'), function () { c.si.splice(n, 1); marquerModifie(); dessinerProps(); dessinerListe(); planifierApercu(0); })), vals));
    });
    if (cibles.length) {
      bloc.appendChild(el('button', { type: 'button', classe: 'sec', texte: '+ ' + T('edi_cond_ajouter'), onclick: function () {
        c.si = c.si || [];
        c.si.push({ champ: cibles[cibles.length - 1].nom, op: '=', egal: true, valeurs: [] });
        marquerModifie(); dessinerProps(); dessinerListe();
      } }));
    } else {
      bloc.appendChild(el('p', { classe: 'edi-aide', texte: T('edi_cond_aucune_cible') }));
    }
    return bloc;
  }

  /** Envoi au serveur ; si le jeton a changé (reconnexion entre-temps), nouvel essai avec le jeton renvoyé. */
  function envoyer(fd, deja) {
    fd.set('_jeton', D.jeton);
    return fetch('admin.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(lireJson).then(function (r) {
      if (!r.ok && r.jeton && r.jeton !== D.jeton && !deja) {
        D.jeton = r.jeton;
        return envoyer(fd, true);
      }
      return r;
    });
  }

  /** Réponse JSON du serveur ; autre chose (page de connexion) = session d'administration expirée. */
  function lireJson(r) {
    if ((r.headers.get('Content-Type') || '').indexOf('application/json') < 0) {
      return Promise.resolve({ ok: false, erreur: T('edi_err_session'), champ: '' });
    }
    return r.json();
  }

  /* ---------- Aperçu (rendu par le serveur) ---------- */
  function dessinerChoixApercu() {
    vider(choixApercu);
    var ls = S.modele ? [base()].concat(autresLangues()) : [];
    choixApercu.style.display = ls.length > 1 ? '' : 'none';
    ls.forEach(function (l) { choixApercu.appendChild(el('option', { value: l, texte: nomLangue(l) })); });
    if (ls.indexOf(S.langueAp) < 0) S.langueAp = ls[0] || null;
    if (S.langueAp) choixApercu.value = S.langueAp;
  }

  function nettoyer(m) {
    var c = copie(m);
    c.champs.forEach(function (x) { delete x._auto; });
    return JSON.stringify(c);
  }

  function demanderApercu(fini) {
    var fd = new FormData();
    var num = ++S.enCours;
    fd.append('_jeton', D.jeton);
    fd.append('action', 'editeur_apercu');
    fd.append('id', S.id || 'nouveau');
    fd.append('source', S.mode === 'texte' ? 'texte' : 'modele');
    if (S.mode === 'texte') fd.append('ini', S.ini); else fd.append('modele', nettoyer(S.modele));
    fd.append('langue', S.langueAp || '');
    var ch = champ();
    fd.append('choisi', ch ? ch.nom : '');
    apercuEtat.className = 'edi-etat';
    apercuEtat.textContent = T('edi_verification');
    envoyer(fd)
      .then(function (r) {
        if (num !== S.enCours) return; // une réponse plus récente est attendue
        S.valide = r.ok;
        S.erreur = r.ok ? '' : r.erreur;
        S.champErreur = r.ok ? '' : (r.champ || '');
        if (r.ini !== undefined) S.ini = r.ini;
        if (r.ok) {
          iframe.srcdoc = r.apercu;
          apercuEtat.className = 'edi-etat ok';
          apercuEtat.textContent = '✓ ' + T('edi_valide');
          dessinerColonnes(r.colonnes);
        } else {
          apercuEtat.className = 'edi-etat alerte';
          apercuEtat.textContent = r.erreur;
          dessinerColonnes(null);
        }
        if (S.mode === 'visuel') dessinerListe();
        if (fini) fini(r);
      })
      .catch(function () {
        if (num !== S.enCours) return;
        apercuEtat.className = 'edi-etat alerte';
        apercuEtat.textContent = T('edi_err_reseau');
      });
  }
  var minuterie = null;
  function planifierApercu(delai) {
    clearTimeout(minuterie);
    minuterie = setTimeout(function () { demanderApercu(); }, delai === undefined ? 500 : delai);
  }

  function dessinerColonnes(col) {
    vider(apercuColonnes);
    S.colonnes = col;
    if (!col || !col.archivage) return;
    var txt = T('edi_colonnes_archivage', { n: col.reponses });
    if (col.ajoutees.length) txt += ' ' + T('edi_colonnes_ajoutees', { liste: col.ajoutees.join(', ') });
    if (col.retirees.length) txt += ' ' + T('edi_colonnes_retirees', { liste: col.retirees.join(', ') });
    if (!col.ajoutees.length && !col.retirees.length) txt += ' ' + T('edi_colonnes_ordre');
    apercuColonnes.appendChild(el('div', { classe: 'edi-avert-colonnes', texte: '⚠ ' + txt }));
  }

  // Messages de l'aperçu : clic sur un champ → sélection ; lien de langue → langue d'aperçu
  window.addEventListener('message', function (ev) {
    if (ev.source !== iframe.contentWindow || !ev.data || !S.modele) return;
    if (ev.data.ins === 'choisir' && S.mode === 'visuel') {
      for (var i = 0; i < S.modele.champs.length; i++) if (S.modele.champs[i].nom === ev.data.nom) { choisir(i); break; }
    } else if (ev.data.ins === 'langue') {
      S.langueAp = ev.data.langue; dessinerChoixApercu(); planifierApercu(0);
    }
  });

  /* ---------- Enregistrement ---------- */
  function enregistrer() {
    if (!verifierNomFormulaire()) { document.getElementById('edi-id').focus(); return; }
    if (S.colonnes && S.colonnes.archivage && !confirm(T('edi_confirmer_archivage', { n: S.colonnes.reponses }))) return;
    var fd = new FormData();
    fd.append('_jeton', D.jeton);
    fd.append('action', 'editeur_enregistrer');
    fd.append('id', S.id);
    fd.append('nouveau', S.nouveau ? '1' : '');
    fd.append('version', S.version);
    fd.append('source', S.mode === 'texte' ? 'texte' : 'modele');
    if (S.mode === 'texte') fd.append('ini', S.ini); else fd.append('modele', nettoyer(S.modele));
    btnEnregistrer.disabled = true;
    dessinerStatut(T('edi_enregistrement'), '');
    envoyer(fd)
      .then(function (r) {
        btnEnregistrer.disabled = false;
        if (!r.ok) {
          dessinerStatut(r.erreur, 'alerte');
          if (r.champ) { S.champErreur = r.champ; if (S.mode === 'visuel') dessinerListe(); }
          return;
        }
        S.modifie = false;
        S.version = r.version;
        dessinerStatut('✓ ' + r.message, 'ok');
        if (S.nouveau) { window.location.href = r.url; }
        planifierApercu(0);
      })
      .catch(function () { btnEnregistrer.disabled = false; dessinerStatut(T('edi_err_reseau'), 'alerte'); });
  }
  window.addEventListener('beforeunload', function (ev) { if (S.modifie) { ev.preventDefault(); ev.returnValue = ''; } });
  /*
   * Raccourcis : Ctrl+S enregistrer (partout) ; Alt+↑ / Alt+↓ déplacer le champ choisi ;
   * hors zones de saisie : ↑ / ↓ choisir le champ précédent / suivant, Suppr supprimer, Inser ajouter.
   */
  document.addEventListener('keydown', function (ev) {
    if ((ev.ctrlKey || ev.metaKey) && (ev.key === 's' || ev.key === 'S')) { ev.preventDefault(); enregistrer(); return; }
    if (S.mode !== 'visuel' || !S.modele) return;
    var saisie = /^(INPUT|TEXTAREA|SELECT)$/.test(ev.target.tagName) || ev.target.isContentEditable;
    var haut = ev.key === 'ArrowUp', bas = ev.key === 'ArrowDown';
    if (ev.altKey && !ev.ctrlKey && (haut || bas) && S.sel >= 0) {
      ev.preventDefault();
      deplacer(S.sel, haut ? -1 : 1);
      focaliserSelection();
    } else if (!saisie && !ev.altKey && !ev.ctrlKey && !ev.metaKey && (haut || bas)) {
      ev.preventDefault();
      choisir(Math.max(-1, Math.min(S.modele.champs.length - 1, S.sel + (haut ? -1 : 1))));
      focaliserSelection();
    } else if (!saisie && ev.key === 'Delete' && S.sel >= 0) {
      ev.preventDefault();
      supprimer(S.sel);
      focaliserSelection();
    } else if (!saisie && ev.key === 'Insert') {
      ev.preventDefault();
      ajouter(liste.querySelector('.edi-ajout select').value);
    }
  });

  /* ---------- Démarrage ---------- */
  construire();
  if (D.erreur) {
    apercuEtat.className = 'edi-etat alerte';
    apercuEtat.textContent = D.erreur;
  }
  if (S.nouveau) { S.modifie = true; dessinerStatut(); verifierNomFormulaire(); }
  planifierApercu(0);
})();
