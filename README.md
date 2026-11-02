# inscription — formulaire d'inscription générique

Petit outil pour recueillir des inscriptions sur un **hébergement mutualisé** (Free, OVH, o2switch, Infomaniak, Ionos…) :

- le formulaire est **décrit dans un fichier texte** (`.ini`) : pas de programmation, pas de base de données ;
- chaque inscription est ajoutée à un **fichier CSV** qui s'ouvre directement dans Excel ou LibreOffice Calc ;
- plusieurs formulaires peuvent cohabiter (un fichier de description = un formulaire = un fichier de données) ;
- une page d'administration protégée par mot de passe permet de consulter et télécharger les inscriptions.

Il suffit de **PHP 7.4 ou plus récent** (aucune extension particulière, aucune session PHP, aucune base MySQL).

---

## 1. Contenu

```
inscription/                 ← dossier à copier sur le site
├── index.php                ← le formulaire public
├── admin.php                ← consultation / téléchargement des inscriptions
├── emplacement.php          ← indique où se trouve la zone privée
└── prive/                   ← ZONE PRIVÉE (ne doit pas être lisible depuis le web)
    ├── .htaccess            ← interdit l'accès web au dossier
    ├── config.php           ← réglages (mot de passe admin, séparateur CSV…)
    ├── lib.php              ← le moteur
    ├── formulaires/         ← descriptions des formulaires : un fichier .ini par formulaire
    │   ├── exemple.ini      ← exemple complet (tous les types de champs)
    │   └── simple.ini       ← exemple minimal, à copier pour démarrer
    └── donnees/             ← les inscriptions : un fichier .csv par formulaire (créé tout seul)
```

## 2. Installation (copie des fichiers)

1. Copiez le dossier `inscription/` par FTP (FileZilla…) dans la partie publique de votre site :
   `www/`, `public_html/`, `htdocs/` ou la racine chez Free.
   Le formulaire est alors à l'adresse `https://votre-site.fr/inscription/`.
   Vous pouvez renommer le dossier (`inscriptions`, `journee2026`…) : l'adresse change en conséquence.
