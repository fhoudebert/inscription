# Administration

[← Back to the README](../README.md) · [Creating a form](forms-en.md) · [Version française](administration.md)

Contents: [Installation](#installation) · [The admin.php page](#the-adminphp-page) · [Form editor](#form-editor) ·
[Retrieving the answers](#retrieving-the-answers) ·
[Managing forms](#managing-forms) · [Personal access](#personal-access) · [Catalogues and orders](#catalogues-and-orders) ·
[Images](#images) · [Private area](#private-area) · [Settings](#settings) ·
[Interface languages](#interface-languages) · [Security](#security) · [Troubleshooting](#troubleshooting)

## Installation

1. Copy the `inscription/` folder by FTP (FileZilla…) into the public part of your site:
   `www/`, `public_html/`, `htdocs/` or the root at Free.
   The form is then at `https://your-site.com/inscription/`.
   You can rename the folder (`registrations`, `day2026`…): the address changes accordingly.
2. The `prive/donnees/` folder must be **writable** by PHP. This is the default with most
   hosts; otherwise, in FileZilla: right-click on `donnees` → *File permissions* → `755`
   (or `775`/`777` if saving still fails). To add forms from `admin.php`,
   the same applies to `prive/formulaires/`.
3. Open `https://your-site.com/inscription/admin.php` and choose a password: the page shows a line
   to paste into `prive/config.php` (in place of `'admin_mot_de_passe' => '',`). Upload `config.php` to the server again.
   The password itself is stored nowhere, only its hash.
4. Log in to `admin.php`: an **automatic test** checks that the data cannot be read
   from the web. If a red message appears, follow [Private area](#private-area).
5. Delete or adapt the examples in `prive/formulaires/`, then send a test answer.

Files whose names start with a dot (`.htaccess`) are sometimes hidden or not uploaded by the FTP client:
in FileZilla, menu *Server* → *Force showing hidden files*, and check that they are present on the server.

Free (personal pages): nothing special to do, the tool does not use PHP sessions
(so the `sessions/` folder is not needed).

## The admin.php page

Address: `https://your-site.com/inscription/admin.php` (password, login valid for 4 hours).

At the top of the page: the result of the [protection test](#private-area), the **English · Français** links and **Log out**.
Then, for each form:

- title, file name, label **listed**, **not listed — access by link** or **personal access**, languages if it is multilingual;
- number of answers (and of places), status: open, not open yet, closed, full;
- **sharing link** with **Copy** and **Open** buttons — this is the link to send for a form that is not listed
  (see [Listing and sharing link](forms-en.md#listing-and-sharing-link)); for a restricted form,
  it is replaced by the **Manage access** section (see [Personal access](#personal-access));
- **Edit** (opens the [editor](#form-editor)), **Download** the answers file (`.csv`), **View** the answers
  in a table, **Download the descriptor (.ini)**;
- **Delete the form**, **Delete the answers** (see [Managing forms](#managing-forms)).

A badly written form is shown with the details of the error and a **Fix in the editor** button.
Two forms that write to the same answers file (FTP copy of an `.ini` containing `fichier = …`)
are flagged with a red warning on each of them: answers mixed up and places shared, to be fixed.
At the bottom: **Create a form**, uploading an `.ini` file, and the "Other files" section (previous versions
of answers files, answers of deleted forms).

**Disabling the page**: `'admin_actif' => false` in `prive/config.php`. `admin.php` then answers "Page not found"
(you can also delete the file); everything is done by FTP. Since the automatic test is no longer shown,
check the protection by hand (see [Private area](#private-area)).

## Form editor

To create or edit a form without writing any `.ini`: a form's **Edit** button, or the
**Create a form** section (empty form, "Registration" or "Survey" template, or copy of an existing form).
The editor requires `'admin_gestion' => true`, JavaScript and a computer screen (on a small screen, the columns are stacked).

The page has three columns:

- **on the left, the list**: "Form settings" then the fields in display order. Fields are reordered
  by **drag and drop** (⠿ handle; dropping a field on "Form settings" puts it first), or with the
  **↑ ↓** buttons of the selected field, which also has **⧉** to duplicate it and **✕** to delete it. At the bottom, **Add** a field
  of the chosen type (it is placed after the selected field). A field with an error is outlined in red;
- **in the middle, the properties** of the selected field, depending on its type: question, column name, possible answers
  (one per line), help text, example, required, unique value, limits, pre-filled value, and the
  **Show if…** assistant (choose a field placed before, "is one of" / "is not one of", tick the answers;
  several conditions = all at once). For the form settings (contents at the top of the panel): title,
  **Listing and access** (who can answer: everyone or personal access; listing; notification email; confirmation email),
  texts, period and places, languages;
- **on the right, the preview**, refreshed as you type and produced by the same code as the public page: what you see there
  is what respondents will see (conditions included: you can tick answers to try them).
  Clicking a field in the preview selects it. Above it: "Form is valid" or the error to fix.

The **column name** of a new field is derived from its question ("Full name" → `full_name`) as long as you do not
change it; renaming a field updates the conditions that refer to it (and the confirmation email setting). Deleting a field removes the conditions that
depended on it (after confirmation).

**Multilingual forms**: as soon as another language is ticked in the settings, **Français (base) · English** tabs
appear above the properties; in the tab of another language, only the texts are translated, with the base
text as a reminder. A translated list of answers that does not have the same number of lines as the original is flagged.
The preview language is chosen at the top of the preview.

**Keyboard shortcuts**: **Ctrl+S** save; **Alt+↑ / Alt+↓** move the selected field (even while typing);
outside input fields: **↑ / ↓** select the previous or next field, **Del** delete the field (after confirmation),
**Ins** add a field of the type shown below the list.

**Save** (or Ctrl+S): the file is checked like an uploaded file; the previous version is kept in
`prive/formulaires/supprimes/`. If the columns change while answers exist, the editor says so permanently
above the preview and asks for confirmation (the current answers file will be archived at the next answer).
If the file was changed in the meantime (by FTP or in another window), saving is refused: reload the page.
It is also refused if the **answers file** (Advanced settings) is already used by another form: the error appears
in the preview; leave the name empty (form name) or choose another one.
For a new form, first give its **name in the address** (at the top of the page).

**Text mode**: button at the top of the page, to edit the `.ini` directly, with the same checks and the same preview.
You can only go back to visual mode with a valid text. This is also how you fix a badly written form.

Good to know: saving from the editor **rewrites the whole file**. Comments (`;`) written by hand
disappear, English keys are rewritten in French and the order of the keys is normalised; the content itself
is strictly the same. The previous version stays in `supprimes/`. In text mode, the text is saved as is.

## Retrieving the answers

- **Through `admin.php`**: each form's **Download** button.
- **By FTP**: folder `prive/donnees/`, one `<file>.csv` file per form.

File format:

- UTF-8 encoding with BOM, `;` separator (can be changed in `config.php`): a double-click opens it correctly in
  Excel (French version), LibreOffice Calc or [GridCraft](https://getartcraft.com/apps/gridcraft) (free, open spreadsheet);
  in LibreOffice, choose "UTF-8" and "semicolon" if asked; with an English version of Excel, which expects commas,
  set `'separateur' => ','` or import the file (see [Troubleshooting](#troubleshooting));
- columns: `numero` (random answer number, e.g. `K7QF-M2ZD`, also shown to the respondent), `date` (date and time sent),
  `langue` (multilingual forms only: the respondent's language), `acces` and `acces_nom` (restricted forms:
  identifier and name of the access that answered), then one column per field, in the order of the descriptor;
- answers starting with `=`, `+`, `-` or `@` are prefixed with an apostrophe, so that Excel does not run them as formulas.

`<file>-ancien-YYYYMMDD-HHMMSS.csv` files: previous answers set aside when a form's fields changed
(see [Changing a form already in use](forms-en.md#changing-a-form-already-in-use)).

**Correcting or deleting an answer**: download the file by FTP, edit it (keeping the CSV format,
`;` separator, UTF-8), upload it again. To erase everything: delete the file by FTP, or use the **Delete the answers** button.
Remember to delete the data once it is no longer needed (GDPR).

## Managing forms

From `admin.php`, without FTP (setting `'admin_gestion' => true`, the default):

- **Add**: section "Add or replace a form", choose an `.ini` file on your computer.
  The name in the address is that of the file (`reunion.ini` → `index.php?f=reunion`), or the one you give.
- **Edit**: **Download the descriptor (.ini)** button, correct the file, upload it again ticking **Replace**.
  The previous version is kept in `prive/formulaires/supprimes/`. If fields were added, removed or renamed,
  the page warns that the current answers file will be archived at the next answer.
- **Delete a form**: **Delete the form** link, tick **I confirm**. The descriptor is moved to
  `prive/formulaires/supprimes/` (recoverable by FTP); answers already received are **kept** and can still be downloaded
  ("Other files" section).
- **Delete answers**: **Delete the answers** link, tick **I confirm**: the CSV file is **permanently erased**.

Safeguards:
- the uploaded file is **fully checked before being installed** (syntax, types, options, conditions, languages, UTF-8);
- it is refused if its answers file (`fichier` key, or form name) is already used by another form —
  which happens when copying an `.ini` that contains a `fichier = …` line;
  in case of error, nothing is changed and the message gives the error (field or line concerned);
- 200 KB at most, name limited to unaccented letters, digits, `-` and `_`, `.ini` extension enforced:
  the file can never become an executable program;
- the character sequence `${` is refused (it would allow reading server variables);
- each action is protected by a token tied to the login (a booby-trapped page cannot trigger it on your behalf);
- PHP must be able to write to `prive/formulaires/`; otherwise, the page says so and management remains possible by FTP.

With `'admin_gestion' => false`, `admin.php` is only used to view and download: the add and delete buttons disappear.

## Personal access

For a form declared `acces = personnel` or `acces = lien` (see [Restricted access](forms-en.md#restricted-access)),
the form's entry in `admin.php` shows the number of accesses and answers, and a **Manage access** section:

1. **Create**: paste the list of people, one per line, with their name and/or e-mail and/or phone number, in
   any order and with any separator ("Mary Smith ; mary@example.com", "John Martin 06 12 34 56 78",
   "paul@example.com") — a column copied from a spreadsheet works. Or give a number of unnamed accesses (500 at most
   at a time), then **Create the accesses**. The name, e-mail and phone will pre-fill the matching fields of the
   form (see [Restricted access](forms-en.md#restricted-access)).
2. **Distribute**: the page then shows, **only once**, each identifier with its password and its personal link,
   and a **Download the list (CSV)** button (`identifiant;nom;email;telephone;mot_de_passe;lien`, without `mot_de_passe` in `lien` mode).
   This file is used for mail merge (LibreOffice, Thunderbird…) or printing. Passwords and link tokens
   are only kept as hashes: they can never be shown again.
3. **Follow up**: the access table shows for each one its name and contact, its status (active, revoked, locked after too many attempts)
   and the date of its answer and of its last change.
4. **Act** on an access:
   - **Revoke**: the access can no longer open the form (its answer, if any, is kept); **Reactivate** undoes this
     and unlocks a locked access;
   - **New password** / **New link**: in case of loss or leak; the old one stops working immediately
     and the new one is shown once, as at creation.

Accesses are stored in `prive/donnees/acces/<file>.csv` (identifier, name, e-mail, phone, hash, status, dates, failed attempts),
in the private area. They stay in place if the form is replaced; they are tied to the name of the answers file
(`fichier` key). These actions require `'admin_gestion' => true`.

## Catalogues and orders

For forms that contain a `commande` field (see [Order form](forms-en.md#order-form)).

### Catalogues

**Product catalogues** section of the dashboard: list of catalogues (number of products and of available products),
**New catalogue**, **Import a CSV**. The **Edit** button opens the catalogue as a table:

- one row per product: **Available** (checkbox), **Category** (groups products on the form), **Product**
  (with the variety: "Curly kale"), **Unit** (kg, bunch, piece, 250 g punnet…), **Price** per unit, quantity **Step**;
- **All available** / **None available** (applies to the rows shown: combined with the filter, you can for instance tick
  all the cabbages at once), **Filter…**, **Add a product**, **remove** checkbox to delete a row;
- **Save**: changes apply to the following orders; orders already placed keep the prices
  of the time they were placed.

So each week, you just open the catalogue, tick what is available and adjust the prices.

**Import a CSV** (from a spreadsheet, `;` or `,` separator): at least a `Product` column; recognised columns
`Category`, `Product`, `Variety` (added to the name), `Unit`, `Price` (or `Unit price`), `Step`, `Available`
(the French headers `Catégorie`, `Produit`, `Variété`, `Unité`, `Prix`, `Pas`, `Disponible` work too).
Rows without a product name are ignored; an empty `Available` column means "available". Then check the table
and save. **Download** gives the catalogue as CSV, to rework it in a spreadsheet and import it again.

Catalogues are in `prive/catalogues/` (private area).

### Orders

On the entry of an order form, the **Orders (n in progress)** button opens the list:

- filters **In progress** (to prepare + ready), **to prepare**, **ready**, **handed over**, **cancelled**, **All**;
- for each order: number, date, customer (first answers), number of products, total (estimated or final), status,
  **Prepare** button; at the bottom, the total of the orders shown;
- **Quantities to prepare**: the sum, product by product, of the orders shown (excluding cancelled ones) — so you know
  how much to harvest or prepare;
- **Export the orders (CSV)**: one row per order with the answers, the details, the estimated total and the final total.

The **Prepare** page of an order:

1. recalls the customer's answers;
2. **Preparation and final price**: for each product, the quantity ordered, the quantity **served** (weighed, 0 if
   missing) and the **unit price**, all editable; **+ Add a product** (replacement, something forgotten); an
   **adjustment** line (delivery fee, discount as a negative amount); the final total is recalculated live;
3. **Status** (to prepare, ready, handed over, cancelled) and **Note for the customer**;
4. **Save the preparation**, then **Message to send**: the text (the form's `message` template) includes
   the final quantities and prices; you can touch it up, then **Copy** it and paste it into a text message or an e-mail,
   with your own means. The application sends nothing itself.

If the customer changes their order after it was prepared (restricted, editable form), the page says so;
**Start again from the customer's order** takes their new order.

The details of the orders are in `prive/donnees/commandes/<file>.json`; they are deleted with the answers file
(**Delete the answers**). Creating or editing a catalogue and preparing an order require `'admin_gestion' => true`.

## Images

**Images (logos)** section of the dashboard: the images in `prive/images/`, with their size and the forms that
use them (or "site logo"). You can **add an image** there (PNG, JPEG, GIF or WebP, 1 MB at most; SVG refused) and
**delete** an unused image — an image still in use cannot be deleted.

- The type is checked on the file's content, not its name; the name is simplified (`Logo Asso été.JPG` → `logo-asso-ete.jpg`)
  and numbered if it already exists.
- Images stay in the private area: `index.php?image=<name>` serves them (with browser caching), without opening the rest of the folder.
- In the editor, **Form settings → Logo**: choose the site logo, no logo or an image, or upload a new one,
  and enter the alternative text. The preview shows it immediately.
- **Site logo**: `'logo' => 'my-logo.png'` in `prive/config.php` (and `'logo_texte'`), shown on all public
  pages except those of forms that have `logo = …` or `logo = non`. A logo that cannot be found is flagged in red.
- Adding or deleting an image requires `'admin_gestion' => true`; by FTP, simply drop the file into `prive/images/`
  (name made of letters, digits, `-` and `_`).

## Private area

Answers contain personal data: nobody must be able to read
`https://your-site.com/inscription/prive/donnees/xxx.csv`. Three levels of protection, from the simplest to the safest:

### a) Default: .htaccess (Apache)

The folders `prive/`, `prive/donnees/` (and `donnees/acces/`, `donnees/commandes/`), `prive/formulaires/`, `prive/catalogues/` and `prive/lang/` contain an `.htaccess` file that forbids
any web access. This works with most shared hosts (Apache or compatible servers).

**Check**: log in to `admin.php`, an automatic test is shown at the top of the page:
- green "Data folder protected": all is well;
- red "Warning: the data folder can be read from the web": your host ignores `.htaccess`
  (an nginx server, for example) → go to b).

You can also test by hand: `https://your-site.com/inscription/prive/formulaires/simple.ini` must show
a "403 Forbidden" error page. If the contents of the file are shown, the protection is not working.
(Tested under Apache 2.4: with `.htaccess` taken into account, all files in `prive/` answer 403;
without it, the CSV files can be downloaded by anyone and `admin.php` shows the red warning.)

### b) Safest: move prive/ out of the public part

If your hosting has a folder above `www/` (OVH, o2switch, Infomaniak, cPanel in general):

1. move the `prive/` folder **next to** `www/` (not inside it), and rename it, for example, `inscription-prive`:
   ```
   /home/myaccount/
   ├── inscription-prive/      ← the former prive/ folder
   └── www/
       └── inscription/
           ├── index.php
           ├── admin.php
           └── emplacement.php
   ```
2. edit `emplacement.php` to give the new path:
   ```php
   return dirname(__DIR__, 2) . '/inscription-prive';
   ```
   (`__DIR__` = the `www/inscription` folder, `dirname(__DIR__, 2)` = `/home/myaccount`). A full path also works:
   `return '/home/myaccount/inscription-prive';` — the exact path is usually shown in the host's customer area.
3. open `admin.php`: the green message "The data folder is outside the public part of the site" confirms the change.

At Free (personal pages), there is no folder above the site root: stay with solution a), or add c).

### c) In addition: a folder name that cannot be guessed

Rename `prive/` to a random name (e.g. `prive-8f3k2q9xw7`) and give it in `emplacement.php`:
```php
return __DIR__ . '/prive-8f3k2q9xw7';
```
Never publish this name (no link to it). The names of the answers files can also be made unpredictable
with the `fichier = "day-x7p2m9"` key in the descriptor.

### Protecting admin.php too (optional)

`admin.php` is already protected by its password (attempts slowed down, login valid for 4 hours).
You can add your host's password protection ("protected directory" / `.htpasswd`),
but it would apply to the whole folder — so also to the public form — unless you only protect `admin.php`
with a `<Files admin.php>` directive in an `.htaccess` next to it.

## Settings

File `prive/config.php`:

| key | role |
|-----|------|
| `langue` | default interface language: `fr` (default) or `en` |
| `admin_actif` | `true` (default): `admin.php` page enabled; `false`: disabled, retrieval by FTP only |
| `admin_gestion` | `true` (default): `admin.php` can add, replace and delete forms and answers; `false`: view only |
| `admin_mot_de_passe` | hash of the `admin.php` password (generated by `admin.php` itself) |
| `dossier_formulaires`, `dossier_donnees` | location of the descriptors and answers (in the private area by default) |
| `separateur` | `;` (French Excel) or `,` |
| `fuseau` | time zone of the dates (`Europe/Paris`) |
| `liste_publique` | `false`: `index.php` without `?f=` shows no list, not even listed forms |
| `longueur_max` | maximum length of an answer |
| `expediteur` | sender address of notifications (often required to be an address of your own domain) |
| `devise` | currency symbol of order forms: `€` (default), `CHF`, `$`… |
| `confirmation` | `true` (default): forms may send a confirmation email to the respondent; `false`: never |
| `confirmation_max_heure` | maximum number of confirmation emails per hour for the whole site (default 30; 0 = no limit) |
| `conditions_ou` | `false` (default); `true` allows OR (`\|\|`) in `si` conditions |
| `logo`, `logo_texte` | default logo to the left of the title of public pages (image in `prive/images/`) and its alternative text; empty = none (see [Images](#images)) |
| `debug` | `true`: shows the details of errors (set back to `false` afterwards) |

## Interface languages

The interface (buttons, error messages, administration page, notification email) exists in **French** and **English**:

- **home page** (list of forms): in the browser's language if it is installed, otherwise that of `config.php`;
  **English · Français** links to change it (choice remembered); titles of multilingual forms are translated there;
- default language: `'langue' => 'fr'` in `prive/config.php`;
- **forms**: their language is set in their descriptor, with `langue = en` or, for a form in several languages,
  `langues = "fr | en"` (see [Multilingual form](forms-en.md#multilingual-form));
- **administration page**: **English · Français** links at the top of the page (choice remembered by the browser).

**Adding a language** (German, Spanish…): copy `prive/lang/fr.php` (or `en.php`) as `de.php`, `es.php`…,
translate the texts on the right without touching the names on the left or the `{n}`, `{titre}`… markers, then upload it to the server.
The language appears immediately on the home page and in `admin.php`, and can be used with `langue = de`
or `langues = "fr | de"`. A missing interface text is shown in French.

## Security

- Private area protected by `.htaccess`, movable out of the site, with a self-test on `admin.php`.
- Signed tokens (HMAC) against forged submissions, without PHP sessions; submissions in less than 3 seconds are refused,
  and an invisible honeypot field stops robots.
- Server-side validation of every field (formats, allowed options, conditions), even if the browser is bypassed.
- Writing under a lock (`flock`): two simultaneous answers do not get mixed up, and the limit on places is respected.
- Escaping of all HTML output; Excel formulas neutralised in the CSV.
- Unlisted forms: cannot be found without the sharing key (same response as a non-existent form).
- Restricted access: random identifiers (40 bits), 10-character passwords (≈ 57 bits) or 24-character link tokens,
  stored only as hashes (`password_hash`, SHA-256); one-second pause per failure and 15-minute lock
  after 10 failures; login through a signed cookie (2 hours, invalidated by a password change); a single answer
  per access, checked under the same lock as the write.
- Administration: hashed password, attempts slowed down, token on each action, uploaded files checked before installation.
- A secret key is created automatically in `donnees/.secret` on first use; deleting it invalidates
  open forms, logs out the administration and **changes the sharing links** of unlisted forms.

## Troubleshooting

| symptom | likely cause |
|---------|--------------|
| "Form unavailable: incomplete installation" | `emplacement.php` does not point to the folder containing `lib.php` |
| "Your answer could not be recorded" | the `donnees/` folder is not writable (see [Installation](#installation), step 2) |
| "Form not found or incorrectly described" | error in the `.ini`: the details are shown on `admin.php` |
| "Page not found" | wrong address, or unlisted form opened without its sharing link |
| a sharing link answers "Page not found" | form renamed or deleted, or `.secret` file erased: copy the link again from `admin.php` |
| "The page has expired" | form left open for more than 24 h: submitting again is enough (what was entered is kept) |
| "PHP cannot write to … formulaires" | give write permissions to the `prive/formulaires/` folder (see [Installation](#installation), step 2) |
| wrong accents or everything in one column in Excel | open through *Data* → *From Text/CSV*, UTF-8 encoding, `;` delimiter |
| no notification email | `mail()` disabled by the host, or `expediteur` outside your domain |
| no confirmation email | same causes; or `'confirmation' => false`; or limit reached (same address less than 10 minutes ago, `confirmation_max_heure`): see the PHP error log |
| "OR (\|\|) in conditions is disabled" | set `'conditions_ou' => true` in `config.php`, or write the condition with `&` |
| a change made by FTP does not show | wait a few seconds (host's PHP cache) then reload |
| a personal link answers "Page not found" | link copied incorrectly (identifier or token cut off); in `lien` mode, link renewed since ("New link") |
| "This access is no longer valid" | access revoked: **Reactivate** in `admin.php` |
| "Too many attempts" | 10 wrong passwords: wait 15 minutes, or **Reactivate** the access in `admin.php` |
| password lost | **New password** on the access's row, then pass it on to the person |
| the editor refuses to save: "changed in the meantime" | the file changed since the editor was opened (FTP, another window): reload the page |
| the editor stays empty | JavaScript disabled in the browser |
| "… uses the catalogue …, not found" | the catalogue named by `catalogue =` does not exist in `prive/catalogues/`: create it (Catalogues section) or fix the name |
| "invalid logo …" | the `logo =` key contains a path or an unknown extension: give only the name of an image in `prive/images/` (or `non`) |
| the logo does not show | image missing from `prive/images/` (warning in `admin.php`), or `logo = non` in the form |
| a product does not appear on the order form | it is not ticked "available" in the catalogue |
| "the answers file … is already used by the form …" | the `.ini` (often a copy) contains `fichier = …` like another form: remove the line, or empty "Answers file name" in the editor |
| "Your administration session has expired" in the editor | logged in for more than 4 h: log in again in another tab, then save (changes remain in the editor) |
