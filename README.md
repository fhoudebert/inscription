# inscription — formulaires d'inscription et sondages pour hébergement mutualisé

Recueillir des inscriptions, des réponses à un sondage ou des **commandes** sur un **hébergement mutualisé** (Free, OVH, o2switch, Infomaniak, Ionos…),
sans base de données ni programmation :

- chaque formulaire est **décrit dans un fichier texte** (`.ini`), qu'on peut écrire à la main ou créer et modifier
  dans un **éditeur visuel** avec aperçu en direct ;
- les réponses s'ajoutent à un **fichier CSV** qui s'ouvre dans Excel, LibreOffice Calc ou [GridCraft](https://getartcraft.com/apps/gridcraft) ;
- un formulaire est **diffusé** (listé sur la page d'accueil), **non diffusé** (ouvert seulement par un lien de partage)
  ou à **accès restreint** : chaque personne invitée reçoit un lien personnel (et un mot de passe), répond une fois
  et peut modifier sa réponse — les robots et les inconnus ne peuvent rien envoyer ;
- une **page d'administration** protégée par mot de passe permet de télécharger les réponses, copier les liens de partage,
  créer les accès personnels, créer, modifier ou supprimer des formulaires ;
- un formulaire peut prendre des **commandes** sur un catalogue de produits (prix, unité, disponibilité) avec total estimé ;
  l'admin donne les quantités à préparer, permet de fixer le prix définitif de chaque commande et prépare le message
  à copier dans un SMS ou un e-mail ;
- un **logo** peut s'afficher à gauche du titre (logo du site, ou propre à chaque formulaire) ;
- les textes peuvent contenir des **liens**, et le répondant peut recevoir un **courriel de confirmation** ;
- l'interface est en **français et en anglais**, et un formulaire peut être **multilingue** (langue du navigateur, changement par un lien).

Prérequis : **PHP 7.4 ou plus récent**. Aucune extension particulière, aucune session PHP, aucune base MySQL.

## Documentation

| page | contenu |
|------|---------|
| **[Créer un formulaire](docs/formulaires.md)** | syntaxe des fichiers `.ini`, types de champs, champs conditionnels, diffusion, accès restreint, formulaire de commande, formulaires multilingues, exemples |
| **[Administration](docs/administration.md)** | page `admin.php`, éditeur de formulaires, récupération des réponses, gestion des formulaires, accès personnels, catalogues et commandes, images (logos), zone privée, réglages, sécurité, dépannage, langues de l'interface |

## Installation en bref

1. Copiez le dossier `inscription/` par FTP dans la partie publique du site (`www/`, `public_html/`, racine chez Free).
   Le formulaire sera à l'adresse `https://votre-site.fr/inscription/`. Vous pouvez aussi copier le contenu du dossier vers un dossier de votre choix. (Le formulaire sera à l'adresse `https://votre-site.fr/votredossier/`.)
2. Vérifiez que PHP peut écrire dans `prive/donnees/` (et dans `prive/formulaires/` pour gérer les formulaires depuis l'admin) :
   droits `755`, ou `775`/`777` si l'enregistrement échoue.
3. Ouvrez `admin.php`, choisissez un mot de passe et collez la ligne obtenue dans `prive/config.php`.
4. Connectez-vous à `admin.php` : le **test automatique** doit indiquer en vert que les données ne sont pas lisibles depuis le web.
   Sinon, voir [Zone privée](docs/administration.md#zone-privée).
5. Créez votre formulaire dans l'éditeur (`admin.php` → **Créer un formulaire**) ou adaptez un exemple
   de `prive/formulaires/`, puis faites une réponse d'essai.

Détails : [Administration → Installation](docs/administration.md#installation).

## Organisation des fichiers

```
inscription/                 ← dossier à copier sur le site
├── index.php                ← page publique : liste des formulaires et formulaires
├── admin.php                ← administration
├── emplacement.php          ← indique où se trouve la zone privée
└── prive/                   ← ZONE PRIVÉE, interdite d'accès web (.htaccess)
    ├── config.php           ← réglages
    ├── lib.php              ← le moteur
    ├── editeur.js, .css     ← l'éditeur de formulaires de admin.php
    ├── lang/                ← textes de l'interface (fr.php, en.php)
    ├── formulaires/         ← un fichier .ini par formulaire (+ exemples)
    ├── catalogues/          ← catalogues de produits des formulaires de commande
    ├── images/              ← logos affichés à gauche du titre (servis par index.php)
    └── donnees/             ← un fichier .csv de réponses par formulaire
```

## Un formulaire en quelques lignes

```ini
[formulaire]
titre     = "Inscription à la réunion du 5 décembre"
fermeture = "2026-12-03"
places    = 40

[nom]
type        = texte
libelle     = "Nom et prénom"
obligatoire = oui

[repas]
type    = choix
libelle = "Restez-vous au repas ?"
options = "Oui | Non"
```

Déposé sous `prive/formulaires/reunion.ini` (par FTP, par téléversement ou créé dans l'éditeur de `admin.php`), il est en ligne à l'adresse
`index.php?f=reunion` ; les réponses arrivent dans `prive/donnees/reunion.csv`.

Exemples fournis : `simple.ini` (minimal), `exemple.ini` (inscription complète), `sondage.ini` (satisfaction, non diffusé),
`colloque.ini` (bilingue français / anglais), `meeting-en.ini` (écrit en anglais), `ag-membres.ini` (accès restreint par mot de passe),
`panier.ini` (commande du panier de la semaine d'un maraîcher, sur le catalogue fictif `legumes`),
`rencontre.ini` (grilles de choix : présence, repas, covoiturage sur trois jours).
Tout est expliqué dans [Créer un formulaire](docs/formulaires.md).

## Données personnelles

Les réponses sont des données personnelles : elles restent dans la zone privée (`prive/`), protégée par `.htaccess`
et déplaçable hors de la partie publique du site. Pensez à supprimer les réponses quand elles ne sont plus utiles (RGPD).
Voir [Zone privée](docs/administration.md#zone-privée) et [Sécurité](docs/administration.md#sécurité).