2. Le dossier `prive/donnees/` doit être **accessible en écriture** par PHP. C'est le cas par défaut chez la
   plupart des hébergeurs ; sinon, dans FileZilla : clic droit sur `donnees` → *Droits d'accès au fichier* → `755`
   (ou `775`/`777` si l'enregistrement échoue encore).
3. Ouvrez `https://votre-site.fr/inscription/admin.php`, choisissez un mot de passe : la page affiche une ligne
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

S'il n'y a qu'un seul formulaire, `index.php` l'affiche directement ; sinon il affiche la liste.

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
| `merci`     | message affiché après l'inscription | « Merci, votre inscription est enregistrée. » |
| `bouton`    | texte du bouton d'envoi | « Envoyer l'inscription » |
| `fichier`   | nom du fichier de données (`donnees/<fichier>.csv`) | le nom du formulaire |
| `ouverture` | date/heure d'ouverture : `"2026-11-15"` ou `"2026-11-15 09:00"` | — |
| `fermeture` | date/heure de clôture (une date seule = jusqu'à 23 h 59) | — |
| `places`    | nombre maximal d'inscriptions (0 = illimité) | 0 |
| `notifier`  | adresse(s) recevant un courriel à chaque inscription (séparées par des virgules) | — |
| `cache`     | `oui` = ne pas afficher ce formulaire dans la liste (accessible seulement par son adresse) | non |

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
| `titre`      | intertitre de section (rien à saisir) | — (pas de colonne) |
| `info`       | paragraphe d'information (rien à saisir) | — (pas de colonne) |

### Propriétés d'un champ

| clé           | rôle |
|---------------|------|
| `type`        | voir ci-dessus (défaut : `texte`) |
| `libelle`     | la question affichée |
| `aide`        | petit texte d'aide sous la question |
| `obligatoire` | `oui` = réponse exigée (pour `case` : la case doit être cochée) |
| `options`     | réponses possibles pour `choix`, `liste`, `cases`, séparées par `\|` : `"Oui \| Non \| Peut-être"` |
| `defaut`      | valeur pré-remplie (pour `cases`, plusieurs valeurs séparées par `\|`) |
| `exemple`     | texte grisé d'exemple dans la zone de saisie |
| `min`, `max`  | bornes d'un `nombre` ou d'une `date` ; longueur mini/maxi d'un `texte` ou `texte_long` |
| `unique`      | `oui` = refuse une valeur déjà inscrite (par ex. sur l'e-mail, pour éviter les doubles inscriptions) |
| `si`          | condition d'affichage (voir ci-dessous) |

### Champs conditionnels (`si`)

Un champ (ou un titre, ou un paragraphe d'info) peut n'apparaître qu'en fonction d'une réponse
à un champ `choix`, `liste`, `cases` ou `case` **décrit avant lui** :

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

`prive/formulaires/exemple.ini` montre tous les types et options.

Si le fichier contient une erreur (type inconnu, condition vers un champ inexistant, guillemet oublié…),
la page affiche « Formulaire introuvable ou mal décrit » ; le détail de l'erreur est indiqué sur `admin.php`
(ou sur la page elle-même si vous mettez `'debug' => true` dans `config.php`).

Après modification d'un fichier sur le serveur, attendez quelques secondes avant de recharger la page
(certains hébergeurs gardent les fichiers PHP en cache un court instant).

## 4. Récupérer les inscriptions

**Par la page d'administration** — `https://votre-site.fr/inscription/admin.php` :
nombre d'inscrits, état (ouvert, clos, complet), bouton **Télécharger** (fichier `.csv`) et **Voir** (tableau à l'écran).

**Par FTP** — dans `prive/donnees/` : un fichier `<fichier>.csv` par formulaire.

Format des fichiers :

- encodage UTF-8 avec BOM, séparateur `;` (modifiable dans `config.php`) : un double-clic l'ouvre correctement dans
  Excel (version française) ou LibreOffice ; dans LibreOffice, choisissez « UTF-8 » et « point-virgule » si la question est posée ;
- colonnes : `numero` (numéro d'inscription aléatoire, ex. `K7QF-M2ZD`, aussi montré à l'inscrit), `date` (date et heure d'envoi),
  puis un champ par colonne, dans l'ordre du descripteur ;
- les réponses commençant par `=`, `+`, `-` ou `@` sont précédées d'une apostrophe, pour qu'Excel ne les exécute pas comme formules.

**Modifier un formulaire déjà utilisé** : si vous ajoutez, retirez ou renommez un champ, l'ancien fichier est conservé sous le nom
`<fichier>-ancien-AAAAMMJJ-HHMMSS.csv` et un nouveau fichier démarre avec les nouvelles colonnes
(les deux restent téléchargeables depuis `admin.php`). Changer un libellé ou une option ne crée pas de nouveau fichier.

**Supprimer ou corriger une inscription** : téléchargez le fichier par FTP, modifiez-le (en gardant le format CSV,
séparateur `;`, UTF-8), renvoyez-le ; ou supprimez-le pour repartir de zéro.
Pensez à supprimer les données quand elles ne sont plus utiles (RGPD).

## 5. Mettre les données dans une zone privée

Les inscriptions contiennent des données personnelles : personne ne doit pouvoir lire
`https://votre-site.fr/inscription/prive/donnees/xxx.csv`. Trois niveaux de protection, du plus simple au plus sûr :

### a) Par défaut : `.htaccess` (Apache)

Les dossiers `prive/`, `prive/donnees/` et `prive/formulaires/` contiennent un fichier `.htaccess` qui interdit
tout accès web. Cela fonctionne chez la plupart des hébergeurs mutualisés (serveurs Apache ou compatibles).

Attention : les fichiers commençant par un point sont parfois masqués ou non envoyés par le client FTP.
Dans FileZilla : menu *Serveur* → *Forcer l'affichage des fichiers cachés*, et vérifiez qu'ils sont bien présents sur le serveur.

**Vérification** : connectez-vous à `admin.php`, un test automatique s'affiche en haut de la page :
- vert « Dossier des données protégé » : tout va bien ;
- rouge « Attention : le dossier des données est lisible depuis le web » : votre hébergeur ignore `.htaccess`
  (serveur nginx, par exemple) → passez au b).

Vous pouvez aussi tester à la main : `https://votre-site.fr/inscription/prive/config.php` doit afficher une page
d'erreur « 403 Forbidden » (et non une page blanche).

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
| `admin_mot_de_passe` | mot de passe chiffré de `admin.php` (généré par `admin.php` lui-même) |
| `dossier_formulaires`, `dossier_donnees` | emplacement des descripteurs et des données (par défaut dans la zone privée) |
| `separateur` | `;` (Excel français) ou `,` |
| `fuseau` | fuseau horaire des dates (`Europe/Paris`) |
| `liste_publique` | `false` : `index.php` sans `?f=` n'affiche pas la liste des formulaires |
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
- Une clé secrète est créée automatiquement dans `donnees/.secret` au premier usage ; la supprimer invalide les
  formulaires ouverts et déconnecte l'administration (sans autre conséquence).

## 8. Dépannage

| symptôme | cause probable |
|----------|----------------|
| « Formulaire indisponible : installation incomplète » | `emplacement.php` ne pointe pas vers le dossier contenant `lib.php` |
| « L'inscription n'a pas pu être enregistrée » | le dossier `donnees/` n'est pas inscriptible (voir § 2, étape 2) |
| « Formulaire introuvable ou mal décrit » | erreur dans le `.ini` : le détail est affiché sur `admin.php` |
| « La page a expiré » | formulaire resté ouvert plus de 24 h : renvoyer suffit (la saisie est conservée) |
| accents incorrects dans Excel | ouvrir via *Données* → *À partir d'un fichier texte/CSV*, encodage UTF-8 |
| pas de courriel de notification | `mail()` désactivé chez l'hébergeur, ou `expediteur` hors de votre domaine |
