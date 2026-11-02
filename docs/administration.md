# Administration

[← Retour au README](../README.md) · [Créer un formulaire](formulaires.md)

Sommaire : [Installation](#installation) · [La page admin.php](#la-page-adminphp) · [Récupérer les réponses](#récupérer-les-réponses) ·
[Gérer les formulaires](#gérer-les-formulaires) · [Zone privée](#zone-privée) · [Réglages](#réglages) ·
[Langues de l'interface](#langues-de-linterface) · [Sécurité](#sécurité) · [Dépannage](#dépannage)

## Installation

1. Copiez le dossier `inscription/` par FTP (FileZilla…) dans la partie publique de votre site :
   `www/`, `public_html/`, `htdocs/` ou la racine chez Free.
   Le formulaire est alors à l'adresse `https://votre-site.fr/inscription/`.
   Vous pouvez renommer le dossier (`inscriptions`, `journee2026`…) : l'adresse change en conséquence.
2. Le dossier `prive/donnees/` doit être **accessible en écriture** par PHP. C'est le cas par défaut chez la
   plupart des hébergeurs ; sinon, dans FileZilla : clic droit sur `donnees` → *Droits d'accès au fichier* → `755`
   (ou `775`/`777` si l'enregistrement échoue encore). Pour ajouter des formulaires depuis `admin.php`,
   il en va de même pour `prive/formulaires/`.
3. Ouvrez `https://votre-site.fr/inscription/admin.php` et choisissez un mot de passe : la page affiche une ligne
   à coller dans `prive/config.php` (à la place de `'admin_mot_de_passe' => '',`). Renvoyez `config.php` sur le serveur.
   Le mot de passe lui-même n'est enregistré nulle part, seulement son empreinte.
4. Connectez-vous à `admin.php` : un **test automatique** vérifie que les données ne sont pas lisibles
   depuis le web. Si un message rouge apparaît, suivez [Zone privée](#zone-privée).
5. Supprimez ou adaptez les exemples de `prive/formulaires/`, puis faites une réponse d'essai.

Les fichiers commençant par un point (`.htaccess`) sont parfois masqués ou non envoyés par le client FTP :
dans FileZilla, menu *Serveur* → *Forcer l'affichage des fichiers cachés*, et vérifiez qu'ils sont présents sur le serveur.

Particularité Free (pages perso) : rien à faire de spécial, l'outil n'utilise pas de session PHP
(donc pas besoin du dossier `sessions/`).

## La page admin.php

Adresse : `https://votre-site.fr/inscription/admin.php` (mot de passe, connexion valable 4 heures).

En haut de page : le résultat du [test de protection](#zone-privée), les liens **English · Français** et **Se déconnecter**.
Puis, pour chaque formulaire :

- titre, nom du fichier, étiquette **diffusé** ou **non diffusé — accès par lien**, langues s'il est multilingue ;
- nombre de réponses (et de places), état : ouvert, pas encore ouvert, clos, complet ;
- **lien de partage** avec boutons **Copier** et **Ouvrir** — c'est le lien à envoyer pour un formulaire non diffusé
  (voir [Diffusion et lien de partage](formulaires.md#diffusion-et-lien-de-partage)) ;
- **Télécharger** le fichier de réponses (`.csv`), **Voir** les réponses dans un tableau, **Télécharger le descripteur (.ini)** ;
- **Supprimer le formulaire**, **Supprimer les réponses** (voir [Gérer les formulaires](#gérer-les-formulaires)).

Un formulaire mal écrit apparaît avec le détail de l'erreur, pour le corriger.
En bas : l'ajout d'un formulaire, et la rubrique « Autres fichiers » (anciennes versions de fichiers de réponses,
réponses des formulaires supprimés).

**Désactiver la page** : `'admin_actif' => false` dans `prive/config.php`. `admin.php` répond alors « Page introuvable »
(vous pouvez aussi supprimer le fichier) ; tout se fait par FTP. Le test automatique n'étant plus affiché,
vérifiez la protection à la main (voir [Zone privée](#zone-privée)).

## Récupérer les réponses

- **Par `admin.php`** : bouton **Télécharger** de chaque formulaire.
- **Par FTP** : dossier `prive/donnees/`, un fichier `<fichier>.csv` par formulaire.

Format des fichiers :

- encodage UTF-8 avec BOM, séparateur `;` (modifiable dans `config.php`) : un double-clic l'ouvre correctement dans
  Excel (version française), LibreOffice Calc ou [GridCraft](https://getartcraft.com/apps/gridcraft) (tableur libre et gratuit) ;
  dans LibreOffice, choisissez « UTF-8 » et « point-virgule » si la question est posée ;
- colonnes : `numero` (numéro de réponse aléatoire, ex. `K7QF-M2ZD`, aussi montré au répondant), `date` (date et heure d'envoi),
  `langue` (formulaires multilingues seulement : langue du répondant), puis un champ par colonne, dans l'ordre du descripteur ;
- les réponses commençant par `=`, `+`, `-` ou `@` sont précédées d'une apostrophe, pour qu'Excel ne les exécute pas comme formules.

Fichiers `<fichier>-ancien-AAAAMMJJ-HHMMSS.csv` : anciennes réponses mises de côté quand les champs d'un formulaire ont changé
(voir [Modifier un formulaire déjà utilisé](formulaires.md#modifier-un-formulaire-déjà-utilisé)).

**Corriger ou supprimer une réponse** : téléchargez le fichier par FTP, modifiez-le (en gardant le format CSV,
séparateur `;`, UTF-8), renvoyez-le. Pour tout effacer : supprimez le fichier par FTP, ou bouton **Supprimer les réponses**.
Pensez à supprimer les données quand elles ne sont plus utiles (RGPD).

## Gérer les formulaires

Depuis `admin.php`, sans FTP (réglage `'admin_gestion' => true`, par défaut) :

- **Ajouter** : rubrique « Ajouter ou remplacer un formulaire », choisir un fichier `.ini` sur votre ordinateur.
  Le nom dans l'adresse est celui du fichier (`reunion.ini` → `index.php?f=reunion`), ou celui que vous indiquez.
- **Modifier** : bouton **Télécharger le descripteur (.ini)**, corriger le fichier, le renvoyer en cochant **Remplacer**.
  L'ancienne version est conservée dans `prive/formulaires/supprimes/`. Si des champs ont été ajoutés, retirés ou renommés,
  la page prévient que le fichier de réponses actuel sera archivé à la prochaine réponse.
- **Supprimer un formulaire** : lien **Supprimer le formulaire**, cocher **Je confirme**. Le descripteur est déplacé dans
  `prive/formulaires/supprimes/` (récupérable par FTP) ; les réponses déjà reçues sont **conservées** et restent téléchargeables
  (rubrique « Autres fichiers »).
- **Supprimer des réponses** : lien **Supprimer les réponses**, cocher **Je confirme** : le fichier CSV est **effacé définitivement**.

Garde-fous :
- le fichier envoyé est **entièrement vérifié avant d'être installé** (syntaxe, types, options, conditions, langues, UTF-8) ;
  en cas d'erreur, rien n'est modifié et le message indique l'erreur (champ ou ligne en cause) ;
- 200 Ko au plus, nom limité à lettres non accentuées, chiffres, `-` et `_`, extension `.ini` imposée :
  le fichier ne peut jamais devenir un programme exécutable ;
- la suite de caractères `${` est refusée (elle permettrait de lire des variables du serveur) ;
- chaque action est protégée par un jeton lié à la connexion (une page piégée ne peut pas la déclencher à votre place) ;
- PHP doit pouvoir écrire dans `prive/formulaires/` ; sinon, la page l'indique et la gestion reste possible par FTP.

Avec `'admin_gestion' => false`, `admin.php` ne sert plus qu'à consulter et télécharger : les boutons d'ajout et de suppression disparaissent.

## Zone privée

Les réponses contiennent des données personnelles : personne ne doit pouvoir lire
`https://votre-site.fr/inscription/prive/donnees/xxx.csv`. Trois niveaux de protection, du plus simple au plus sûr :

### a) Par défaut : .htaccess (Apache)

Les dossiers `prive/`, `prive/donnees/`, `prive/formulaires/` et `prive/lang/` contiennent un fichier `.htaccess` qui interdit
tout accès web. Cela fonctionne chez la plupart des hébergeurs mutualisés (serveurs Apache ou compatibles).

**Vérification** : connectez-vous à `admin.php`, un test automatique s'affiche en haut de la page :
- vert « Dossier des données protégé » : tout va bien ;
- rouge « Attention : le dossier des données est lisible depuis le web » : votre hébergeur ignore `.htaccess`
  (serveur nginx, par exemple) → passez au b).

Vous pouvez aussi tester à la main : `https://votre-site.fr/inscription/prive/formulaires/simple.ini` doit afficher
une page d'erreur « 403 Forbidden » (ou « Accès interdit »). Si le contenu du fichier s'affiche, la protection ne fonctionne pas.
(Test fait sous Apache 2.4 : avec `.htaccess` pris en compte, tous les fichiers de `prive/` répondent 403 ;
sans, les CSV sont téléchargeables par n'importe qui et `admin.php` affiche l'alerte rouge.)

### b) Le plus sûr : sortir prive/ de la partie publique

Si votre hébergement a un dossier au-dessus de `www/` (OVH, o2switch, Infomaniak, cPanel en général) :

1. déplacez le dossier `prive/` **à côté** de `www/` (et non dedans), et renommez-le par exemple `inscription-prive` :
   ```
   /home/moncompte/
   ├── inscription-prive/      ← l'ancien dossier prive/
   └── www/
       └── inscription/
           ├── index.php
           ├── admin.php
           └── emplacement.php
   ```
2. modifiez `emplacement.php` pour indiquer le nouveau chemin :
   ```php
   return dirname(__DIR__, 2) . '/inscription-prive';
   ```
   (`__DIR__` = le dossier `www/inscription`, `dirname(__DIR__, 2)` = `/home/moncompte`). Un chemin complet marche aussi :
   `return '/home/moncompte/inscription-prive';` — le chemin exact figure en général dans l'espace client de l'hébergeur.
3. ouvrez `admin.php` : le message vert « Le dossier des données est hors de la partie publique du site » confirme le changement.

Chez Free (pages perso), il n'y a pas de dossier au-dessus de la racine du site : restez sur la solution a), ou ajoutez la c).

### c) En complément : un nom de dossier impossible à deviner

Renommez `prive/` en un nom aléatoire (ex. `prive-8f3k2q9xw7`) et indiquez-le dans `emplacement.php` :
```php
return __DIR__ . '/prive-8f3k2q9xw7';
```
Ne publiez jamais ce nom (pas de lien vers lui). Les noms des fichiers de réponses peuvent aussi être rendus imprévisibles
avec la clé `fichier = "journee-x7p2m9"` dans le descripteur.

### Protéger aussi admin.php (facultatif)

`admin.php` est déjà protégée par son mot de passe (essais ralentis, connexion valable 4 heures).
Vous pouvez ajouter la protection par mot de passe de votre hébergeur (« répertoire protégé » / `.htpasswd`),
mais elle s'appliquerait au dossier entier — donc aussi au formulaire public — sauf à ne protéger que `admin.php`
avec une directive `<Files admin.php>` dans un `.htaccess` à côté.

## Réglages

Fichier `prive/config.php` :

| clé | rôle |
|-----|------|
| `langue` | langue de l'interface par défaut : `fr` (défaut) ou `en` |
| `admin_actif` | `true` (défaut) : page `admin.php` active ; `false` : désactivée, récupération par FTP uniquement |
| `admin_gestion` | `true` (défaut) : `admin.php` peut ajouter, remplacer, supprimer des formulaires et des réponses ; `false` : consultation seule |
| `admin_mot_de_passe` | empreinte du mot de passe de `admin.php` (générée par `admin.php` lui-même) |
| `dossier_formulaires`, `dossier_donnees` | emplacement des descripteurs et des réponses (par défaut dans la zone privée) |
| `separateur` | `;` (Excel français) ou `,` |
| `fuseau` | fuseau horaire des dates (`Europe/Paris`) |
| `liste_publique` | `false` : `index.php` sans `?f=` n'affiche aucune liste, même des formulaires diffusés |
| `longueur_max` | longueur maximale d'une réponse |
| `expediteur` | adresse d'expédition des notifications (souvent obligatoirement une adresse de votre domaine) |
| `debug` | `true` : affiche le détail des erreurs (à remettre à `false` ensuite) |

## Langues de l'interface

L'interface (boutons, messages d'erreur, page d'administration, courriel de notification) existe en **français** et en **anglais** :

- **page d'accueil** (liste des formulaires) : dans la langue du navigateur si elle est installée, sinon celle de `config.php` ;
  liens **English · Français** pour en changer (choix mémorisé) ; les titres des formulaires multilingues y sont traduits ;
- langue par défaut : `'langue' => 'fr'` dans `prive/config.php` ;
- **formulaires** : leur langue se règle dans leur descripteur, avec `langue = en` ou, pour un formulaire en plusieurs langues,
  `langues = "fr | en"` (voir [Formulaire multilingue](formulaires.md#formulaire-multilingue)) ;
- **page d'administration** : liens **English · Français** en haut de page (choix mémorisé par le navigateur).

**Ajouter une langue** (allemand, espagnol…) : copier `prive/lang/fr.php` (ou `en.php`) sous le nom `de.php`, `es.php`…,
traduire les textes de droite sans toucher aux noms de gauche ni aux repères `{n}`, `{titre}`…, puis l'envoyer sur le serveur.
La langue apparaît aussitôt sur la page d'accueil et dans `admin.php`, et peut être utilisée par `langue = de`
ou `langues = "fr | de"`. Un texte d'interface manquant est affiché en français.

## Sécurité

- Zone privée protégée par `.htaccess`, déplaçable hors du site, avec auto-test sur `admin.php`.
- Jetons signés (HMAC) contre les envois forgés, sans session PHP ; refus des envois en moins de 3 secondes
  et champ piège invisible contre les robots.
- Validation côté serveur de chaque champ (formats, options autorisées, conditions), même si le navigateur est contourné.
- Écriture sous verrou (`flock`) : deux réponses simultanées ne se mélangent pas, et la limite de places est respectée.
- Échappement de tout l'affichage HTML ; neutralisation des formules Excel dans le CSV.
- Formulaires non diffusés : introuvables sans la clé de partage (réponse identique à un formulaire inexistant).
- Administration : mot de passe haché, essais ralentis, jeton sur chaque action, fichiers téléversés vérifiés avant installation.
- Une clé secrète est créée automatiquement dans `donnees/.secret` au premier usage ; la supprimer invalide les
  formulaires ouverts, déconnecte l'administration et **change les liens de partage** des formulaires non diffusés.

## Dépannage

| symptôme | cause probable |
|----------|----------------|
| « Formulaire indisponible : installation incomplète » | `emplacement.php` ne pointe pas vers le dossier contenant `lib.php` |
| « Votre réponse n'a pas pu être enregistrée » | le dossier `donnees/` n'est pas inscriptible (voir [Installation](#installation), étape 2) |
| « Formulaire introuvable ou mal décrit » | erreur dans le `.ini` : le détail est affiché sur `admin.php` |
| « Page introuvable » | adresse erronée, ou formulaire non diffusé ouvert sans son lien de partage |
| un lien de partage répond « Page introuvable » | formulaire renommé ou supprimé, ou fichier `.secret` effacé : recopiez le lien depuis `admin.php` |
| « La page a expiré » | formulaire resté ouvert plus de 24 h : renvoyer suffit (la saisie est conservée) |
| « PHP ne peut pas écrire dans … formulaires » | donner les droits d'écriture au dossier `prive/formulaires/` (voir [Installation](#installation), étape 2) |
| accents incorrects dans Excel | ouvrir via *Données* → *À partir d'un fichier texte/CSV*, encodage UTF-8 |
| pas de courriel de notification | `mail()` désactivé chez l'hébergeur, ou `expediteur` hors de votre domaine |
| une modification par FTP ne s'affiche pas | attendre quelques secondes (cache PHP de l'hébergeur) puis recharger |
