# Créer un formulaire

[← Retour au README](../README.md) · [Administration](administration.md)

Sommaire : [Principe](#principe) · [Règles d'écriture](#règles-décriture) · [Section formulaire](#section-formulaire) ·
[Types de champs](#types-de-champs) · [Propriétés d'un champ](#propriétés-dun-champ) ·
[Champs conditionnels](#champs-conditionnels) · [Grille](#grille) · [Liens](#liens) · [Courriel de confirmation](#courriel-de-confirmation) · [Exemples](#exemples) · [Diffusion et lien de partage](#diffusion-et-lien-de-partage) ·
[Accès restreint](#accès-restreint) · [Formulaire de commande](#formulaire-de-commande) ·
[Logo](#logo) · [Formulaire multilingue](#formulaire-multilingue) · [Écrire en anglais](#écrire-en-anglais) ·
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
| `logo`      | image affichée à gauche du titre : nom d'un fichier de `prive/images/` (voir [Logo](#logo)) ; `non` = aucun logo | le logo du site (`config.php`), s'il y en a un |
| `logo_texte` | texte de remplacement du logo, lu par les lecteurs d'écran (traduisible : `logo_texte_en`) | — |
| `fichier`   | nom du fichier de réponses (`donnees/<fichier>.csv`) ; deux formulaires ne peuvent pas avoir le même (pensez-y en copiant un `.ini`) | le nom du formulaire |
| `ouverture` | date/heure d'ouverture : `"2026-11-15"` ou `"2026-11-15 09:00"` | — |
| `fermeture` | date/heure de clôture (une date seule = jusqu'à 23 h 59) | — |
| `places`    | nombre maximal de réponses (0 = illimité) | 0 |
| `notifier`  | adresse(s) recevant un courriel à chaque réponse (séparées par des virgules) | — |
| `confirmation` | `oui` : courriel de confirmation au répondant, à l'adresse du premier champ `email` ; ou le nom du champ `email` à utiliser (voir [Courriel de confirmation](#courriel-de-confirmation)) | pas de courriel |
| `confirmation_texte` | texte en tête du courriel de confirmation (traduisible) | le message `merci` |
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
| `montant`    | somme d'argent : le symbole de `devise` (config.php) s'affiche après la zone ; accepte `12`, `12,5`, `12.50`, `1 250,00 €` ; jamais négatif sauf `min` négatif | le montant à deux décimales (`12,50`) |
| `heure`      | sélecteur d'heure ; accepte aussi `9h`, `9h05`, `14:30` ; `min` / `max` = plage horaire (`06:00`, `23:30`) | l'heure `HH:MM` |
| `choix`      | boutons radio : une seule réponse parmi `options` | la réponse choisie |
| `liste`      | liste déroulante : une réponse parmi `options` | la réponse choisie |
| `cases`      | cases à cocher : plusieurs réponses parmi `options` | `Matin \| Midi` |
| `case`       | une case unique (accord, consentement RGPD…) | `oui` / `non` |
| `echelle`    | note sur une ligne (1 à 5 par défaut, ou les valeurs de `options`), avec textes `gauche` / `droite` aux extrémités | la note choisie |
| `grille`     | tableau de choix : une ligne par question (`lignes`), une colonne par réponse (`colonnes`), voir [Grille](#grille) | une colonne `<nom>_<ligne>` par ligne |
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
| `lignes`, `colonnes` | pour `grille` : questions (lignes) et réponses possibles (colonnes), séparées par `\|` |
| `multiple`    | pour `grille` : `oui` = cases à cocher, plusieurs réponses par ligne |
| `gauche`, `droite` | pour `echelle` : textes affichés aux deux bouts (ex. `"Pas du tout"` / `"Tout à fait"`) |
| `defaut`      | valeur pré-remplie (pour `cases`, plusieurs valeurs séparées par `\|`) |
| `exemple`     | texte grisé d'exemple dans la zone de saisie |
| `min`, `max`  | bornes d'un `nombre` ou d'une `date` ; longueur mini/maxi d'un `texte` ou `texte_long` (voir [Limites](#limites-min-et-max)) |
| `entier`      | pour `nombre` : `oui` = nombre entier seulement (pas de virgule) |
| `unique`      | `oui` = refuse une valeur déjà enregistrée (par ex. sur l'e-mail, pour éviter les doubles inscriptions) |
| `si`          | condition d'affichage (voir ci-dessous) |

### Limites (min et max)

```ini
[groupe]
type    = nombre
libelle = "Nombre de personnes (vous compris)"
min     = 1
max     = 30
entier  = oui
```

- **nombre** : `min` et `max` acceptent la virgule ou le point (`2,5`), et les nombres négatifs ; `entier = oui` refuse les
  décimales. Les limites s'affichent sous la question (« Nombre entier. Entre 1 et 30. ») et sont vérifiées à l'envoi
  (« Entre 1 et 30. ») ; sur téléphone, le clavier numérique s'ouvre ;
- **montant** : comme un nombre, avec deux décimales au plus ; sans `min`, un montant négatif est refusé ;
- **heure** : `min = 06:00`, `max = 23:30` ;
- **date** : `min = 2026-12-01`, `max = 2026-12-31` (format `AAAA-MM-JJ`) ; le calendrier du navigateur ne propose que ces dates ;
- **texte**, **texte_long** : nombre de caractères minimum / maximum ;
- une seule des deux limites suffit ; une limite mal écrite, un `min` plus grand que `max` ou une valeur `defaut` hors des
  limites rendent le formulaire invalide, avec un message qui le dit.
| `catalogue`   | pour `commande` : nom du catalogue de produits (`prive/catalogues/<nom>.csv`) |

## Champs conditionnels

Avec `si`, un champ (ou un titre, ou un paragraphe d'info) n'apparaît qu'en fonction d'une réponse
à un champ `choix`, `liste`, `cases`, `echelle`, `case`, `nombre` ou `date` **décrit avant lui** :

| écriture | sens |
|----------|------|
| `si = "profil = Association"` | affiché si « Association » est choisi |
| `si = "profil = Association \| Bénévole"` | affiché si l'une de ces réponses est choisie |
| `si = "profil != Bénévole"` | affiché si « Bénévole » n'est **pas** choisi |
| `si = "profil = Association & stand = Oui"` | `&` = les deux conditions à la fois |
| `si = "covoiturage = oui"` | pour un champ de type `case` (cochée = `oui`, non cochée = `non`) |
| `si = "groupe > 4"` | nombre : `<`, `<=`, `>`, `>=`, et aussi `=` / `!=` (virgule ou point : `2,5`) |
| `si = "arrivee < 2026-12-12"` | date, au format `AAAA-MM-JJ` |
| `si = "depart < 07:00"` · `si = "transport > 100"` | heure `HH:MM` · montant |
| `si = "satisfaction <= 2"` | échelle de notes, si toutes ses notes sont des nombres |
| `si = "hebergement = Oui & groupe > 4"` | les deux à la fois : choix **et** comparaison |
| `si = "hebergement = Oui \|\| groupe > 4"` | `\|\|` = **au moins une** des conditions (OU) — à activer dans `config.php` |

Une comparaison (`<`, `<=`, `>`, `>=`) ne porte que sur un **seul** nombre ou une seule date. **Tant que le nombre (ou la date)
n'est pas saisi, la condition est fausse** : le champ reste masqué, même avec `!=`. À l'écran, l'affichage change pendant la frappe.

Le **OU** (`||`) est désactivé par défaut : mettez `'conditions_ou' => true` dans `prive/config.php` pour l'autoriser.
Une même condition est soit toute en `&` (toutes vraies), soit toute en `||` (au moins une vraie) : le mélange est refusé.
Une condition sur un champ masqué est fausse. Dans l'éditeur, un choix « Toutes les conditions (ET) / Au moins une (OU) »
apparaît dès qu'il y a deux conditions (si le OU est activé).

Un champ masqué n'est jamais obligatoire et n'est pas enregistré (colonne vide), même si une valeur
est envoyée de force. Sans JavaScript, tous les champs s'affichent mais les mêmes règles s'appliquent à l'enregistrement.

## Grille

Une **grille** pose la même question pour plusieurs lignes (jours, ateliers…) dans un tableau, un bouton par case :

```ini
[vehiculer]
type        = grille
libelle     = "Si des convives ne sont pas véhiculés, êtes-vous disponible pour les véhiculer ?"
lignes      = "Vendredi | Samedi | Dimanche"
colonnes    = "Oui | Non"
obligatoire = oui
si          = "vehicule = Oui"
```

- **une réponse par ligne** (boutons radio) ; avec `multiple = oui`, des **cases à cocher** et plusieurs réponses par ligne
  (ex. repas : déjeuner et dîner) ;
- `obligatoire = oui` : **chaque ligne** doit avoir une réponse ; les lignes oubliées sont signalées en rouge ;
- **fichier de réponses** : une colonne par ligne, nommée `<champ>_<ligne>` en minuscules sans accents ni espaces
  (`vehiculer_vendredi`, `vehiculer_samedi`, `vehiculer_dimanche`), contenant la réponse (`Oui`), ou les réponses
  séparées par `|` avec `multiple = oui`. Renommer une ligne change sa colonne (l'ancien fichier est alors archivé,
  voir [Modifier un formulaire déjà utilisé](#modifier-un-formulaire-déjà-utilisé)) ;
- dans la notification par courriel et le message d'une commande (`{vehiculer}`), la grille est résumée « Vendredi : Oui », une ligne par jour ;
- multilingue : `lignes_en`, `colonnes_en`, avec autant d'éléments que `lignes` et `colonnes` ;
- sur téléphone, les lignes s'affichent l'une sous l'autre avec leurs réponses ;
- une grille peut être conditionnelle (`si = …`), mais un autre champ ne peut pas encore dépendre de la réponse à une grille.

## Liens

Les textes affichés peuvent contenir des liens, dans `intro`, `merci`, `aide`, le `libelle` d'un `info` et celui d'une `case` :

```ini
intro = "Programme et plan d'accès : [site de la rencontre](https://exemple.org/rencontre)."

[reglement]
type        = case
libelle     = "J'ai lu le [règlement](https://exemple.org/reglement) et je l'accepte"
obligatoire = oui
```

- `[texte du lien](https://…)` affiche « texte du lien » cliquable ; une adresse écrite seule (`https://exemple.org`) devient
  aussi un lien ; `mailto:contact@exemple.org` est accepté ;
- seules les adresses `https://`, `http://` et `mailto:` sont reconnues (pas de `javascript:`…) ; tout autre balisage
  est affiché tel quel. Les liens s'ouvrent dans un nouvel onglet, pour ne pas perdre la saisie en cours ;
- dans les courriels (notification, confirmation), `[texte](adresse)` devient « texte (adresse) ».

## Courriel de confirmation

Le répondant peut recevoir un courriel récapitulant sa réponse :

```ini
[formulaire]
confirmation       = oui      ; ou le nom du champ email à utiliser : confirmation = email_contact
confirmation_texte = "Merci, votre inscription est enregistrée. Programme : https://exemple.org/programme"
```

- il faut un champ de type `email` (le premier, avec `oui`) ; le courriel part à l'adresse saisie, après l'enregistrement ;
- contenu : `confirmation_texte` (sinon le message `merci`), le numéro de réponse (si `afficher_numero`), puis toutes les
  réponses, **dans la langue du répondant** ; après une modification (accès restreint `modifiable`), le sujet l'indique ;
- la page de remerciement signale l'envoi ; « Répondre » au courriel écrit à la première adresse de `notifier` ;
- garde-fous contre les robots qui saisiraient l'adresse d'un tiers : au plus un courriel par adresse et par formulaire
  toutes les 10 minutes, et au plus `confirmation_max_heure` (30) courriels par heure pour tout le site ;
- `'confirmation' => false` dans `config.php` coupe ces courriels pour tout le site (une alerte le rappelle dans `admin.php`) ;
- l'envoi utilise `mail()` de l'hébergeur, comme les notifications : réglez `expediteur` sur une adresse de votre domaine,
  sinon les messages risquent de finir dans les indésirables (voir [Réglages](administration.md#réglages)).

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
| `frais.ini` | remboursement de frais : heures de départ et de retour (plage horaire), montants (avec maximum), infos conditionnelles (`depart < 07:00`, `transport > 100`) |
| `rencontre.ini` | rencontre sur trois jours : grilles de présence, de repas (plusieurs réponses) et de covoiturage (conditionnelle), liens, courriel de confirmation |

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
- le fichier des réponses contient deux colonnes de plus, `acces` (identifiant) et `acces_nom` (nom donné à l'accès, à défaut son e-mail ou son téléphone) :
  on sait qui a répondu quoi ;
- un formulaire à accès restreint n'apparaît **jamais** dans la liste publique, et sans lien valide l'adresse répond « Page introuvable » ;
- `places` limite le nombre de personnes qui répondent ; une modification ne compte pas comme une nouvelle réponse ;
- en mode `personnel`, la connexion dure 2 heures (lien « se déconnecter » sur la page) ; après 10 mots de passe faux,
  l'accès est bloqué 15 minutes ;
- **champs déjà connus** : si l'accès a été créé avec un nom, un e-mail ou un téléphone (« Marie Durand ; marie@exemple.fr »),
  ces informations pré-remplissent le champ `nom` (à défaut le premier texte court), le premier champ `email` et le premier
  champ `telephone` du formulaire. Pour cette personne, ces champs ne sont **plus obligatoires** : laissés vides, ils
  prennent la valeur connue ; elle peut aussi les corriger. Un champ affiché sous condition (`si`) n'est jamais pré-rempli.

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
- **Accès réservé aux clients (facultatif)** : avec `acces = lien` (ou `personnel`), chaque client reçoit un lien personnel ;
  ses nom, téléphone et e-mail, donnés à la création des accès, sont déjà remplis et ne sont plus obligatoires.
  Avec `modifiable = oui` (défaut), il retrouve sa commande pré-remplie et peut la modifier ; si elle était déjà préparée,
  la fiche de préparation le signale. Sans `acces`, le formulaire reste ouvert à tous. Dans l'éditeur : Réglages du formulaire
  → **Diffusion et accès** → **Qui peut répondre**.
- Un formulaire ne peut contenir qu'**un** champ `commande`. Le montant affiché est indicatif : le prix définitif
  (pesée, produit manquant, remise) se fixe dans l'administration.

Exemple complet : `prive/formulaires/panier.ini` et le catalogue fictif `prive/catalogues/legumes.csv`.

## Logo

Une image (logo de l'association, de l'entreprise…) peut s'afficher **à gauche du titre** du formulaire, et aussi sur la page
de remerciement et la page de connexion des accès restreints :

```ini
[formulaire]
titre      = "Panier de la semaine"
logo       = "exemple-panier.png"
logo_texte = "Les jardins de l'exemple"
```

- l'image se range dans `prive/images/` : rubrique **Images** de `admin.php`, bouton d'envoi de l'éditeur, ou FTP
  (voir [Images](administration.md#images)) ; elle est servie par `index.php`, la zone privée reste fermée ;
- formats acceptés : **PNG, JPEG, GIF, WebP**, 1 Mo et 4000 × 4000 pixels au plus. Le **SVG est refusé** (il peut contenir du script) ;
- affichée sur 80 pixels de haut au plus (56 sur téléphone) : prévoir une image d'environ 160 pixels de haut pour un rendu net ;
- sans clé `logo`, c'est le **logo du site** qui s'affiche (clé `logo` de `config.php`, aussi sur la liste des formulaires) ;
  `logo = non` le retire pour ce formulaire ;
- `logo_texte` décrit l'image pour les lecteurs d'écran ; laissez-le vide si le titre suffit ;
- un nom d'image incorrect (chemin, extension inconnue) rend le formulaire invalide ; une image absente est simplement
  omise, avec une alerte dans `admin.php`.

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
| `lignes` / `colonnes` | `rows` / `columns` | | `grille` | `grid` |
| `notifier` | `notify` | | `texte`, `texte_long` | `text`, `textarea` |
| `confirmation` | `confirm` | | | |
| `diffusion` | `listed` | | `telephone`, `nombre`, `montant`, `heure` | `phone`, `number`, `amount`, `time` |
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

Les erreurs d'écriture les plus courantes sont signalées avec leur numéro de ligne :

| message | correction |
|---------|------------|
| « ligne 12 : « [c_8_detail » — il manque le crochet fermant ] » | écrire `[c_8_detail]` |
| « il manque le crochet ouvrant [ » | une ligne `nom]` : écrire `[nom]` |
| « le texte entre guillemets qui commence ici n'est jamais refermé » | ajouter le `"` final du texte commencé à cette ligne |
| « teste « (Samedi matin », qui n'est pas une option » | dans `si`, pas de parenthèses ; valeurs écrites exactement comme dans `options` |
| « « > » ne s'applique qu'à un nombre, une date ou une échelle » | comparaison sur un champ texte ou choix : utiliser `=` / `!=` |

Après modification d'un fichier par FTP, attendez quelques secondes avant de recharger la page
(certains hébergeurs gardent les fichiers PHP en cache un court instant).
