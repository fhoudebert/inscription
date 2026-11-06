# Administration

[← Retour au README](../README.md) · [Créer un formulaire](formulaires.md)

Sommaire : [Installation](#installation) · [La page admin.php](#la-page-adminphp) · [Éditeur de formulaires](#éditeur-de-formulaires) ·
[Récupérer les réponses](#récupérer-les-réponses) ·
[Gérer les formulaires](#gérer-les-formulaires) · [Accès personnels](#accès-personnels) · [Catalogues et commandes](#catalogues-et-commandes) ·
[Zone privée](#zone-privée) · [Réglages](#réglages) ·
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

- titre, nom du fichier, étiquette **diffusé**, **non diffusé — accès par lien** ou **accès personnel**, langues s'il est multilingue ;
- nombre de réponses (et de places), état : ouvert, pas encore ouvert, clos, complet ;
- **lien de partage** avec boutons **Copier** et **Ouvrir** — c'est le lien à envoyer pour un formulaire non diffusé
  (voir [Diffusion et lien de partage](formulaires.md#diffusion-et-lien-de-partage)) ; pour un formulaire à accès restreint,
  il est remplacé par la rubrique **Gérer les accès** (voir [Accès personnels](#accès-personnels)) ;
- **Modifier** (ouvre l'[éditeur](#éditeur-de-formulaires)), **Télécharger** le fichier de réponses (`.csv`), **Voir** les réponses
  dans un tableau, **Télécharger le descripteur (.ini)** ;
- **Supprimer le formulaire**, **Supprimer les réponses** (voir [Gérer les formulaires](#gérer-les-formulaires)).

Un formulaire mal écrit apparaît avec le détail de l'erreur et un bouton **Corriger dans l'éditeur**.
Deux formulaires qui écrivent dans le même fichier de réponses (copie par FTP d'un `.ini` contenant `fichier = …`)
sont signalés par une alerte rouge sur chacun d'eux : réponses mélangées et places partagées, à corriger.
En bas : **Créer un formulaire**, l'envoi d'un fichier `.ini`, et la rubrique « Autres fichiers » (anciennes versions
de fichiers de réponses, réponses des formulaires supprimés).

**Désactiver la page** : `'admin_actif' => false` dans `prive/config.php`. `admin.php` répond alors « Page introuvable »
(vous pouvez aussi supprimer le fichier) ; tout se fait par FTP. Le test automatique n'étant plus affiché,
vérifiez la protection à la main (voir [Zone privée](#zone-privée)).

## Éditeur de formulaires

Pour créer ou modifier un formulaire sans écrire de `.ini` : bouton **Modifier** d'un formulaire, ou rubrique
**Créer un formulaire** (formulaire vide, modèle « Inscription » ou « Sondage », ou copie d'un formulaire existant).
L'éditeur demande `'admin_gestion' => true`, JavaScript et un écran d'ordinateur (sur un petit écran, les colonnes s'empilent).

La page a trois colonnes :

- **à gauche, la liste** : « Réglages du formulaire » puis les champs dans l'ordre d'affichage. On réordonne les champs
  en les **glissant-déposant** (poignée ⠿ ; lâcher un champ sur « Réglages du formulaire » le met en tête), ou avec les boutons
  **↑ ↓** du champ sélectionné, qui a aussi **⧉** pour le dupliquer et **✕** pour le supprimer. En bas, **Ajouter** un champ
  du type choisi (il se place après le champ sélectionné). Un champ en erreur est encadré en rouge ;
- **au milieu, les propriétés** du champ sélectionné, selon son type : question, nom de colonne, réponses possibles
  (une par ligne), texte d'aide, exemple, obligatoire, valeur unique, bornes, valeur pré-remplie, et l'assistant
  **Afficher si…** (choisir un champ placé avant, « est parmi » / « n'est pas parmi », cocher les réponses ;
  plusieurs conditions = toutes à la fois). Pour les réglages du formulaire : titre, textes, période, places,
  qui peut répondre (tout le monde, accès personnel), diffusion, langues, courriel de notification ;
- **à droite, l'aperçu**, rafraîchi pendant la frappe et produit par le même code que la page publique : ce qu'on y voit
  est ce que verront les répondants (conditions comprises : on peut cocher les réponses pour les essayer).
  Un clic sur un champ de l'aperçu le sélectionne. Au-dessus : « Formulaire valide » ou l'erreur à corriger.

Le **nom de colonne** d'un nouveau champ se déduit de sa question (« Nom et prénom » → `nom_et_prenom`) tant qu'on ne le
modifie pas ; renommer un champ met à jour les conditions qui le visent. Supprimer un champ retire les conditions qui
en dépendaient (après confirmation).

**Formulaires multilingues** : dès qu'une autre langue est cochée dans les réglages, des onglets **Français (base) · English**
apparaissent au-dessus des propriétés ; dans l'onglet d'une autre langue, seuls les textes se traduisent, avec le texte
de base en rappel. Une liste de réponses traduite qui n'a pas le même nombre de lignes que l'originale est signalée.
La langue de l'aperçu se choisit en haut de l'aperçu.

**Raccourcis clavier** : **Ctrl+S** enregistrer ; **Alt+↑ / Alt+↓** déplacer le champ sélectionné (même pendant la saisie) ;
en dehors des zones de saisie : **↑ / ↓** choisir le champ précédent ou suivant, **Suppr** supprimer le champ (après confirmation),
**Inser** ajouter un champ du type affiché sous la liste.

**Enregistrer** (ou Ctrl+S) : le fichier est vérifié comme un fichier envoyé ; l'ancienne version est conservée dans
`prive/formulaires/supprimes/`. Si les colonnes changent alors que des réponses existent, l'éditeur l'indique en permanence
au-dessus de l'aperçu et demande confirmation (le fichier de réponses actuel sera archivé à la prochaine réponse).
Si le fichier a été modifié entre-temps (par FTP ou dans une autre fenêtre), l'enregistrement est refusé : rechargez la page.
Il l'est aussi si le **fichier de réponses** (Réglages avancés) est déjà celui d'un autre formulaire : l'erreur apparaît dès
l'aperçu ; laissez le nom vide (nom du formulaire) ou choisissez-en un autre.
Pour un nouveau formulaire, indiquez d'abord son **nom dans l'adresse** (en haut de page).

**Mode texte** : bouton en haut de page, pour éditer le `.ini` directement, avec la même vérification et le même aperçu.
On ne revient au mode visuel qu'avec un texte valide. C'est aussi par là qu'on corrige un formulaire mal écrit.

Bon à savoir : un enregistrement depuis l'éditeur **réécrit tout le fichier**. Les commentaires (`;`) écrits à la main
disparaissent, les clés anglaises sont réécrites en français et l'ordre des clés est normalisé ; le contenu, lui,
est strictement le même. L'ancienne version reste dans `supprimes/`. En mode texte, le texte est enregistré tel quel.

## Récupérer les réponses

- **Par `admin.php`** : bouton **Télécharger** de chaque formulaire.
- **Par FTP** : dossier `prive/donnees/`, un fichier `<fichier>.csv` par formulaire.

Format des fichiers :

- encodage UTF-8 avec BOM, séparateur `;` (modifiable dans `config.php`) : un double-clic l'ouvre correctement dans
  Excel (version française), LibreOffice Calc ou [GridCraft](https://getartcraft.com/apps/gridcraft) (tableur libre et gratuit) ;
  dans LibreOffice, choisissez « UTF-8 » et « point-virgule » si la question est posée ;
- colonnes : `numero` (numéro de réponse aléatoire, ex. `K7QF-M2ZD`, aussi montré au répondant), `date` (date et heure d'envoi),
  `langue` (formulaires multilingues seulement : langue du répondant), `acces` et `acces_nom` (formulaires à accès restreint :
  identifiant et nom de l'accès qui a répondu), puis un champ par colonne, dans l'ordre du descripteur ;
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
- il est refusé si son fichier de réponses (clé `fichier`, ou nom du formulaire) est déjà celui d'un autre formulaire —
  ce qui arrive en copiant un `.ini` qui contient une ligne `fichier = …` ;
  en cas d'erreur, rien n'est modifié et le message indique l'erreur (champ ou ligne en cause) ;
- 200 Ko au plus, nom limité à lettres non accentuées, chiffres, `-` et `_`, extension `.ini` imposée :
  le fichier ne peut jamais devenir un programme exécutable ;
- la suite de caractères `${` est refusée (elle permettrait de lire des variables du serveur) ;
- chaque action est protégée par un jeton lié à la connexion (une page piégée ne peut pas la déclencher à votre place) ;
- PHP doit pouvoir écrire dans `prive/formulaires/` ; sinon, la page l'indique et la gestion reste possible par FTP.

Avec `'admin_gestion' => false`, `admin.php` ne sert plus qu'à consulter et télécharger : les boutons d'ajout et de suppression disparaissent.

## Accès personnels

Pour un formulaire déclaré `acces = personnel` ou `acces = lien` (voir [Accès restreint](formulaires.md#accès-restreint)),
la fiche du formulaire dans `admin.php` affiche le nombre d'accès et de réponses, et une rubrique **Gérer les accès** :

1. **Créer** : coller la liste des personnes, une par ligne, avec leur nom et/ou leur e-mail et/ou leur téléphone, dans
   n'importe quel ordre et avec n'importe quel séparateur (« Marie Durand ; marie@exemple.fr », « Jean Martin 06 12 34 56 78 »,
   « paul@exemple.fr ») — une colonne copiée d'un tableur convient. Ou indiquer un nombre d'accès sans nom (500 au plus
   à la fois), puis **Créer les accès**. Le nom, l'e-mail et le téléphone pré-rempliront les champs correspondants du
   formulaire (voir [Accès restreint](formulaires.md#accès-restreint)).
2. **Distribuer** : la page affiche alors, **une seule fois**, chaque identifiant avec son mot de passe et son lien personnel,
   et un bouton **Télécharger la liste (CSV)** (`identifiant;nom;email;telephone;mot_de_passe;lien`, sans `mot_de_passe` en mode `lien`).
   Ce fichier sert au publipostage (LibreOffice, Thunderbird…) ou à l'impression. Les mots de passe et les jetons des liens
   ne sont conservés que sous forme d'empreinte : ils ne pourront plus être affichés.
3. **Suivre** : le tableau des accès indique pour chacun son nom et son contact, son état (actif, révoqué, bloqué après trop d'essais)
   et la date de sa réponse et de sa dernière modification.
4. **Agir** sur un accès :
   - **Révoquer** : l'accès ne permet plus d'ouvrir le formulaire (sa réponse éventuelle est conservée) ; **Réactiver** l'annule
     et débloque un accès bloqué ;
   - **Nouveau mot de passe** / **Nouveau lien** : en cas de perte ou de fuite ; l'ancien cesse aussitôt de fonctionner
     et le nouveau s'affiche une fois, comme à la création.

Les accès sont enregistrés dans `prive/donnees/acces/<fichier>.csv` (identifiant, nom, e-mail, téléphone, empreinte, état, dates, essais manqués),
dans la zone privée. Ils restent en place si le formulaire est remplacé ; ils sont liés au nom du fichier de réponses
(clé `fichier`). Ces actions demandent `'admin_gestion' => true`.

## Catalogues et commandes

Pour les formulaires qui contiennent un champ `commande` (voir [Formulaire de commande](formulaires.md#formulaire-de-commande)).

### Catalogues

Rubrique **Catalogues de produits** du tableau de bord : liste des catalogues (nombre de produits et de produits disponibles),
**Nouveau catalogue**, **Importer un CSV**. Le bouton **Modifier** ouvre le catalogue sous forme de tableau :

- une ligne par produit : **Disponible** (case), **Catégorie** (regroupe les produits sur le formulaire), **Produit**
  (avec la variété : « Chou frisé »), **Unité** (kg, botte, pièce, barquette 250 g…), **Prix** par unité, **Pas** de quantité ;
- **Tout disponible** / **Rien de disponible** (s'applique aux lignes affichées : combiné au filtre, on coche par exemple
  tous les choux d'un coup), **Filtrer…**, **Ajouter un produit**, case **retirer** pour supprimer une ligne ;
- **Enregistrer** : les changements valent pour les commandes suivantes ; les commandes déjà passées gardent les prix
  du moment où elles ont été faites.

Chaque semaine, il suffit donc d'ouvrir le catalogue, de cocher ce qui est disponible et d'ajuster les prix.

**Importer un CSV** (depuis un tableur, séparateur `;` ou `,`) : une colonne `Produit` au moins ; colonnes reconnues
`Catégorie`, `Produit`, `Variété` (ajoutée au nom), `Unité`, `Prix` (ou `Prix unitaire`), `Pas`, `Disponible`.
Les lignes sans nom de produit sont ignorées ; une colonne `Disponible` vide vaut « disponible ». Vérifiez ensuite le tableau
et enregistrez. **Télécharger** donne le catalogue en CSV, pour le retravailler dans un tableur et le réimporter.

Les catalogues sont dans `prive/catalogues/` (zone privée).

### Commandes

Sur la fiche d'un formulaire de commande, le bouton **Commandes (n en cours)** ouvre la liste :

- filtres **En cours** (à préparer + prêtes), **à préparer**, **prête**, **remise**, **annulée**, **Toutes** ;
- pour chaque commande : numéro, date, client (premières réponses), nombre de produits, total (estimé ou définitif), état,
  bouton **Préparer** ; en bas, le total des commandes affichées ;
- **Quantités à préparer** : la somme, produit par produit, des commandes affichées (hors annulées) — de quoi savoir
  combien récolter ou préparer ;
- **Exporter les commandes (CSV)** : une ligne par commande avec les réponses, le détail, le total estimé et le total définitif.

La fiche **Préparer** d'une commande :

1. rappelle les réponses du client ;
2. **Préparation et prix définitif** : pour chaque produit, la quantité commandée, la quantité **servie** (pesée, 0 si
   manquant) et le **prix unitaire**, modifiables ; **+ Ajouter un produit** (remplacement, oubli) ; une ligne
   d'**ajustement** (frais de livraison, remise en montant négatif) ; le total définitif se recalcule en direct ;
3. **État** (à préparer, prête, remise, annulée) et **Note pour le client** ;
4. **Enregistrer la préparation**, puis **Message à envoyer** : le texte (modèle `message` du formulaire) reprend
   les quantités et prix définitifs ; on peut le retoucher, puis **Copier** et le coller dans un SMS ou un e-mail,
   avec ses propres moyens. L'application n'envoie rien elle-même.

Si le client modifie sa commande après sa préparation (formulaire à accès restreint et modifiable), la fiche l'indique ;
**Repartir de la commande du client** reprend sa nouvelle commande.

Le détail des commandes est dans `prive/donnees/commandes/<fichier>.json` ; il est supprimé avec le fichier de réponses
(**Supprimer les réponses**). Créer, modifier un catalogue et préparer une commande demandent `'admin_gestion' => true`.

## Zone privée

Les réponses contiennent des données personnelles : personne ne doit pouvoir lire
`https://votre-site.fr/inscription/prive/donnees/xxx.csv`. Trois niveaux de protection, du plus simple au plus sûr :

### a) Par défaut : .htaccess (Apache)

Les dossiers `prive/`, `prive/donnees/` (et `donnees/acces/`, `donnees/commandes/`), `prive/formulaires/`, `prive/catalogues/` et `prive/lang/` contiennent un fichier `.htaccess` qui interdit
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
| `devise` | symbole monétaire des formulaires de commande : `€` (défaut), `CHF`, `$`… |
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
- Accès restreint : identifiants aléatoires (40 bits), mots de passe de 10 caractères (≈ 57 bits) ou jetons de lien de 24 caractères,
  stockés uniquement sous forme d'empreinte (`password_hash`, SHA-256) ; pause d'une seconde par échec et blocage 15 minutes
  après 10 échecs ; connexion par cookie signé (2 heures, invalidé par un changement de mot de passe) ; une seule réponse
  par accès, vérifiée sous le même verrou que l'écriture.
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
| un lien personnel répond « Page introuvable » | lien mal copié (identifiant ou jeton tronqué) ; en mode `lien`, lien renouvelé depuis (« Nouveau lien ») |
| « Cet accès n'est plus valable » | accès révoqué : **Réactiver** dans `admin.php` |
| « Trop d'essais » | 10 mots de passe faux : attendre 15 minutes, ou **Réactiver** l'accès dans `admin.php` |
| mot de passe perdu | **Nouveau mot de passe** sur la ligne de l'accès, puis le transmettre à la personne |
| l'éditeur refuse d'enregistrer : « modifié entre-temps » | le fichier a changé depuis l'ouverture de l'éditeur (FTP, autre fenêtre) : recharger la page |
| l'éditeur reste vide | JavaScript désactivé dans le navigateur |
| « … utilise le catalogue …, introuvable » | le catalogue nommé par `catalogue =` n'existe pas dans `prive/catalogues/` : le créer (rubrique Catalogues) ou corriger le nom |
| un produit n'apparaît pas sur le formulaire de commande | il n'est pas coché « disponible » dans le catalogue |
| « le fichier de réponses … est déjà celui du formulaire … » | le `.ini` (souvent une copie) contient `fichier = …` comme un autre formulaire : retirer la ligne, ou vider « Nom du fichier de réponses » dans l'éditeur |
| « Votre session d'administration a expiré » dans l'éditeur | connexion de plus de 4 h : se reconnecter dans un autre onglet, puis enregistrer (les modifications restent dans l'éditeur) |
