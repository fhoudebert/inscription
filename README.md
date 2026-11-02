# inscription — formulaire d'inscription générique

Petit outil pour recueillir des inscriptions (ou les réponses à un sondage) sur un **hébergement mutualisé** (Free, OVH, o2switch, Infomaniak, Ionos…) :

- le formulaire est **décrit dans un fichier texte** (`.ini`) : pas de programmation, pas de base de données ;
- chaque inscription est ajoutée à un **fichier CSV** qui s'ouvre directement dans Excel, LibreOffice Calc ou [GridCraft](https://getartcraft.com/apps/gridcraft) ;
- plusieurs formulaires peuvent cohabiter (un fichier de description = un formulaire = un fichier de données) ;
- un formulaire peut être **diffusé** (listé sur la page d'accueil) ou **non diffusé** (ouvert seulement par un lien de partage à clé unique) ;
- une page d'administration protégée par mot de passe permet de consulter et télécharger les réponses, copier les liens de partage,
  ajouter, remplacer ou supprimer des formulaires ;
- l'interface est **traduite** (français, anglais ; d'autres langues s'ajoutent avec un seul fichier).

Il suffit de **PHP 7.4 ou plus récent** (aucune extension particulière, aucune session PHP, aucune base MySQL).

---

## 1. Contenu

```
inscription/                 ← dossier à copier sur le site
├── index.php                ← le formulaire public
├── admin.php                ← administration : réponses, liens de partage, ajout/suppression de formulaires
├── emplacement.php          ← indique où se trouve la zone privée
└── prive/                   ← ZONE PRIVÉE (ne doit pas être lisible depuis le web)
    ├── .htaccess            ← interdit l'accès web au dossier
    ├── config.php           ← réglages (mot de passe admin, séparateur CSV…)
    ├── lib.php              ← le moteur
    ├── lang/                ← textes de l'interface : fr.php, en.php (une langue = un fichier)
    ├── formulaires/         ← descriptions des formulaires : un fichier .ini par formulaire
    │   ├── exemple.ini      ← exemple complet : inscription à une journée (diffusé)
    │   ├── sondage.ini      ← questionnaire de satisfaction anonyme (non diffusé : lien de partage)
    │   ├── meeting-en.ini   ← exemple écrit en anglais, interface en anglais
    │   ├── simple.ini       ← exemple minimal, à copier pour démarrer
    │   └── supprimes/       ← formulaires supprimés ou remplacés depuis admin.php (créé tout seul)
    └── donnees/             ← les inscriptions : un fichier .csv par formulaire (créé tout seul)
```

## 2. Installation (copie des fichiers)

1. Copiez le dossier `inscription/` par FTP (FileZilla…) dans la partie publique de votre site :
   `www/`, `public_html/`, `htdocs/` ou la racine chez Free.
   Le formulaire est alors à l'adresse `https://votre-site.fr/inscription/`.
   Vous pouvez renommer le dossier (`inscriptions`, `journee2026`…) : l'adresse change en conséquence.
2. Le dossier `prive/donnees/` doit être **accessible en écriture** par PHP. C'est le cas par défaut chez la
   plupart des hébergeurs ; sinon, dans FileZilla : clic droit sur `donnees` → *Droits d'accès au fichier* → `755`
   (ou `775`/`777` si l'enregistrement échoue encore). Pour ajouter des formulaires depuis `admin.php`,
   il en va de même pour `prive/formulaires/`.
3. (Si vous utilisez la page d'administration, ce qui est le cas par défaut) ouvrez `https://votre-site.fr/inscription/admin.php`, choisissez un mot de passe : la page affiche une ligne
   à coller dans `prive/config.php` (à la place de `'admin_mot_de_passe' => '',`). Renvoyez `config.php` sur le serveur.
4. Toujours sur `admin.php`, connectez-vous : un **test automatique** vérifie que les données ne sont pas lisibles
   depuis le web (voir § 5). Si un message rouge apparaît, suivez le § 5.
5. Supprimez ou adaptez les exemples de `prive/formulaires/`, puis testez votre formulaire en vous inscrivant.

Particularité Free (pages perso) : rien à faire de spécial, l'outil n'utilise pas de session PHP
(donc pas besoin du dossier `sessions/`).

## 3. Décrire un formulaire

Un formulaire = un fichier `prive/formulaires/<nom>.ini`, en texte brut **encodé en UTF-8**
(Bloc-notes, Notepad++, gedit, Kate…). Son adresse est `index.php?f=<nom>`.
Le `<nom>` ne contient que des lettres non accentuées, des chiffres, `-` et `_`.

`index.php` affiche la liste des formulaires **diffusés** (`diffusion = oui`, le défaut) ; s'il n'y en a qu'un,
il l'affiche directement. Les formulaires non diffusés s'ouvrent par leur lien de partage (voir plus bas).

### Règles d'écriture

- Une section `[formulaire]` donne les réglages généraux, puis **une section par champ**, dans l'ordre d'affichage.
- Le nom de section d'un champ (`[email]`, `[nom_prenom]`…) devient le **nom de la colonne** dans le fichier CSV :
  lettres non accentuées, chiffres et `_`, commençant par une lettre. `numero` et `date` sont réservés.
- **Mettez les textes entre guillemets doubles** : `libelle = "Nom de l'association"`.
  Un guillemet double dans un texte s'écrit `\"`. Un texte entre guillemets peut tenir sur plusieurs lignes.
- Une ligne commençant par `;` est un commentaire.
- Pour oui/non : `oui`, `1`, `yes`, `true` = oui ; vide, `non`, `0` = non.

### Section `[formulaire]`

| clé         | rôle | défaut |
|-------------|------|--------|
| `titre`     | titre affiché en haut de la page | le nom du fichier |
| `intro`     | texte d'introduction | — |
| `merci`     | message affiché après l'envoi | « Merci, votre réponse est enregistrée. » |
| `bouton`    | texte du bouton d'envoi | « Envoyer » |
| `fichier`   | nom du fichier de données (`donnees/<fichier>.csv`) | le nom du formulaire |
| `ouverture` | date/heure d'ouverture : `"2026-11-15"` ou `"2026-11-15 09:00"` | — |
| `fermeture` | date/heure de clôture (une date seule = jusqu'à 23 h 59) | — |
| `places`    | nombre maximal d'inscriptions (0 = illimité) | 0 |
| `notifier`  | adresse(s) recevant un courriel à chaque inscription (séparées par des virgules) | — |
| `diffusion` | `oui` = listé sur la page d'accueil ; `non` = accessible uniquement par le lien de partage | oui |
| `langue`    | langue de l'interface pour ce formulaire (`fr`, `en`…) : boutons, messages d'erreur, valeur des cases | celle de `config.php` |
| `afficher_numero` | `non` = ne pas montrer le numéro de réponse après l'envoi (utile pour un sondage anonyme) | oui |

### Types de champs

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
| `titre`      | intertitre de section (rien à saisir) | — (pas de colonne) |
| `info`       | paragraphe d'information (rien à saisir) | — (pas de colonne) |

### Propriétés d'un champ

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
| `unique`      | `oui` = refuse une valeur déjà inscrite (par ex. sur l'e-mail, pour éviter les doubles inscriptions) |
| `si`          | condition d'affichage (voir ci-dessous) |

### Champs conditionnels (`si`)

Un champ (ou un titre, ou un paragraphe d'info) peut n'apparaître qu'en fonction d'une réponse
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

### Exemple

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

`prive/formulaires/exemple.ini` montre la plupart des types et options (dont deux listes déroulantes).

### Exemple de sondage

`prive/formulaires/sondage.ini` est un questionnaire de satisfaction anonyme (adresse `index.php?f=sondage`) :
notes sur une échelle (`echelle`, de 1 à 5 ou de 0 à 10), liste déroulante « Comment avez-vous connu la journée ? »
avec un champ « Précisez » qui n'apparaît que pour « Autre », choix multiples et commentaires libres.
Aucun nom ni e-mail n'y est demandé, et `afficher_numero = non` évite d'afficher un numéro de réponse.
Les notes arrivent dans le CSV sous forme de nombres : moyennes et graphiques se font directement dans le tableur.

```ini
[satisfaction]
type        = echelle
libelle     = "Globalement, êtes-vous satisfait(e) de la journée ?"
gauche      = "Pas du tout"
droite      = "Tout à fait"
obligatoire = oui
```

Si le fichier contient une erreur (type inconnu, condition vers un champ inexistant, guillemet oublié…),
la page affiche « Formulaire introuvable ou mal décrit » ; le détail de l'erreur est indiqué sur `admin.php`
(ou sur la page elle-même si vous mettez `'debug' => true` dans `config.php`).

Après modification d'un fichier sur le serveur, attendez quelques secondes avant de recharger la page
(certains hébergeurs gardent les fichiers PHP en cache un court instant).

### Diffusion et lien de partage

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

### Écrire un formulaire en anglais

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
| `langue` | `language` | | `cases`, `case` | `checkboxes`, `consent` |
| | | | `echelle`, `titre` | `scale`, `heading` |

Voir `prive/formulaires/meeting-en.ini`. Avec `language = en`, les boutons et messages sont en anglais et une case cochée
est enregistrée `yes` / `no` (au lieu de `oui` / `non`).

## 4. Récupérer les inscriptions

**Par la page d'administration** (si elle est active : `'admin_actif' => true`, réglage par défaut) — `https://votre-site.fr/inscription/admin.php` :
pour chaque formulaire : nombre de réponses, état (ouvert, clos, complet), diffusé ou non, **lien de partage** avec bouton **Copier**,
boutons **Télécharger** (fichier `.csv`) et **Voir** (tableau à l'écran), téléchargement du descripteur `.ini`.
La page propose aussi le choix de la langue (English / Français).

**Par FTP** — dans `prive/donnees/` : un fichier `<fichier>.csv` par formulaire.
Si vous ne voulez récupérer les données que par FTP, désactivez la page d'administration dans `prive/config.php` :
```php
'admin_actif' => false,
```
`admin.php` répond alors « Page introuvable » (vous pouvez aussi supprimer le fichier `admin.php` du serveur).
Sans page d'administration, le test automatique de protection n'est plus affiché : faites la vérification à la main (§ 5 a).

Format des fichiers :

- encodage UTF-8 avec BOM, séparateur `;` (modifiable dans `config.php`) : un double-clic l'ouvre correctement dans
  Excel (version française), LibreOffice Calc ou [GridCraft](https://getartcraft.com/apps/gridcraft) (tableur libre et gratuit) ; dans LibreOffice, choisissez « UTF-8 » et « point-virgule » si la question est posée ;
- colonnes : `numero` (numéro d'inscription aléatoire, ex. `K7QF-M2ZD`, aussi montré à l'inscrit), `date` (date et heure d'envoi),
  puis un champ par colonne, dans l'ordre du descripteur ;
- les réponses commençant par `=`, `+`, `-` ou `@` sont précédées d'une apostrophe, pour qu'Excel ne les exécute pas comme formules.

**Modifier un formulaire déjà utilisé** : si vous ajoutez, retirez ou renommez un champ, l'ancien fichier est conservé sous le nom
`<fichier>-ancien-AAAAMMJJ-HHMMSS.csv` et un nouveau fichier démarre avec les nouvelles colonnes
(les deux restent téléchargeables depuis `admin.php`). Changer un libellé ou une option ne crée pas de nouveau fichier.

**Supprimer ou corriger une inscription** : téléchargez le fichier par FTP, modifiez-le (en gardant le format CSV,
séparateur `;`, UTF-8), renvoyez-le ; ou supprimez-le pour repartir de zéro (par FTP, ou bouton **Supprimer les réponses** de `admin.php`).
Pensez à supprimer les données quand elles ne sont plus utiles (RGPD).

### Ajouter, remplacer ou supprimer un formulaire depuis `admin.php`

Plus besoin de FTP pour gérer les formulaires (réglage `'admin_gestion' => true`, par défaut) :

- **Ajouter** : section « Ajouter ou remplacer un formulaire », choisir un fichier `.ini` sur votre ordinateur.
  Le nom dans l'adresse est celui du fichier (`reunion.ini` → `index.php?f=reunion`), ou celui que vous indiquez.
- **Modifier** : bouton **Télécharger le descripteur (.ini)**, corriger le fichier, le renvoyer en cochant **Remplacer**.
  L'ancienne version est conservée dans `prive/formulaires/supprimes/`. Si des champs ont été ajoutés, retirés ou renommés,
  la page prévient que le fichier de réponses actuel sera archivé à la prochaine réponse.
- **Supprimer un formulaire** : lien **Supprimer le formulaire**, cocher **Je confirme**. Le descripteur est déplacé dans
  `prive/formulaires/supprimes/` (récupérable par FTP) ; les réponses déjà reçues sont **conservées** et restent téléchargeables
  (rubrique « Autres fichiers »).
- **Supprimer des réponses** : lien **Supprimer les réponses**, cocher **Je confirme** : le fichier CSV est **effacé définitivement**.

Garde-fous :
- le fichier envoyé est **entièrement vérifié avant d'être installé** (syntaxe, types, options, conditions, UTF-8) ;
  en cas d'erreur, rien n'est modifié et le message indique l'erreur (champ ou ligne en cause) ;
- 200 Ko au plus, nom limité à lettres non accentuées, chiffres, `-` et `_`, extension `.ini` imposée :
  le fichier ne peut jamais devenir un programme exécutable ;
- la suite de caractères `${` est refusée (elle permettrait de lire des variables du serveur) ;
- chaque action est protégée par un jeton lié à la connexion (une page piégée ne peut pas la déclencher à votre place) ;
- PHP doit pouvoir écrire dans `prive/formulaires/` ; sinon, la page l'indique et la gestion reste possible par FTP.

Avec `'admin_gestion' => false`, `admin.php` ne sert plus qu'à consulter et télécharger : les boutons d'ajout et de suppression disparaissent.

## 5. Mettre les données dans une zone privée

Les inscriptions contiennent des données personnelles : personne ne doit pouvoir lire
`https://votre-site.fr/inscription/prive/donnees/xxx.csv`. Trois niveaux de protection, du plus simple au plus sûr :

### a) Par défaut : `.htaccess` (Apache)

Les dossiers `prive/`, `prive/donnees/`, `prive/formulaires/` et `prive/lang/` contiennent un fichier `.htaccess` qui interdit
tout accès web. Cela fonctionne chez la plupart des hébergeurs mutualisés (serveurs Apache ou compatibles).

Attention : les fichiers commençant par un point sont parfois masqués ou non envoyés par le client FTP.
Dans FileZilla : menu *Serveur* → *Forcer l'affichage des fichiers cachés*, et vérifiez qu'ils sont bien présents sur le serveur.

**Vérification** : connectez-vous à `admin.php`, un test automatique s'affiche en haut de la page :
- vert « Dossier des données protégé » : tout va bien ;
- rouge « Attention : le dossier des données est lisible depuis le web » : votre hébergeur ignore `.htaccess`
  (serveur nginx, par exemple) → passez au b).

Vous pouvez aussi tester à la main : `https://votre-site.fr/inscription/prive/formulaires/simple.ini` doit afficher
une page d'erreur « 403 Forbidden » (ou « Accès interdit »). Si le contenu du fichier s'affiche, la protection ne fonctionne pas.
(Test fait sous Apache 2.4 : avec `.htaccess` pris en compte, tous les fichiers de `prive/` répondent 403 ;
sans, les CSV sont téléchargeables par n'importe qui et `admin.php` affiche l'alerte rouge.)

### b) Le plus sûr : sortir `prive/` de la partie publique

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
Ne publiez jamais ce nom (pas de lien vers lui). Les noms des fichiers de données peuvent aussi être rendus imprévisibles
avec la clé `fichier = "journee-x7p2m9"` dans le descripteur.

### Protéger aussi la page d'administration (facultatif)

`admin.php` est déjà protégée par son mot de passe (essais ralentis, connexion valable 4 heures).
Vous pouvez ajouter la protection par mot de passe de votre hébergeur (« répertoire protégé » / `.htpasswd`),
mais elle s'appliquerait au dossier entier — donc aussi au formulaire public — sauf à ne protéger que `admin.php`
avec une directive `<Files admin.php>` dans un `.htaccess` à côté.

## 6. Réglages (`prive/config.php`)

| clé | rôle |
|-----|------|
| `langue` | langue de l'interface par défaut : `fr` (défaut) ou `en` |
| `admin_actif` | `true` (défaut) : page `admin.php` active ; `false` : désactivée, récupération par FTP uniquement |
| `admin_gestion` | `true` (défaut) : `admin.php` peut ajouter, remplacer, supprimer des formulaires et des réponses ; `false` : consultation seule |
| `admin_mot_de_passe` | mot de passe chiffré de `admin.php` (généré par `admin.php` lui-même) |
| `dossier_formulaires`, `dossier_donnees` | emplacement des descripteurs et des données (par défaut dans la zone privée) |
| `separateur` | `;` (Excel français) ou `,` |
| `fuseau` | fuseau horaire des dates (`Europe/Paris`) |
| `liste_publique` | `false` : `index.php` sans `?f=` n'affiche aucune liste, même des formulaires diffusés |
| `longueur_max` | longueur maximale d'une réponse |
| `expediteur` | adresse d'expédition des notifications (souvent obligatoirement une adresse de votre domaine) |
| `debug` | `true` : affiche le détail des erreurs (à remettre à `false` ensuite) |

## 7. Sécurité

- Zone privée protégée par `.htaccess`, déplaçable hors du site, avec auto-test sur `admin.php`.
- Jetons signés (HMAC) contre les envois forgés, sans session PHP ; refus des envois en moins de 3 secondes
  et champ piège invisible contre les robots.
- Validation côté serveur de chaque champ (formats, options autorisées, conditions), même si le navigateur est contourné.
- Écriture sous verrou (`flock`) : deux inscriptions simultanées ne se mélangent pas, et la limite de places est respectée.
- Échappement de tout l'affichage HTML ; neutralisation des formules Excel dans le CSV.
- Formulaires non diffusés : introuvables sans la clé de partage (réponse identique à un formulaire inexistant).
- Administration : mot de passe haché, essais ralentis, jeton sur chaque action, fichiers téléversés vérifiés avant installation.
- Une clé secrète est créée automatiquement dans `donnees/.secret` au premier usage ; la supprimer invalide les
  formulaires ouverts, déconnecte l'administration et **change les liens de partage** des formulaires non diffusés.

## 8. Dépannage

| symptôme | cause probable |
|----------|----------------|
| « Formulaire indisponible : installation incomplète » | `emplacement.php` ne pointe pas vers le dossier contenant `lib.php` |
| « L'inscription n'a pas pu être enregistrée » | le dossier `donnees/` n'est pas inscriptible (voir § 2, étape 2) |
| « Formulaire introuvable ou mal décrit » | erreur dans le `.ini` : le détail est affiché sur `admin.php` |
| « Page introuvable » | adresse erronée, ou formulaire non diffusé ouvert sans son lien de partage |
| « La page a expiré » | formulaire resté ouvert plus de 24 h : renvoyer suffit (la saisie est conservée) |
| accents incorrects dans Excel | ouvrir via *Données* → *À partir d'un fichier texte/CSV*, encodage UTF-8 |
| pas de courriel de notification | `mail()` désactivé chez l'hébergeur, ou `expediteur` hors de votre domaine |
| un lien de partage répond « Page introuvable » | formulaire renommé ou supprimé, ou fichier `.secret` effacé : recopiez le lien depuis `admin.php` |
| « PHP ne peut pas écrire dans … formulaires » | donner les droits d'écriture au dossier `prive/formulaires/` (§ 2, étape 2) |

## 9. Langues

L'interface (boutons, messages d'erreur, page d'administration, courriel de notification) existe en **français** et en **anglais** :

- langue par défaut : `'langue' => 'fr'` dans `prive/config.php` ;
- langue d'un formulaire : `langue = en` dans son descripteur (les textes du formulaire eux-mêmes sont écrits par vous,
  dans la langue de votre choix) ;
- page d'administration : liens **English / Français** en haut de page (choix mémorisé par le navigateur).

**Ajouter une langue** (allemand, espagnol…) : copier `prive/lang/fr.php` (ou `en.php`) sous le nom `de.php`, `es.php`…,
traduire les textes de droite sans toucher aux noms de gauche ni aux repères `{n}`, `{titre}`…, puis l'envoyer sur le serveur.
La langue apparaît aussitôt dans `admin.php` et peut être utilisée par `langue = de`. Un texte manquant est affiché en français.
