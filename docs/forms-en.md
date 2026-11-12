# Creating a form

[← Back to the README](../README.md) · [Administration](admin-en.md) · [Version française](formulaires.md)

Contents: [Principle](#principle) · [Writing rules](#writing-rules) · [Form section](#form-section) ·
[Field types](#field-types) · [Field properties](#field-properties) ·
[Conditional fields](#conditional-fields) · [Grid](#grid) · [Calculated total](#calculated-total) · [Links](#links) · [Confirmation email](#confirmation-email) · [Examples](#examples) · [Listing and sharing link](#listing-and-sharing-link) ·
[Restricted access](#restricted-access) · [Order form](#order-form) ·
[Logo](#logo) · [Multilingual form](#multilingual-form) · [Writing in English](#writing-in-english) ·
[Changing a form already in use](#changing-a-form-already-in-use) · [Errors](#errors)

> Keys and types are given here under their reference (French) names, as the editor writes them.
> Each one has an English equivalent that you can use instead: see [Writing in English](#writing-in-english).

## Principle

One form = one file `prive/formulaires/<name>.ini`, in plain text **encoded in UTF-8**
(Notepad, Notepad++, gedit, Kate…). Its address is `index.php?f=<name>`.
The `<name>` contains only unaccented letters, digits, `-` and `_`.

The file is uploaded by FTP, sent from `admin.php`, or created and edited without writing anything in
the **form editor** (see [Form editor](admin-en.md#form-editor)):
everything described on this page can be set there with checkboxes, lists and a live preview.
Each form has its own answers file, `prive/donnees/<name>.csv`.

`index.php` shows the list of **listed** forms (`diffusion = oui`, the default); if there is only one,
it shows it directly. Unlisted forms are opened through their [sharing link](#listing-and-sharing-link).

## Writing rules

- A `[formulaire]` section gives the general settings, then **one section per field**, in display order.
- The section name of a field (`[email]`, `[full_name]`…) becomes the **column name** in the CSV file:
  unaccented letters, digits and `_`, starting with a letter. `numero`, `date`, `langue`, `acces` and `acces_nom` are reserved.
- **Put texts between double quotes**: `libelle = "Name of the association"`.
  A double quote inside a text is written `\"`. A quoted text can span several lines.
- A line starting with `;` is a comment.
- For yes/no: `oui`, `1`, `yes`, `true` = yes; empty, `non`, `no`, `0` = no.
- The character sequence `${` is forbidden (it would make the server read variables).

## Form section

Settings of the `[formulaire]` section:

| key         | role | default |
|-------------|------|---------|
| `titre`     | title shown at the top of the page | the file name |
| `intro`     | introduction text | — |
| `merci`     | message shown after submitting | "Thank you, your answer has been recorded." |
| `bouton`    | text of the submit button | "Submit" |
| `logo`      | image shown to the left of the title: name of a file in `prive/images/` (see [Logo](#logo)); `non` = no logo | the site logo (`config.php`), if there is one |
| `logo_texte` | alternative text of the logo, read by screen readers (translatable: `logo_texte_en`) | — |
| `fichier`   | name of the answers file (`donnees/<fichier>.csv`); two forms cannot share the same one (keep this in mind when copying an `.ini`) | the form name |
| `ouverture` | opening date/time: `"2026-11-15"` or `"2026-11-15 09:00"` | — |
| `fermeture` | closing date/time (a date alone = until 23:59) | — |
| `places`    | maximum number of answers (0 = unlimited) | 0 |
| `notifier`  | address(es) receiving an email for each answer (separated by commas) | — |
| `confirmation` | `oui`: confirmation email to the respondent, at the address of the first `email` field; or the name of the `email` field to use, or several names separated by `\|` (first address filled in) (see [Confirmation email](#confirmation-email)) | no email |
| `confirmation_texte` | text at the top of the confirmation email (translatable) | the `merci` message |
| `diffusion` | `oui` = listed on the home page; `non` = only reachable through the [sharing link](#listing-and-sharing-link) | oui |
| `acces`     | `personnel` = restricted access with a personal link + password; `lien` = with a personal link only (see [Restricted access](#restricted-access)) | — (open access) |
| `modifiable` | restricted access: `oui` = each person can come back and change their answer; `non` = one final answer | oui |
| `afficher_numero` | `non` = do not show the answer number after submitting (useful for an anonymous survey) | oui |
| `langue`    | interface language for this form (`fr`, `en`…): buttons, error messages, value of checkboxes | that of `config.php` |
| `langues`   | multilingual form: languages offered, e.g. `"fr \| en"` (see [Multilingual form](#multilingual-form)) | — |
| `message`   | order form: template of the message sent to the customer when their order is ready (see [Order form](#order-form)) | default text |

## Field types

| `type`       | display | value recorded |
|--------------|---------|----------------|
| `texte`      | one line of text | the text |
| `texte_long` | multi-line text area | the text |
| `email`      | e-mail address (format checked) | the address |
| `telephone`  | phone number (format checked) | the number |
| `nombre`     | number (limits `min` / `max`) | the number |
| `date`       | calendar (limits `min` / `max` in `YYYY-MM-DD` format) | `YYYY-MM-DD` |
| `montant`    | sum of money: the `devise` symbol (config.php) is shown after the box; accepts `12`, `12,5`, `12.50`, `1 250,00 €`; never negative unless `min` is negative | the amount with two decimals (`12,50`) |
| `heure`      | time picker; also accepts `9h`, `9h05`, `14:30`; `min` / `max` = time range (`06:00`, `23:30`) | the time `HH:MM` |
| `total`      | amount **calculated** from number / amount fields (`calcul = "adultes * 15 + enfants * 8"`), shown live, see [Calculated total](#calculated-total) | the total (`54,00`) |
| `choix`      | radio buttons: a single answer among `options` | the chosen answer |
| `liste`      | drop-down list: one answer among `options` | the chosen answer |
| `cases`      | checkboxes: several answers among `options` | `Morning \| Noon` |
| `case`       | a single checkbox (agreement, GDPR consent…) | `oui` / `non` |
| `echelle`    | rating on one line (1 to 5 by default, or the values of `options`), with `gauche` / `droite` texts at both ends | the chosen rating |
| `grille`     | choice table: one row per question (`lignes`), one column per answer (`colonnes`), see [Grid](#grid) | one column `<name>_<row>` per row |
| `commande`   | table of the available products of a `catalogue`, quantity per product, estimated total (only one per form) | the readable details + a `<name>_total` column |
| `titre`      | section heading (nothing to enter) | — (no column) |
| `info`       | information paragraph (nothing to enter) | — (no column) |

## Field properties

| key           | role |
|---------------|------|
| `type`        | see above (default: `texte`) |
| `libelle`     | the question shown |
| `aide`        | small help text under the question |
| `obligatoire` | `oui` = answer required (for `case`: the box must be ticked) |
| `options`     | possible answers for `choix`, `liste`, `cases`, `echelle`, separated by `\|`: `"Yes \| No \| Maybe"` |
| `lignes`, `colonnes` | for `grille`: questions (rows) and possible answers (columns), separated by `\|` |
| `multiple`    | for `grille`: `oui` = checkboxes, several answers per row |
| `saisie`      | for `grille`: `nombre`, `montant`, `texte` or `liste` = a box to fill in (or a drop-down list) per cell instead of a choice (see [Input grid](#input-grid)) |
| `valeurs`     | for `grille` with `saisie = liste`: choices offered in each drop-down list, separated by `\|` |
| `gauche`, `droite` | for `echelle`: texts shown at both ends (e.g. `"Not at all"` / `"Completely"`) |
| `defaut`      | pre-filled value (for `cases`, several values separated by `\|`) |
| `exemple`     | greyed example text in the input box |
| `min`, `max`  | limits of a `nombre` or a `date`; min/max length of a `texte` or `texte_long` (see [Limits](#limits-min-and-max)) |
| `entier`      | for `nombre`: `oui` = whole numbers only (no decimals) |
| `unique`      | `oui` = refuses a value already recorded (e.g. on the e-mail, to avoid double registrations) |
| `si`          | display condition (see below) |
| `catalogue`   | for `commande`: name of the product catalogue (`prive/catalogues/<name>.csv`) |

### Limits (min and max)

```ini
[groupe]
type    = nombre
libelle = "Number of people (including you)"
min     = 1
max     = 30
entier  = oui
```

- **number**: `min` and `max` accept a comma or a point (`2,5`), and negative numbers; `entier = oui` refuses
  decimals. The limits are shown under the question ("Whole number. Between 1 and 30.") and checked on submission
  ("Between 1 and 30."); on a phone, the numeric keypad opens;
- **amount**: like a number, with two decimals at most; without `min`, a negative amount is refused;
- **time**: `min = 06:00`, `max = 23:30`;
- **date**: `min = 2026-12-01`, `max = 2026-12-31` (`YYYY-MM-DD` format); the browser's calendar only offers these dates;
- **text**, **long text**: minimum / maximum number of characters;
- one of the two limits is enough; a badly written limit, a `min` greater than `max` or a `defaut` value outside the
  limits make the form invalid, with a message saying so.

## Conditional fields

With `si`, a field (or a heading, or an info paragraph) only appears depending on the answer
to a `choix`, `liste`, `cases`, `echelle`, `case`, `nombre` or `date` field **described before it**:

| syntax | meaning |
|--------|---------|
| `si = "profil = Association"` | shown if "Association" is chosen |
| `si = "profil = Association \| Volunteer"` | shown if one of these answers is chosen |
| `si = "profil != Volunteer"` | shown if "Volunteer" is **not** chosen |
| `si = "profil = Association & stand = Yes"` | `&` = both conditions at once |
| `si = "covoiturage = oui"` | for a `case` field (ticked = `oui`, not ticked = `non`) |
| `si = "groupe > 4"` | number: `<`, `<=`, `>`, `>=`, and also `=` / `!=` (comma or point: `2,5`) |
| `si = "arrivee < 2026-12-12"` | date, in `YYYY-MM-DD` format |
| `si = "depart < 07:00"` · `si = "transport > 100"` | time `HH:MM` · amount |
| `si = "satisfaction <= 2"` | rating scale, if all its ratings are numbers |
| `si = "hebergement = Yes & groupe > 4"` | both at once: choice **and** comparison |
| `si = "hebergement = Yes \|\| groupe > 4"` | `\|\|` = **at least one** of the conditions (OR) — to be enabled in `config.php` |

A comparison (`<`, `<=`, `>`, `>=`) only applies to a **single** number or date. **As long as the number (or the date)
has not been entered, the condition is false**: the field stays hidden, even with `!=`. On screen, the display changes as you type.

**OR** (`||`) is disabled by default: set `'conditions_ou' => true` in `prive/config.php` to allow it.
A given condition is either all `&` (all true) or all `||` (at least one true): mixing them is refused.
A condition on a hidden field is false. In the editor, an "All conditions (AND) / At least one condition (OR)" choice
appears as soon as there are two conditions (if OR is enabled).

A hidden field is never required and is not recorded (empty column), even if a value
is forced into the submission. Without JavaScript, all fields are shown but the same rules apply when recording.

## Grid

A **grid** asks the same question for several rows (days, workshops…) in a table, one button per cell:

```ini
[vehiculer]
type        = grille
libelle     = "If some guests have no car, are you available to drive them?"
lignes      = "Friday | Saturday | Sunday"
colonnes    = "Yes | No"
obligatoire = oui
si          = "vehicule = Yes"
```

- **one answer per row** (radio buttons); with `multiple = oui`, **checkboxes** and several answers per row
  (e.g. meals: lunch and dinner);
- `obligatoire = oui`: **each row** must have an answer; forgotten rows are flagged in red;
- **answers file**: one column per row, named `<field>_<row>` in lower case without accents or spaces
  (`vehiculer_friday`, `vehiculer_saturday`, `vehiculer_sunday`), containing the answer (`Yes`), or the answers
  separated by `|` with `multiple = oui`. Renaming a row changes its column (the old file is then archived,
  see [Changing a form already in use](#changing-a-form-already-in-use));
- in the notification email and in an order's message (`{vehiculer}`), the grid is summarised as "Friday: Yes", one line per day;
- multilingual: `lignes_en`, `colonnes_en`, with as many items as `lignes` and `colonnes`;
- on a phone, the rows are shown one below the other with their answers;
- a grid can be conditional (`si = …`), but another field cannot yet depend on the answer to a grid.

### Input grid

With `saisie = nombre` (or `montant`, `texte`), each cell is a **box to fill in** instead of a button:

```ini
[repas]
type     = grille
saisie   = nombre
libelle  = "Type of meal"
lignes   = "Saturday lunch vegetarian | Saturday lunch classic | Sunday lunch vegetarian | Sunday dinner classic"
colonnes = "Number"
entier   = oui
min      = 0
```

- **one column** (or none): one box per row, file column `<field>_<row>` (`repas_saturday_lunch_vegetarian`…);
- **several columns** (`colonnes = "Vegetarian | Classic"`, rows = days): one box per cell, column
  `<field>_<row>_<column>` (`repas_groupe_saturday_lunch_vegetarian`);
- `min`, `max`, `entier` apply to **each cell** and are shown under the question; an invalid cell is outlined
  in red and the message names it ("Saturday dinner / Classic: Whole number expected");
- `obligatoire = oui`: **at least one cell** filled in; empty cells stay empty in the file;
- `saisie = montant` shows the currency and records `12,50`; `saisie = texte`: short text;
- notification and confirmation: "Saturday lunch: Vegetarian 2, Classic 3";
- on a phone, each row is shown with its boxes and the name of their column.

With `saisie = liste`, each cell is a **drop-down list**; its choices are given in `valeurs` (the `colonnes`
remain the table headers):

```ini
[ateliers]
type     = grille
saisie   = liste
libelle  = "Workshops wanted"
lignes   = "Saturday | Sunday"
colonnes = "Morning | Afternoon"
valeurs  = "Pottery | Basketry | Hiking | Singing"
```

- each list starts with "—" (no choice); one file column per cell (`ateliers_saturday_morning`…), containing the choice;
- only the choices in `valeurs` are accepted; `obligatoire = oui`: at least one cell chosen;
- multilingual: `valeurs_en`, with as many items as `valeurs` (the choice is recorded in the base language).

## Calculated total

A `total` field calculates a price from the quantities entered:

```ini
[adultes]
type    = nombre
libelle = "Number of adult tickets"
entier  = oui
min     = 1

[enfants]
type    = nombre
libelle = "Number of child tickets"
entier  = oui
min     = 0

[total]
type    = total
libelle = "Total price"
calcul  = "adultes * 15 + enfants * 8"
```

- `calcul`: terms `field * unit price` (or `price * field`, or `field` alone) separated by `+` or `-`; a number alone
  adds a fixed fee (`+ 2,50`); comma or point;
- the fields used are `nombre`, `montant` or `total` fields **described before**; an empty, unreadable or hidden field counts as 0;
- the total is shown in large type, with the currency, and is updated while typing; it is **recalculated by the server** on
  submission (a value changed in the browser is ignored) then recorded in the answers file;
- it appears in the notification and in the confirmation email ("Total price: 54,00 €");
- it can be used as a condition: `si = "total >= 100"`;
- for a real order form (product catalogue, preparation, final price), see the `commande` type.

## Links

Displayed texts can contain links, in `intro`, `merci`, `aide`, the `libelle` of an `info` and that of a `case`:

```ini
intro = "Programme and directions: [event website](https://example.org/meeting)."

[reglement]
type        = case
libelle     = "I have read the [rules](https://example.org/rules) and accept them"
obligatoire = oui
```

- `[link text](https://…)` shows a clickable "link text"; an address written on its own (`https://example.org`) also
  becomes a link; `mailto:contact@example.org` is accepted;
- only `https://`, `http://` and `mailto:` addresses are recognised (no `javascript:`…); any other markup
  is shown as is. Links open in a new tab, so that what has been entered is not lost;
- in emails (notification, confirmation), `[text](address)` becomes "text (address)".

## Confirmation email

The respondent can receive an email summarising their answer:

```ini
[formulaire]
confirmation       = oui      ; or the name of the email field to use: confirmation = email_contact
confirmation_texte = "Thank you, your registration is recorded. Programme: https://example.org/programme"
```

- an `email` type field is required (the first one, with `oui`); the email is sent to the address entered, after recording;
- **several email fields** (for example one per profile, shown under a condition): list them all, separated by `|`.
  A single email is sent, to the **first address filled in**, in the order given; a field hidden by its condition is empty,
  so it is skipped. In the editor, as soon as there are two email fields, you tick the ones to use (form order):

  ```ini
  [formulaire]
  confirmation = "email_asso | email_perso"

  [profil]
  type    = choix
  libelle = "You are:"
  options = "Association | Individual"

  [email_asso]
  type    = email
  libelle = "Association's email"
  si      = "profil = Association"

  [email_perso]
  type    = email
  libelle = "Your email"
  si      = "profil = Individual"
  ```
- contents: `confirmation_texte` (otherwise the `merci` message), the answer number (if `afficher_numero`), then all the
  answers, **in the respondent's language**; after a change (restricted access, `modifiable`), the subject says so;
- the thank-you page mentions that the email was sent; "Reply" to the email writes to the first address in `notifier`;
- safeguards against robots that would enter someone else's address: at most one email per address and per form
  every 10 minutes, and at most `confirmation_max_heure` (30) emails per hour for the whole site;
- `'confirmation' => false` in `config.php` turns these emails off for the whole site (a warning in `admin.php` reminds you);
- sending uses the host's `mail()`, like notifications: set `expediteur` to an address of your own domain,
  otherwise messages may end up in spam (see [Settings](admin-en.md#settings)).

## Examples

### Registration

```ini
[formulaire]
titre     = "Registration for the 5 December meeting"
fermeture = "2026-12-03"
places    = 40

[nom]
type        = texte
libelle     = "Full name"
obligatoire = oui

[email]
type        = email
libelle     = "E-mail address"
obligatoire = oui
unique      = oui

[repas]
type        = choix
libelle     = "Will you stay for lunch?"
options     = "Yes | No"
obligatoire = oui

[regime]
type    = liste
libelle = "Special diet"
options = "None | Vegetarian | Gluten-free"
si      = "repas = Yes"
```

### Survey

`prive/formulaires/sondage.ini` is an anonymous satisfaction questionnaire (in French):
ratings on a scale (`echelle`, from 1 to 5 or from 0 to 10), a drop-down list "How did you hear about the day?"
with a "Please specify" field that only appears for "Other", multiple choices and free comments.
No name or e-mail is asked, `afficher_numero = non` avoids showing an answer number,
and `diffusion = non` reserves it for the people who receive the link.
Ratings arrive in the CSV as numbers: averages and charts are done directly in the spreadsheet.

```ini
[satisfaction]
type        = echelle
libelle     = "Overall, are you satisfied with the day?"
gauche      = "Not at all"
droite      = "Completely"
obligatoire = oui
```

### Provided examples

| file | contents |
|------|----------|
| `simple.ini` | minimal example, to copy to get started |
| `exemple.ini` | full registration for a one-day event: almost all types, conditions, two drop-down lists |
| `sondage.ini` | anonymous satisfaction questionnaire, not listed |
| `colloque.ini` | bilingual French / English form |
| `meeting-en.ini` | form written with the English keys, English interface |
| `ag-membres.ini` | members' reply to a meeting notice, restricted access (link + password), editable answer |
| `panier.ini` | weekly vegetable box order from a market gardener on the `legumes` catalogue (fictitious data) |
| `spectacle.ini` | show ticket booking: date (date range), adult / child tickets, calculated total price, conditional info on the total, confirmation |
| `frais.ini` | expense claim: departure and return times (time range), amounts (with a maximum), conditional info (`depart < 07:00`, `transport > 100`) |
| `rencontre.ini` | three-day gathering: attendance, meal (several answers) and car-sharing (conditional) grids, links, confirmation email |

Apart from `meeting-en.ini` and the English version of `colloque.ini`, the examples are written in French.

## Listing and sharing link

- `diffusion = oui` (default): the form appears in the list of `index.php` and also opens with `index.php?f=<name>`.
- `diffusion = non`: it appears **nowhere**; `index.php?f=<name>` answers "Page not found".
  It only opens with its **sharing link**, which contains a unique key:
  `https://your-site.com/inscription/index.php?f=sondage&k=352478770464ea7d`.
  This link is shown in `admin.php`, with a **Copy** button: send it only to the people concerned.

The key is calculated from the site's secret key (`prive/donnees/.secret`) and the form's name:
it cannot be guessed, it does not change as long as these two elements do not change, and it differs from one site to another.
To **revoke** a link, rename the form (new `.ini` file name): the old link stops working.
Deleting `.secret` changes the links of all unlisted forms at once.

The old syntax `cache = oui` is still accepted and is equivalent to `diffusion = non`.

## Restricted access

To reserve a form for known people (members, guests, jury…) and prevent any input by robots
or strangers:

```ini
[formulaire]
titre      = "General meeting — members' reply"
acces      = personnel     ; or: lien
modifiable = oui           ; default
```

| `acces` | what each person receives | for whom |
|---------|---------------------------|----------|
| `personnel` | a **personal link** (`index.php?f=ag&i=K7QF-M2ZD`) **and a 10-character password** | the safest, especially if the link and the password are sent through different channels (e-mail + paper, text message…) |
| `lien` | a **secret personal link** only (`…&i=K7QF-M2ZD&t=…`): nothing to type | the simplest; forwarding the link means forwarding the access |

How it works:

- accesses (one per person, with their name or address) are created in `admin.php`, then the list is downloaded
  and distributed **outside the application** (mail merge, post…) — see [Personal access](admin-en.md#personal-access);
- **a single answer per access**. With `modifiable = oui`, a person who comes back with their link (and password)
  finds their answer pre-filled and can change it until closing: the answer is **replaced**, not added;
  with `modifiable = non`, they see "You have already answered this form";
- the answers file contains two more columns, `acces` (identifier) and `acces_nom` (name given to the access, or else its e-mail or phone):
  you know who answered what;
- a restricted form **never** appears in the public list, and without a valid link the address answers "Page not found";
- `places` limits the number of people who answer; a change does not count as a new answer;
- in `personnel` mode, the login lasts 2 hours ("log out" link on the page); after 10 wrong passwords,
  the access is locked for 15 minutes;
- **fields already known**: if the access was created with a name, an e-mail or a phone number ("Mary Smith ; mary@example.com"),
  this information pre-fills the `nom` field (or else the first short text), the first `email` field and the first
  `telephone` field of the form. For this person, these fields are **no longer required**: left empty, they
  take the known value; the person can also correct them. A field shown under a condition (`si`) is never pre-filled.

Full example: `prive/formulaires/ag-membres.ini`.

## Order form

To take orders (vegetable boxes, bread, parcels, meals…): a `commande` type field shows the **available**
products of a catalogue, with their price and unit; the customer gives the quantities and sees the **estimated total**
being calculated.

```ini
[formulaire]
titre   = "This week's box"
message = "Hello {nom},
Your order no. {numero} is ready:
{detail}
Total to pay: {total}
{note}"

[nom]
type        = texte
libelle     = "Full name"
obligatoire = oui

[telephone]
type        = telephone
libelle     = "Phone"
obligatoire = oui

[panier]
type        = commande
libelle     = "Your order"
catalogue   = legumes
obligatoire = oui
```

- **The catalogue** (`prive/catalogues/legumes.csv`) is managed in `admin.php`: products, units, prices, quantity steps,
  "available" box to tick each week; import from a spreadsheet possible. See [Catalogues and orders](admin-en.md#catalogues-and-orders).
  The same item can appear several times with different units and prices (carrots by the bunch and by the kilo, Chinese cabbage, curly kale…).
- **The step** of a product (0,5 for half a kilo, 0,1 for 100 g) sets the minimum quantity and its multiples; without a step, the quantity is free.
- **Prices are always those of the catalogue** at the time of ordering: a price changed in the browser is ignored.
  A product that has become unavailable in the meantime is refused with a clear message.
- **The answers file** contains a readable column ("Carrot (kg): 1,5 × 1,00 € = 1,50 €", one line per product)
  and a `<name>_total` column with the estimated total as a number. The structured details (for preparation) are stored
  separately, in `prive/donnees/commandes/`.
- **The message** (`message`) is the text offered to the seller to notify the customer, to copy into a text message or an e-mail.
  Markers: `{numero}`, `{date}`, `{detail}` (one line per product), `{total}`, `{note}` (note entered during preparation)
  and `{field_name}` for any answer of the form (`{nom}`, `{telephone}`, `{retrait}`…). Without `message`, a default text is used.
- **Access reserved for customers (optional)**: with `acces = lien` (or `personnel`), each customer receives a personal link;
  their name, phone and e-mail, given when the accesses were created, are already filled in and no longer required.
  With `modifiable = oui` (default), they find their order pre-filled and can change it; if it had already been prepared,
  the preparation page says so. Without `acces`, the form stays open to everyone. In the editor: Form settings
  → **Listing and access** → **Who can answer**.
- A form can only contain **one** `commande` field. The amount shown is indicative: the final price
  (weighing, missing product, discount) is set in the administration.

Full example: `prive/formulaires/panier.ini` and the fictitious catalogue `prive/catalogues/legumes.csv`.

## Logo

An image (logo of the association, of the company…) can be shown **to the left of the title** of the form, and also on the
thank-you page and the login page of restricted forms:

```ini
[formulaire]
titre      = "This week's box"
logo       = "exemple-panier.png"
logo_texte = "The example gardens"
```

- the image is stored in `prive/images/`: **Images** section of `admin.php`, upload button in the editor, or FTP
  (see [Images](admin-en.md#images)); it is served by `index.php`, the private area stays closed;
- accepted formats: **PNG, JPEG, GIF, WebP**, 1 MB and 4000 × 4000 pixels at most. **SVG is refused** (it can contain scripts);
- shown at 80 pixels high at most (56 on a phone): use an image about 160 pixels high for a sharp result;
- without a `logo` key, the **site logo** is shown (`logo` key of `config.php`, also on the list of forms);
  `logo = non` removes it for this form;
- `logo_texte` describes the image for screen readers; leave it empty if the title is enough;
- an incorrect image name (path, unknown extension) makes the form invalid; a missing image is simply
  left out, with a warning in `admin.php`.

## Multilingual form

```ini
[formulaire]
langues  = "fr | en"            ; the first one is the base language
titre    = "Colloque du 20 mars"
titre_en = "Symposium, 20 March"

[statut]
type       = choix
libelle    = "Vous êtes :"
libelle_en = "You are:"
options    = "Étudiant | Chercheur | Autre"
options_en = "Student | Researcher | Other"
```

- **Translating a text**: add `_en` (or `_de`, `_es`… depending on the language) to its key. Translatable:
  `titre`, `intro`, `merci`, `bouton` in `[formulaire]`; `libelle`, `aide`, `exemple`, `gauche`, `droite`, `options` in a field.
  `options_en` must have **as many choices, in the same order**, as `options` (otherwise the form is refused).
  An untranslated text is shown in the base language.
- **Language shown**: that of the visitor's browser if it is one of `langues`, otherwise the base language.
  **Français · English** links at the top of the page let you switch; the choice is remembered by the browser.
  Direct link to a language: `index.php?f=colloque&lang=en`.
- **Values recorded**: always in the **base language** (`Étudiant` even if the visitor ticked `Student`),
  so that the column stays consistent and sortable; a `langue` column gives the language used by each respondent.
  `si` conditions are therefore also written with the values of the base language.
- The languages listed must exist in `prive/lang/` (otherwise the form is refused with a clear message);
  to add one, see [Interface languages](admin-en.md#interface-languages).

Full example: `prive/formulaires/colloque.ini` (conditional fields, drop-down list, scale, checkboxes, everything translated).

A form **in a single language other** than the site's does not need `langues`:
`langue = en` is enough for the buttons and messages to be in English.

## Writing in English

All keys and all types have an English equivalent, which can be mixed with French:

| French | English | | French | English |
|--------|---------|-|--------|---------|
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
| `diffusion` | `listed` | | `telephone`, `nombre`, `montant`, `heure`, `total` | `phone`, `number`, `amount`, `time`, `sum` |
| `afficher_numero` | `show_number` | | `choix`, `liste` | `choice` (or `radio`), `dropdown` (or `select`) |
| `acces` = `personnel` / `lien` | `access` = `personal` / `link` | | `modifiable` | `editable` |
| `langue` / `langues` | `language` / `languages` | | `cases`, `case` | `checkboxes`, `consent` |
| | | | `echelle`, `titre` | `scale`, `heading` |

See `prive/formulaires/meeting-en.ini`. With `language = en`, buttons and messages are in English and a ticked box
is recorded as `yes` / `no` (instead of `oui` / `non`).

The same registration form as in [Examples](#registration), written with the English keys:

```ini
[form]
title  = "Registration for the 5 December meeting"
closes = "2026-12-03"
limit  = 40
language = en

[name]
type     = text
label    = "Full name"
required = yes

[lunch]
type     = choice
label    = "Will you stay for lunch?"
options  = "Yes | No"
```

## Changing a form already in use

- Changing a **label**, a help text, the order of options or the dates does not affect the answers already received.
- Adding, removing or renaming a **field** changes the columns: at the next answer, the old file is kept under the name
  `<file>-ancien-YYYYMMDD-HHMMSS.csv` and a new file starts (both can still be downloaded from `admin.php`).
- Renaming an **option** archives nothing, but old answers keep the old text.

## Errors

If the file contains an error (unknown type, condition on a non-existent field, missing quote…),
the page shows "Form not found or incorrectly described"; the details of the error are given on `admin.php`
(or on the page itself with `'debug' => true` in `config.php`). A file sent through `admin.php` is checked
before being installed: in case of error, it is refused and nothing changes.

The most common writing errors are reported with their line number:

| message | fix |
|---------|-----|
| "line 12: "[c_8_detail" — the closing bracket ] of the section name is missing" | write `[c_8_detail]` |
| "the opening bracket [ of the section name is missing" | a `name]` line: write `[name]` |
| "the quoted text starting here is never closed" | add the final `"` of the text started on that line |
| "tests "(Saturday morning", which is not an option" | in `si`, no parentheses; values written exactly as in `options` |
| "“>” only applies to a number, a date or a rating scale" | comparison on a text or choice field: use `=` / `!=` |

After changing a file by FTP, wait a few seconds before reloading the page
(some hosts keep PHP files in cache for a short while).
