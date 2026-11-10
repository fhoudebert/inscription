[Version française](LISEZMOI.md)

# inscription — registration forms and surveys for shared hosting

Collect registrations, survey answers or **orders** on **shared web hosting** (Free, OVH, o2switch, Infomaniak, Ionos…),
with no database and no programming:

- each form is **described in a text file** (`.ini`), which you can write by hand or create and edit
  in a **visual editor** with a live preview;
- answers are appended to a **CSV file** that opens in Excel, LibreOffice Calc or [GridCraft](https://getartcraft.com/apps/gridcraft);
- a form is **listed** (shown on the home page), **not listed** (opened only through a sharing link)
  or **restricted**: each invited person receives a personal link (and a password), answers once
  and can change their answer — robots and strangers cannot send anything;
- a password-protected **administration page** lets you download the answers, copy the sharing links,
  create personal accesses, and create, edit or delete forms;
- a form can take **orders** from a product catalogue (price, unit, availability) with an estimated total;
  the admin page gives the quantities to prepare, lets you set the final price of each order and prepares the message
  to paste into a text message or an e-mail;
- a **logo** can be shown to the left of the title (site logo, or one per form);
- texts can contain **links**, and the respondent can receive a **confirmation email**;
- the interface is in **French and English**, and a form can be **multilingual** (browser language, switch through a link).

Requirements: **PHP 7.4 or later**. No particular extension, no PHP session, no MySQL database.

## Documentation

| page | contents |
|------|----------|
| **[Creating a form](docs/forms-en.md)** | `.ini` file syntax, field types, conditional fields, listing, restricted access, order form, multilingual forms, examples |
| **[Administration](docs/admin-en.md)** | the `admin.php` page, form editor, retrieving answers, managing forms, personal accesses, catalogues and orders, images (logos), private area, settings, security, troubleshooting, interface languages |

The French documentation is in [LISEZMOI.md](LISEZMOI.md), [docs/formulaires.md](docs/formulaires.md) and [docs/administration.md](docs/administration.md).

## Quick installation

1. Copy the `inscription/` folder by FTP into the public part of the site (`www/`, `public_html/`, the root at Free).
   The form will be at `https://your-site.com/inscription/`. You can also copy the contents of the folder into a folder of your choice. (The form will then be at `https://your-site.com/yourfolder/`.)
2. Check that PHP can write to `prive/donnees/` (and to `prive/formulaires/` to manage forms from the admin page):
   permissions `755`, or `775`/`777` if saving fails.
3. Open `admin.php`, choose a password and paste the line you get into `prive/config.php`.
4. Log in to `admin.php`: the **automatic test** must say, in green, that the data cannot be read from the web.
   Otherwise, see [Private area](docs/admin-en.md#private-area).
5. Create your form in the editor (`admin.php` → **Create a form**) or adapt an example
   from `prive/formulaires/`, then send a test answer.

Details: [Administration → Installation](docs/admin-en.md#installation).

## File layout

```
inscription/                 ← folder to copy to the site
├── index.php                ← public page: list of forms and the forms themselves
├── admin.php                ← administration
├── emplacement.php          ← tells where the private area is
└── prive/                   ← PRIVATE AREA, no web access (.htaccess)
    ├── config.php           ← settings
    ├── lib.php              ← the engine
    ├── editeur.js, .css     ← the form editor of admin.php
    ├── lang/                ← interface texts (fr.php, en.php)
    ├── formulaires/         ← one .ini file per form (+ examples)
    ├── catalogues/          ← product catalogues of order forms
    ├── images/              ← logos shown to the left of the title (served by index.php)
    └── donnees/             ← one .csv answers file per form
```

## A form in a few lines

```ini
[formulaire]
titre     = "Registration for the 5 December meeting"
fermeture = "2026-12-03"
places    = 40

[nom]
type        = texte
libelle     = "Full name"
obligatoire = oui

[repas]
type    = choix
libelle = "Will you stay for lunch?"
options = "Yes | No"
```

Keys are written here in French (their reference names); each one also has an English equivalent
(`[form]`, `title`, `closes`, `limit`, `label`, `required`…), see [Writing in English](docs/forms-en.md#writing-in-english).

Saved as `prive/formulaires/reunion.ini` (by FTP, by upload, or created in the `admin.php` editor), it is online at
`index.php?f=reunion`; answers arrive in `prive/donnees/reunion.csv`.

Examples provided: `simple.ini` (minimal), `exemple.ini` (full registration), `sondage.ini` (satisfaction survey, not listed),
`colloque.ini` (bilingual French / English), `meeting-en.ini` (written in English), `ag-membres.ini` (restricted access with a password),
`panier.ini` (weekly vegetable box order from a market gardener, using the fictitious `legumes` catalogue),
`rencontre.ini` (choice grids: attendance, meals, car sharing over three days),
`frais.ini` (amounts and times: travel expense claim),
`spectacle.ini` (ticket booking with a calculated total price).
Everything is explained in [Creating a form](docs/forms-en.md).

## Personal data

Answers are personal data: they stay in the private area (`prive/`), protected by `.htaccess`
and movable out of the public part of the site. Remember to delete answers once they are no longer needed (GDPR).
See [Private area](docs/admin-en.md#private-area) and [Security](docs/admin-en.md#security).
