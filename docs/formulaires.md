# Créer un formulaire

[← Retour au README](../README.md) · [Administration](administration.md)

Sommaire : [Principe](#principe) · [Règles d'écriture](#règles-décriture) · [Section formulaire](#section-formulaire) ·
[Types de champs](#types-de-champs) · [Propriétés d'un champ](#propriétés-dun-champ) ·
[Champs conditionnels](#champs-conditionnels) · [Exemples](#exemples) · [Diffusion et lien de partage](#diffusion-et-lien-de-partage) ·
[Accès restreint](#accès-restreint) · [Formulaire de commande](#formulaire-de-commande) ·
[Formulaire multilingue](#formulaire-multilingue) · [Écrire en anglais](#écrire-en-anglais) ·
[Modifier un formulaire déjà utilisé](#modifier-un-formulaire-déjà-utilisé) · [Erreurs](#erreurs)

## Principe

Un formulaire = un fichier `prive/formulaires/<nom>.ini`, en texte brut **encodé en UTF-8**
(Bloc-notes, Notepad++, gedit, Kate…). Son adresse est `index.php?f=<nom>`.
Le `<nom>` ne contient que des lettres non accentuées, des chiffres, `-` et `_`.

Le fichier se dépose par FTP, s'envoie depuis `admin.php`, ou se crée et se modifie sans rien écrire dans
l'**éditeur de formulaires** (voir [Éditeur de formulaires](administration.md#éditeur-de-formulaires)) :
tout ce qui est décrit dans cette page s'y règle avec des cases, des listes et un aperçu en direct.
Chaque formulaire a son propre fichier de réponses, `prive/donnees/<nom>.csv`.

`index.php` affiche la liste des formulaires **diffusés** (`diffusion = oui`, le défaut) ; s'il n'y en a qu'un,
il l'affiche directement. Les formulaires non diffusés s'ouvrent par leur [lien de partage](#diffusion-et-lien-de-partage).

## Règles d'écriture

- Une section `[formulaire]` donne les réglages généraux, puis **une section par champ**, dans l'ordre d'affichage.
- Le nom de section d'un champ (`[email]`, `[nom_prenom]`…) devient le **nom de la colonne** dans le fichier CSV :
  lettres non accentuées, chiffres et `_`, commençant par une lettre. `numero`, `date`, `langue`, `acces` et `acces_nom` sont réservés.
- **Mettez les textes entre guillemets doubles** : `libelle = "Nom de l'association"`.
  Un guillemet double dans un texte s'écrit `\"`. Un texte entre guillemets peut tenir sur plusieurs lignes.
- Une ligne commençant par `;` est un commentaire.
- Pour oui/non : `oui`, `1`, `yes`, `true` = oui ; vide, `non`, `0` = non.
- La suite de caractères `${` est interdite (elle ferait lire des variables du serveur).

## Section formulaire

Réglages de la section `[formulaire]` :

| clé         | rôle | défaut |
|-------------|------|--------|
| `titre`     | titre affiché en haut de la page | le nom du fichier |
| `intro`     | texte d'introduction | — |
| `merci`     | message affiché après l'envoi | « Merci, votre réponse est enregistrée. » |
| `bouton`    | texte du bouton d'envoi | « Envoyer » |
| `fichier`   | nom du fichier de réponses (`donnees/<fichier>.csv`) ; deux formulaires ne peuvent pas avoir le même (pensez-y en copiant un `.ini`) | le nom du formulaire |
| `ouverture` | date/heure d'ouverture : `"2026-11-15"` ou `"2026-11-15 09:00"` | — |
| `fermeture` | date/heure de clôture (une date seule = jusqu'à 23 h 59) | — |
| `places`    | nombre maximal de réponses (0 = illimité) | 0 |
| `notifier`  | adresse(s) recevant un courriel à chaque réponse (séparées par des virgules) | — |
| `diffusion` | `oui` = listé sur la page d'accueil ; `non` = accessible uniquement par le [lien de partage](#diffusion-et-lien-de-partage) | oui |
| `acces`     | `personnel` = accès restreint par lien personnel + mot de passe ; `lien` = par lien personnel seul (voir [Accès restreint](#accès-restreint)) | — (accès libre) |
| `modifiable` | accès restreint : `oui` = chaque personne peut revenir modifier sa réponse ; `non` = une réponse définitive | oui |
| `afficher_numero` | `non` = ne pas montrer le numéro de réponse après l'envoi (utile pour un sondage anonyme) | oui |
| `langue`    | langue de l'interface pour ce formulaire (`fr`, `en`…) : boutons, messages d'erreur, valeur des cases | celle de `config.php` |
| `langues`   | formulaire multilingue : langues proposées, ex. `"fr \| en"` (voir [Formulaire multilingue](#formulaire-multilingue)) | — |
| `message`   | formulaire de commande : modèle du message envoyé au client quand sa commande est prête (voir [Formulaire de commande](#formulaire-de-commande)) | texte par défaut |

## Types de champs

| `type`       | affichage | valeur enregistrée |
|--------------|-----------|--------------------|
| `texte`      | ligne de texte | le texte |
| `texte_long` | zone de texte sur plusieurs lignes | le texte |
| `email`      | adresse électronique (format vérifié) | l'adresse |
| `telephone`  | numéro de téléphone (format vérifié) | le numéro |
| `nombre`     | nombre (bornes `min` / `max`) | le nombre |
| `date`       | calendrier (bornes `min` / `max` au format `AAAA-MM-JJ`) | `AAAA-MM-JJ` |
| `choix`      | boutons radio : une seule réponse parmi `options` | la réponse choisie |
| `liste`      | liste déroulante : une réponse parmi `options` | la réponse choisie |
| `cases`      | cases à cocher : plusieurs réponses parmi `options` | `Matin \| Midi` |
| `case`       | une case unique (accord, consentement RGPD…) | `oui` / `non` |
| `echelle`    | note sur une ligne (1 à 5 par défaut, ou les valeurs de `options`), avec textes `gauche` / `droite` aux extrémités | la note choisie |
| `commande`   | tableau des produits disponibles d'un `catalogue`, quantité par produit, total estimé (un seul par formulaire) | le détail lisible + une colonne `<nom>_total` |
| `titre`      | intertitre de section (rien à saisir) | — (pas de colonne) |
| `info`       | paragraphe d'information (rien à saisir) | — (pas de colonne) |

## Propriétés d'un champ

| clé           | rôle |
|---------------|------|
| `type`        | voir ci-dessus (défaut : `texte`) |
| `libelle`     | la question affichée |
| `aide`        | petit texte d'aide sous la question |
| `obligatoire` | `oui` = réponse exigée (pour `case` : la case doit être cochée) |
| `options`     | réponses possibles pour `choix`, `liste`, `cases`, `echelle`, séparées par `\|` : `"Oui \| Non \| Peut-être"` |
| `gauche`, `droite` | pour `echelle` : textes affichés aux deux bouts (ex. `"Pas du tout"` / `"Tout à fait"`) |
| `defaut`      | valeur pré-remplie (pour `cases`, plusieurs valeurs séparées par `\|`) |
| `exemple`     | texte grisé d'exemple dans la zone de saisie |
| `min`, `max`  | bornes d'un `nombre` ou d'une `date` ; longueur mini/maxi d'un `texte` ou `texte_long` |
| `unique`      | `oui` = refuse une valeur déjà enregistrée (par ex. sur l'e-mail, pour éviter les doubles inscriptions) |
| `si`          | condition d'affichage (voir ci-dessous) |
| `catalogue`   | pour `commande` : nom du catalogue de produits (`prive/catalogues/<nom>.csv`) |

## Champs conditionnels

Avec `si`, un champ (ou un titre, ou un paragraphe d'info) n'apparaît qu'en fonction d'une réponse
à un champ `choix`, `liste`, `cases`, `echelle` ou `case` **décrit avant lui** :

| écriture | sens |
|----------|------|
| `si = "profil = Association"` | affiché si « Association » est choisi |
| `si = "profil = Association \| Bénévole"` | affiché si l'une de ces réponses est choisie |
| `si = "profil != Bénévole"` | affiché si « Bénévole » n'est **pas** choisi |
| `si = "profil = Association & stand = Oui"` | `&` = les deux conditions à la fois |
| `si = "covoiturage = oui"` | pour un champ de type `case` (cochée = `oui`, non cochée = `non`) |

Un champ masqué n'est jamais obligatoire et n'est pas enregistré (colonne vide), même si une valeur
est envoyée de force. Sans JavaScript, tous les champs s'affichent mais les mêmes règles s'appliquent à l'enregistrement.

## Exemples

### Inscription

```ini
[formulaire]
titre     = "Inscription à la réunion du 5 décembre"
fermeture = "2026-12-03"
places    = 40

[nom]
type        = texte
libelle     = "Nom et prénom"
obligatoire = oui

[email]
type        = email
libelle     = "Adresse électronique"
obligatoire = oui
unique      = oui

[repas]
type        = choix
libelle     = "Restez-vous au repas ?"
options     = "Oui | Non"
obligatoire = oui

[regime]
type    = liste
libelle = "Régime particulier"
options = "Aucun | Végétarien | Sans gluten"
si      = "repas = Oui"
```

### Sondage

`prive/formulaires/sondage.ini` est un questionnaire de satisfaction anonyme :
notes sur une échelle (`echelle`, de 1 à 5 ou de 0 à 10), liste déroulante « Comment avez-vous connu la journée ? »
avec un champ « Précisez » qui n'apparaît que pour « Autre », choix multiples et commentaires libres.
Aucun nom ni e-mail n'y est demandé, `afficher_numero = non` évite d'afficher un numéro de réponse,
et `diffusion = non` le réserve aux personnes qui reçoivent le lien.
Les notes arrivent dans le CSV sous forme de nombres : moyennes et graphiques se font directement dans le tableur.

```ini
[satisfaction]
type        = echelle
libelle     = "Globalement, êtes-vous satisfait(e) de la journée ?"
gauche      = "Pas du tout"
droite      = "Tout à fait"
obligatoire = oui
```

### Exemples fournis

| fichier | contenu |
|---------|---------|
| `simple.ini` | exemple minimal, à copier pour démarrer |
| `exemple.ini` | inscription complète à une journée : presque tous les types, conditions, deux listes déroulantes |
| `sondage.ini` | questionnaire de satisfaction anonyme, non diffusé |
| `colloque.ini` | formulaire bilingue français / anglais |
| `meeting-en.ini` | formulaire écrit avec les clés anglaises, interface en anglais |
| `ag-membres.ini` | réponse des membres à une convocation, accès restreint (lien + mot de passe), réponse modifiable |
| `panier.ini` | commande du panier de la semaine d'un maraîcher sur le catalogue `legumes` (données fictives) |

## Diffusion et lien de partage

- `diffusion = oui` (défaut) : le formulaire figure dans la liste de `index.php` et s'ouvre aussi par `index.php?f=<nom>`.
- `diffusion = non` : il n'apparaît **nulle part** ; `index.php?f=<nom>` répond « Page introuvable ».
  Il ne s'ouvre qu'avec son **lien de partage**, qui contient une clé unique :
  `https://votre-site.fr/inscription/index.php?f=sondage&k=352478770464ea7d`.
  Ce lien est affiché dans `admin.php`, avec un bouton **Copier** : envoyez-le aux seules personnes concernées.

La clé est calculée à partir de la clé secrète du site (`prive/donnees/.secret`) et du nom du formulaire :
elle ne peut pas être devinée, elle ne change pas tant que ces deux éléments ne changent pas, et elle diffère d'un site à l'autre.
Pour **révoquer** un lien, renommez le formulaire (nouveau nom de fichier `.ini`) : l'ancien lien cesse de fonctionner.
Supprimer `.secret` change d'un coup les liens de tous les formulaires non diffusés.

L'ancienne écriture `cache = oui` reste acceptée et équivaut à `diffusion = non`.

## Accès restreint

Pour réserver un formulaire à des personnes connues (membres, invités, jury…) et empêcher toute saisie par des robots
ou des inconnus :

```ini
[formulaire]
titre      = "Assemblée générale — réponse des membres"
acces      = personnel     ; ou : lien
modifiable = oui           ; défaut
```

| `acces` | ce que reçoit chaque personne | pour qui |
|---------|-------------------------------|----------|
| `personnel` | un **lien personnel** (`index.php?f=ag&i=K7QF-M2ZD`) **et un mot de passe** de 10 caractères | le plus sûr, surtout si le lien et le mot de passe partent par des canaux différents (e-mail + papier, SMS…) |
| `lien` | un **lien personnel secret** seul (`…&i=K7QF-M2ZD&t=…`) : rien à saisir | le plus simple ; transférer le lien revient à transférer l'accès |

Fonctionnement :

- les accès (un par personne, avec son nom ou son adresse) se créent dans `admin.php`, puis la liste est téléchargée
  et diffusée **hors de l'application** (publipostage, courrier…) — voir [Accès personnels](administration.md#accès-personnels) ;
- **une seule réponse par accès**. Avec `modifiable = oui`, la personne qui revient avec son lien (et son mot de passe)
  retrouve sa réponse pré-remplie et peut la modifier jusqu'à la fermeture : la réponse est **remplacée**, pas ajoutée ;
  avec `modifiable = non`, elle voit « Vous avez déjà répondu » ;
- le fichier des réponses contient deux colonnes de plus, `acces` (identifiant) et `acces_nom` (nom donné à l'accès) :
  on sait qui a répondu quoi ;
- un formulaire à accès restreint n'apparaît **jamais** dans la liste publique, et sans lien valide l'adresse répond « Page introuvable » ;
- `places` limite le nombre de personnes qui répondent ; une modification ne compte pas comme une nouvelle réponse ;
- en mode `personnel`, la connexion dure 2 heures (lien « se déconnecter » sur la page) ; après 10 mots de passe faux,
  l'accès est bloqué 15 minutes.

Exemple complet : `prive/formulaires/ag-membres.ini`.

## Formulaire de commande

Pour prendre des commandes (panier de légumes, pain, colis, repas…) : un champ de type `commande` affiche les produits
**disponibles** d'un catalogue, avec leur prix et leur unité ; le client indique les quantités et voit le **total estimé**
se calculer.

```ini
[formulaire]
titre   = "Panier de la semaine"
message = "Bonjour {nom},
Votre commande n° {numero} est prête :
{detail}
Total à régler : {total}
{note}"

[nom]
type        = texte
libelle     = "Nom et prénom"
obligatoire = oui

[telephone]
type        = telephone
libelle     = "Téléphone"
obligatoire = oui

[panier]
type        = commande
libelle     = "Votre commande"
catalogue   = legumes
obligatoire = oui
```

- **Le catalogue** (`prive/catalogues/legumes.csv`) se gère dans `admin.php` : produits, unités, prix, pas de quantité,
  case « disponible » à cocher chaque semaine ; import depuis un tableur possible. Voir [Catalogues et commandes](administration.md#catalogues-et-commandes).
  Une même denrée peut figurer plusieurs fois avec des unités et des prix différents (carotte à la botte et au kilo, chou chinois, chou frisé…).
- **Le pas** d'un produit (0,5 pour un demi-kilo, 0,1 pour 100 g) fixe la quantité minimale et ses multiples ; sans pas, la quantité est libre.
- **Les prix sont toujours ceux du catalogue** au moment de la commande : un prix modifié dans le navigateur est ignoré.
  Un produit devenu indisponible entre-temps est refusé avec un message clair.
- **Le fichier de réponses** contient une colonne lisible (« Carotte (kg) : 1,5 × 1,00 € = 1,50 € », une ligne par produit)
  et une colonne `<nom>_total` avec le total estimé en nombre. Le détail structuré (pour la préparation) est enregistré
  à part, dans `prive/donnees/commandes/`.
- **Le message** (`message`) est le texte proposé au vendeur pour prévenir le client, à copier dans un SMS ou un e-mail.
  Repères : `{numero}`, `{date}`, `{detail}` (une ligne par produit), `{total}`, `{note}` (note saisie à la préparation)
  et `{nom_du_champ}` pour toute réponse du formulaire (`{nom}`, `{telephone}`, `{retrait}`…). Sans `message`, un texte par défaut est utilisé.
- Avec un **accès restreint** (`acces = lien` ou `personnel`) et `modifiable = oui`, le client retrouve sa commande
  pré-remplie et peut la modifier ; si elle était déjà préparée, la fiche de préparation le signale.
- Un formulaire ne peut contenir qu'**un** champ `commande`. Le montant affiché est indicatif : le prix définitif
  (pesée, produit manquant, remise) se fixe dans l'administration.

Exemple complet : `prive/formulaires/panier.ini` et le catalogue fictif `prive/catalogues/legumes.csv`.

## Formulaire multilingue

```ini
[formulaire]
langues  = "fr | en"            ; la première est la langue de base
titre    = "Colloque du 20 mars"
titre_en = "Symposium, 20 March"

[statut]
type       = choix
libelle    = "Vous êtes :"
libelle_en = "You are:"
options    = "Étudiant | Chercheur | Autre"
options_en = "Student | Researcher | Other"
```

- **Traduire un texte** : ajouter `_en` (ou `_de`, `_es`… selon la langue) à sa clé. Sont traduisibles :
  `titre`, `intro`, `merci`, `bouton` dans `[formulaire]` ; `libelle`, `aide`, `exemple`, `gauche`, `droite`, `options` dans un champ.
  `options_en` doit compter **autant de choix, dans le même ordre**, que `options` (sinon le formulaire est refusé).
  Un texte non traduit s'affiche dans la langue de base.
- **Langue affichée** : celle du navigateur du visiteur si elle fait partie de `langues`, sinon la langue de base.
  Des liens **Français · English** en haut de page permettent d'en changer ; le choix est mémorisé par le navigateur.
  Lien direct vers une langue : `index.php?f=colloque&lang=en`.
- **Valeurs enregistrées** : toujours dans la **langue de base** (`Étudiant` même si le visiteur a coché `Student`),
  pour que la colonne reste homogène et triable ; une colonne `langue` indique la langue utilisée par chaque répondant.
  Les conditions `si` s'écrivent donc aussi avec les valeurs de la langue de base.
- Les langues listées doivent exister dans `prive/lang/` (sinon le formulaire est refusé avec un message clair) ;
  pour en ajouter une, voir [Langues de l'interface](administration.md#langues-de-linterface).

Exemple complet : `prive/formulaires/colloque.ini` (champs conditionnels, liste déroulante, échelle, cases, tout traduit).

Un formulaire **dans une seule autre langue** que celle du site n'a pas besoin de `langues` :
`langue = en` suffit pour que boutons et messages soient en anglais.

## Écrire en anglais

Toutes les clés et tous les types ont un équivalent anglais, mélangeable avec le français :

| français | anglais | | français | anglais |
|----------|---------|-|----------|---------|
| `[formulaire]` | `[form]` | | `libelle` | `label` |
| `titre` | `title` | | `aide` | `help` |
| `merci` | `thanks` | | `obligatoire` | `required` |
| `bouton` | `button` | | `defaut` | `default` |
| `fichier` | `file` | | `exemple` | `placeholder` |
| `ouverture` / `fermeture` | `opens` / `closes` | | `gauche` / `droite` | `left` / `right` |
| `places` | `limit` | | `si` | `if` |
| `notifier` | `notify` | | `texte`, `texte_long` | `text`, `textarea` |
| `diffusion` | `listed` | | `telephone`, `nombre` | `phone`, `number` |
| `afficher_numero` | `show_number` | | `choix`, `liste` | `choice` (ou `radio`), `dropdown` (ou `select`) |
| `acces` = `personnel` / `lien` | `access` = `personal` / `link` | | `modifiable` | `editable` |
| `langue` / `langues` | `language` / `languages` | | `cases`, `case` | `checkboxes`, `consent` |
| | | | `echelle`, `titre` | `scale`, `heading` |

Voir `prive/formulaires/meeting-en.ini`. Avec `language = en`, les boutons et messages sont en anglais et une case cochée
est enregistrée `yes` / `no` (au lieu de `oui` / `non`).

## Modifier un formulaire déjà utilisé

- Changer un **libellé**, une aide, l'ordre des options ou les dates ne touche pas aux réponses déjà reçues.
- Ajouter, retirer ou renommer un **champ** change les colonnes : à la réponse suivante, l'ancien fichier est conservé sous le nom
  `<fichier>-ancien-AAAAMMJJ-HHMMSS.csv` et un nouveau fichier démarre (les deux restent téléchargeables depuis `admin.php`).
- Renommer une **option** n'archive rien, mais les anciennes réponses gardent l'ancien texte.

## Erreurs

Si le fichier contient une erreur (type inconnu, condition vers un champ inexistant, guillemet oublié…),
la page affiche « Formulaire introuvable ou mal décrit » ; le détail de l'erreur est indiqué sur `admin.php`
(ou sur la page elle-même avec `'debug' => true` dans `config.php`). Un fichier envoyé par `admin.php` est vérifié
avant d'être installé : en cas d'erreur, il est refusé et rien ne change.

Après modification d'un fichier par FTP, attendez quelques secondes avant de recharger la page
(certains hébergeurs gardent les fichiers PHP en cache un court instant).
