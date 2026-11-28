# Ρυθμίσεις: what every setting does

Last update: 2026-11-28 12:15 (X01: `AUDIT_KEEP_DAYS`; R6m). Feature guide for `/settings`, and the source for the future administrator and end-user
manual (Z04). Each entry says what the setting really does in the code, its allowed values, what an empty value means,
who it affects, its limits and the settings it works with. **Keep it true:** whoever changes a setting's behaviour or
text updates this file in the same PR (CLAUDE.md).

Roles: **Διαχειριστής** (administrator), **Αναθέτων** (assigner), **Συγγραφέας** (writer), **Αναγνώστης** (reader).

## How the page works
- Only administrators open `/settings` (others are sent to the start page). Menu of sections on the left, one section on
  the right; **Αποθήκευση** saves only the section shown (`App\Livewire\SettingsPage`).
- Everything shown comes from one list, `App\Support\SettingsCatalog` (label, line under the field, ⓘ text, type,
  rules). Only those keys are ever saved, each checked first; a wrong value is shown in red at its field and **nothing**
  of that section is saved.
- Values live in the `configs` table (key → value). A switch is stored `1` / `0`; empty and `0` both mean off. Spaces at
  the ends of a value are removed.
- Changes take effect at once (the code reads the table every time; nothing is cached), unless an entry says otherwise.
- `?setting=<key>` in the address opens that setting's section (used by links and tests).

## Υπηρεσία/Οργανισμός

### Όνομα υπηρεσίας / σχολείου — `ipiresiasName`
- **Values:** text, required, up to 255 characters. Fresh installation: «Όνομα Σχολείου».
- **Does:** shown under the application's name in the top bar (also on the login page), in the head and foot of
  printouts, in the Excel file's head and its file name, on registration receipts, in assignment e-mails, and in the
  stamp on files, where the stamp design has the «υπηρεσία» field (the default design has it; the stamp itself
  follows *Ηλεκτρονική-έντυπη σφραγίδα*).
- **Who:** everyone sees it, from the next page, printout or e-mail on.

### Χρώμα της πάνω μπάρας στις οθόνες της εφαρμογής — `titleColor`
- **Values:** `#` and 3–8 hex digits (`#1f4e79`), or a colour name of 3–20 English letters (`teal`). Empty: the
  application's blue.
- **Does:** colours the top bar of every page of the new layout, for everyone. Not the login page.
- **Limits:** a name the browser does not know (e.g. `abcd`) passes the check but shows the default blue.

## Πρωτόκολλο → Αρίθμηση και έλεγχοι

### Ενεργό έτος πρωτοκόλλησης — `yearInUse`
- **Values:** empty or a 4-digit year. Fresh installation: the current year.
- **With a year:** every new protocol gets this year and the next number of this year (its highest number + 1; a year
  with no protocols starts at 1). In January write the new year.
- **Empty:** the year is the current calendar year, and the number is the last-created protocol's number + 1: the
  numbering **never** starts again from 1, also not in a new year.
- **With *Έλεγχοι-περιορισμοί καταχώρισης* on:** if the year here is not the current calendar year, **no new protocol
  is saved**, from the form or the Inbox, until it is corrected (or emptied).
- **Who chooses another year:** only an administrator, and only with *Έλεγχοι-περιορισμοί* off, can type another year
  and number in the form. For everyone else, and always for e-mails registered from the Inbox, the year is this one.
- **Also used by:** the default year of *Αναζήτηση* and *Εκτύπωση*; a protocol restored from the trash whose number is
  taken meanwhile (it gets this year, the next free number and today's date); the «Του ενεργού έτους» choice when
  folder rules are applied to existing protocols; the «Καταχώριση με αριθμό N» button on the list's gaps (only on gaps
  of this year).
- **Existing data:** changing it changes nothing already saved.

### ▢ Αρχή αρίθμησης και κενοί αριθμοί (frame of the next two)
The two work together: the gap check starts at the larger of *Αριθμός πρωτοκόλλου για ξεκίνημα* (current year only)
and the first number of the last *N* months, and runs to the year's last number.

### Αριθμός πρωτοκόλλου για ξεκίνημα — `firstProtocolNum`
- **Values:** empty or a whole number ≥ 0; empty or 0 = 1. Fresh installation: 0.
- **Use 1 — the very first number:** only when the database has **no protocol at all, of any year**. A new year with
  older years present starts at 1, not at this number.
- **Use 2 — the gap check:** in the current **calendar** year, numbers below it are never reported as missing (e.g.
  when this year's numbering continues from another book). If it is at or above the year's last number, that year is
  not checked at all.
- The list's gap notice says «έλεγχος από τον αριθμό X» when X > 1.

### Έλεγχος κενών αριθμών και ασυνεχειών για τους τελευταίους Ν μήνες — `gapCheckMonths`
- **Values:** empty or 1–24; empty = 6.
- **Does:** each year touched by the last *N* months is checked separately (a year with fewer than two protocols is
  skipped), from the first number dated inside the window (or *Αριθμός για ξεκίνημα*, if larger) to the year's highest
  number. Missing numbers appear on the *Λίστα* as a yellow «Κενά στην αρίθμηση» notice and as green «ΚΕΝΟ» rows.
- **Who:** administrators, assigners and writers; not readers.
- **Limits:** the green rows show only with the «Όλα» view, sorted by number; otherwise the notice asks for «Όλα».
  Recomputed on every refresh of the list.

## Πρωτόκολλο → Λίστες και αναζήτηση

### Αυτόματη ανανέωση της λίστας πρωτοκόλλων ανά Ν λεπτά — `minutesRefreshInterval`
- **Values:** required, 0–1440; decimals with a dot (`0.5` = every 30 seconds; `0,5` is refused). 0 = off.
  Fresh installation: 5.
- **Does:** the protocol list (rows and gap notice) reloads itself, for everyone. Nothing else on the page reloads.

### Αριθμός πρωτοκόλλων που εμφανίζονται ανά σελίδα — `showRowsInPage`
- **Values:** required, 5–1000. Fresh installation: 25.
- **Does:** rows per page of the protocol list only (not search, not the Inbox). A page past the end shows the last.

### Αριθμός αποτελεσμάτων αναζήτησης ανά σελίδα — `maxRowsInFindPage`
- **Values:** empty or 1–100000; empty = 30.
- **Does:** page size of *Σύνθετη αναζήτηση* and of the search box's results. Not a limit: every result is reachable,
  in pages. Which protocols a user may see is decided by *Περιορισμός λίστας*.

## Πρωτόκολλο → Καταχώριση

### Έλεγχοι-περιορισμοί καταχώρισης — `protocolValidate`
- **Values:** on / off. Fresh installation: on.
- **Always checked (on or off):** valid dates, field lengths, a protocol date, a year 1000–9999, a number ≥ 1.
- **On — every save, new or edited, from the form or the Inbox:**
  - *Θέμα* is required (unless *Επιτρέπεται πρωτόκολλο χωρίς θέμα* applies).
  - Incoming: if any of number / date / place / issuing authority is filled, date, place and authority are required,
    and so is the recipient (for an e-mail the place is not required).
  - Outgoing: date and «σε ποιον» go together, also when the summary is filled.
  - With an assignment for Διεκπεραίωση and any outgoing field filled, the done date is required.
  - A new protocol's year must be the current calendar year (see *Ενεργό έτος*).
  - Administrators cannot choose number or year, nor delete from the form, nor use the gap button; the date only
    with *Δικαίωμα τροποποίησης ημερομηνίας*.
- **Off:** none of the above. An administrator types number, year and date, deletes from the form, and registers a
  missing number from the list's «ΚΕΝΟ» rows.

### Επιτρέπεται πρωτόκολλο χωρίς θέμα — `allowEmptyProtocol`
- **Values:** on / off. Fresh installation: off.
- **Does:** only while *Έλεγχοι-περιορισμοί* are on (off: a subject is never required anyway). A protocol without a
  subject is accepted only if **nothing else** is filled (folder, related numbers, keywords, notes, incoming, outgoing,
  done date) and it has no assignment: a reserved number. Attachments are allowed.
- **Inbox:** never applies (a registered e-mail always has notes, so it needs a subject).
- **Related rule (independent of this setting):** a protocol without a subject can be completed by every writer and
  assigner who can see it.

### Σχετικοί αριθμοί και στα δύο πρωτόκολλα — `saveCycleSxetiko`
- **Values:** on / off. Fresh installation: off.
- **On save** (new or edited protocol): each number written in *Σχετικοί* as `N/YYYY` and **separated by commas**, that
  exists, gets this protocol's number **and the other numbers of this protocol's Σχετικοί**. Not written where the
  other protocol's field would pass 255 characters. It also writes into protocols the user cannot see or edit, and
  moves their «last change» time (which restarts their edit time limit).
- **For older protocols:** while it is saved on, the section shows «Εφαρμογή και στα υπάρχοντα πρωτόκολλα…»: it first
  says how many protocols would change, then adds only the **back-link** (each protocol named by A gets A's number;
  numbers separated by commas, spaces or `;`), as many as fit, keeps their «last change» time, sends no e-mail.
  Administrators only. Details: [RELATED-NUMBERS.md](RELATED-NUMBERS.md).

### ▢ Προτάσεις κατά την πληκτρολόγηση (frame of the next two; R6m, on the page since 2026-11-27)
While typing in *Τόπος έκδοσης*, *Αρχή έκδοσης*, *Παραλήπτης* and *Απευθύνεται σε* (protocol form, Inbox, *Σύνθετη
αναζήτηση*), each field offers the values written most often in the latest protocols the user may see (with
*Περιορισμός λίστας* on, only their own and assigned ones), in alphabetical order. The list is made when the page opens.

### Προτάσεις: από πόσα πρόσφατα πρωτόκολλα — `rowsToSearchGiaSimplirosi`
- **Values:** empty or 1–50000; empty = 1000.
- **Does:** the suggestions come from the latest *N* protocols (by entry). More gives more (and older) values but a
  slower page.

### Προτάσεις: πόσες τιμές ανά πεδίο — `rowsToShowGiaSimplirosi`
- **Values:** empty or 1–1000; empty = 50.
- **Does:** each field offers its *N* most used values. (Before R6m the code raised any value below 50 to 50, so the
  old stored 10 showed 50: hence the default 50.)

## Πρωτόκολλο → Δικαιώματα

### Δικαιώματα τροποποίησης πρωτοκόλλων — `allowWriterUpdateProtocol`
- **Values:** 0 Κανένας · 1 Μόνο όποιος το καταχώρισε · 2 Όλοι οι Συγγραφείς και Αναθέτοντες (required). Fresh
  installation: 1.
- **Does:** whether writers and assigners may change an **existing** protocol: 0 none; 1 only the protocols they
  registered themselves (assigners too); 2 all they can see. Administrators always may; readers never.
- **Exceptions (whatever the value):** a protocol without a subject is open to every writer and assigner; a user
  assigned for Διεκπεραίωση (not Ενημέρωση) may fill *Εξερχόμενο* and the done date until the done date is set
  (an assigned reader only the done date).
- Checked when saving, so a change applies at once to every protocol. With *Περιορισμός λίστας*, «all» means all
  they can see.

### Χρονικό όριο που επιτρέπεται η τροποποίηση πρωτοκόλλου (λεπτά) — `allowWriterUpdateProtocolTimeInMinutes`
- **Values:** empty or 0–525600 (a year); empty or 0 = no limit. Fresh installation: 10.
- **Does:** for writers and assigners who may change the protocol at all; not for administrators. Counted from the
  protocol's creation **and** from its last change:
  - both older than *N* minutes → everything locked;
  - created earlier but changed within *N* minutes → with both incoming and outgoing data, only *Εξερχόμενο* and the
    done date stay open; with outgoing data only, *Εισερχόμενο* is locked; otherwise all stays open.
- The form shows the time left. A protocol without a subject stays open; an assigned handler keeps *Εξερχόμενο* and
  the done date until the done date is set.
- Any change to the protocol restarts the count, also the automatic one of *Σχετικοί αριθμοί*.

### Δικαίωμα τροποποίησης ημερομηνίας πρωτοκόλλων — `allowWriterChangeDate`
- **Values:** on / off. Fresh installation: off.
- **On:** writers, assigners and administrators set the protocol date (if they may save the protocol at all).
  Readers never.
- **Off:** a new protocol gets today's date; an existing one keeps its own. «Αντιγραφή ως νέο» keeps the source's date
  only with the right.
- Administrators also set it whenever *Έλεγχοι-περιορισμοί* are off. Registering from the Inbox always uses today.

### Περιορισμός λίστας — `limitProtocolAccessList`
- **Values:** on / off. Fresh installation: off.
- **On:** writers and readers see only protocols they registered or that are assigned to them (Διεκπεραίωση or
  Ενημέρωση): in the list, search, field suggestions, «similar protocols» hints, go-to-number, and by direct address
  or file link. The *Εκτύπωση* menu is hidden and its addresses refused. In the Inbox, the files of an e-mail already
  registered in a protocol they cannot see are hidden.
- Administrators and assigners see everything. Applies at once.

### Ελεύθερη επιλογή χρόνου διατήρησης — `allowUserChangeKeepSelect`
- **Values:** on / off. Fresh installation: on.
- **Does:** retention (*Διατήρηση*) is kept **per file**, set when a file or ΑΔΑ is added (form or Inbox), and gives
  the file's expiry date (protocol date + years) used by *Εκκαθάριση αρχείου*.
  - On: the folder (Φ) suggests it; whoever adds the file may change it.
  - Off: the field is locked and the server always uses the folder's retention, whatever is sent.
- Each folder's retention is set in *Φάκελοι και διατήρηση*. Files already added keep theirs.

## Αρχεία → Συνημμένα

### Ηλεκτρονική-έντυπη σφραγίδα στα συνημμένα (αρχική επιλογή) — `putStamp`
- **Values:** on / off. Fresh installation: off.
- **Does:** the default of «Με σφραγίδα» for users who never chose; each user's own choice (in *Συνημμένα* or the
  preview) always wins. So a change applies at once to every file, for those users.
- The stamp is a drawing (not a digital signature) added to a **copy** when a file is viewed, downloaded or printed;
  the stored file never changes. Formats: PDF, docx, odt, JPG, PNG (not encrypted PDFs, not pictures over 30 MP).
  Only protocol attachments. Files that already carry an old stamp inside are not stamped again.
- Design: *Σφραγίδα* page (administrators). Place and see-through per file: in the preview.

### Διεύθυνση Διαύγειας — `diavgeiaUrl`
- **Values:** empty or an `http(s)` address, up to 255 characters. Fresh installation:
  `http://static.diavgeia.gov.gr/doc/`.
- **Does:** an ΑΔΑ becomes a link to this address + the ΑΔΑ, in the *Λίστα* and the protocol form (search results and
  the trash show it as plain text). Empty: no link. End it with `/`.

### Τα συνημμένα αρχεία EML ή HTML να εμφανίζονται πρώτα στη λίστα πρωτοκόλλων — `emailFileFirst`
- **Values:** on / off. Fresh installation: off.
- **Does:** only in the *Λίστα*: the e-mail's own file (recognised by its name `email_YYYYMMDD_HHMMSS.eml|html`) is
  shown first (on) or after the other files (off; first if it is the only one). Either way it is shown as
  «dd/mm/yyyy · hh:mm:ss». Everyone.

### Μορφή αποθήκευσης των αρχείων email — `saveEmailAs`
- **Values:** σελίδα html (stored empty) · αρχείο e-mail eml (`eml`). Fresh installation: eml.
- **Does:** when an e-mail is registered from the Inbox, the e-mail itself is kept with the protocol as
  `email_<date>_<time>.eml` (the message exactly as received) or `.html` (a page with its text). Never stamped.
  Applies to new registrations only.

## Αρχεία → Κάδος

### Αυτόματη εκκαθάριση του κάδου μετά από Ν ημέρες — `trashRetentionDays`
- **Values:** empty or 0–3650; empty = 30; 0 = never automatically.
- **Does:** deleted attachments and deleted protocols wait in *Διαχείριση → Κάδος* (restorable) and are deleted for
  good *N* days after deletion. Checked once a day, at the first use of the application that day (no server
  scheduler needed). Items in the trash before the upgrade count from the upgrade day. A change applies to
  everything already in the trash.

## E-mail → Λογαριασμός εισερχομένων

### Λογαριασμός εισερχομένων — `defaultImapEmail`
- **Values:** empty or one of the accounts defined in `.env` (`IMAP_HOST`, `IMAP_USERNAME`, `IMAP_PASSWORD` …; a test
  account only with `TEST_IMAP_HOST`). Fresh installation: `default`.
- **Does:** the mail account (folder INBOX) the *Εισερχόμενα* open and their count badge in the menu counts.
  **Empty: no Inbox for anyone, administrators included.**

### Χρήστες με δικαίωμα καταχώρισης email — `allowedEmailUsers`
- **Values:** empty or usernames separated by spaces, commas or `;`; whole names, case does not matter; up to 2000
  characters.
- **Does:** empty = every administrator, assigner and writer has the Inbox. With names, only they, **and always every
  administrator** (owner, 2026-11-27: an administrator never needs to be listed). Readers never, listed or not.
- Needs *Λογαριασμός εισερχομένων*.

## E-mail → Εισερχόμενα

### Να εμφανίζονται email των τελευταίων N ημερών — `daysToCheckEmailBack`
- **Values:** empty or 1–3650; empty = only today's. Fresh installation: 7.
- **Does:** the Inbox and its count badge show the e-mails received from the start of the day *N* days ago. Older ones
  stay in the mailbox, only hidden. Registered e-mails and those marked «Αναγνωσμένο» leave the Inbox anyway.

### Αριθμός emails που να εμφανίζονται ανά σελίδα — `emailNumFetch`
- **Values:** empty or 1–200; empty = 1. Fresh installation: 5. Fewer load faster (suggested 5–10).

### Ταξινόμηση email στα Εισερχόμενα — `emailFetchOrderDesc`
- **Values:** Νεότερα πρώτα (1) · Παλαιότερα πρώτα (0); required. Fresh installation: 0.
- **Does:** only the **starting** order. Each user switches their own with ↑/↓ in the Inbox and keeps it; changing the
  setting no longer affects a user who has switched.

### Διακόσμηση των email (αρχική επιλογή) στη σελίδα των Εισερχομένων — `inboxEnvelope`
- **Values:** Αεροπορικός · Χάρτινος με γραμματόσημο · Λιτός · Καμία. Unset = Αεροπορικός.
- **Does:** the default look; each user's choice in their menu (on the Inbox page) wins, and can be cleared back to
  this default.

## E-mail → Ειδοποιήσεις

### Αποστολή email σε όποιον ανατίθεται πρωτόκολλο — `sendEmailOnDiekperaiosiChange`
- **Values:** on / off.
- **Does:** on every save, each user **newly** added to an assignment (Διεκπεραίωση or Ενημέρωση, also by a folder's
  automatic assignments and Inbox registrations) gets an e-mail; also the person saving, if they assign themselves.
  Each send is noted in *Παρατηρήσεις*; a user without a valid address gives a warning. Turning it on sends nothing for
  existing assignments. The form's «Ανάθεση» button always sends, whatever this setting.

### Αποστολή απόδειξης για κάθε e-mail που καταχωρίζεται — `alwaysSendReceitForEmails`
- **Values:** on / off.
- **Does:** in the Inbox, «Καταχώριση» is hidden and «Καταχώριση και απόδειξη» remains; **the server enforces it too**
  (R6m): every registration sends a receipt. The address is filled in from the e-mail's Reply-To, else its sender;
  if neither exists the user must type one. «Αναγνωσμένο» (not registering) sends nothing. The protocol form's own
  receipt is not affected.

## Not on the page

### Values in `.env` (R6m: were settings)
| `.env` | Was | Default | Does |
|---|---|---|---|
| `MYSQLDUMP_PATH` | «Διαδρομή του mysqldump» (`mysqldumpPath`) | empty = `mysqldump` / `mariadb-dump` found on the PATH (the Docker image has it) | the program *Διαχείριση → Backup* runs; only those two programs are ever run |
| `PRINT_MAX_ROWS` | «Μέγιστες γραμμές εκτύπωσης και Excel» (`maxRowsInXlsExport`) | 5000 | most protocols in one printout or Excel file; the printout says when more were left out |
| `AUDIT_KEEP_DAYS` | — (new, X01) | empty = keep everything | days the audit log keeps who-did-what; older entries removed once a day ([AUDIT-LOG.md](AUDIT-LOG.md)) |
| `APP_TIMEZONE` | the hidden key `timeZone` (never on the new page) | Europe/Athens | the application's time zone: every date it shows and fills in, also the Inbox's and the e-mail's HTML copy |

Their `configs` rows (and `timeZone`'s) now read «Δεν χρησιμοποιείται πια: ορίζεται στο .env ως … (δείτε το
.env.example).», with the old value at the end (migration `2026_11_27_000002`). Template: `.env.example`.

### `configs` keys still read, not on the page
| Key | Read by | Effect | Status |
|---|---|---|---|
| `protocolArrowStep` | `ProtocolController` `/goto/…/bb|ff` | step of the «jump N» addresses; no button uses them | hidden by the owner's decision (R6d) |
| `wideListProtocol` | old layout (login pages only) | full-width old layout | kept until the old layout goes (Z01) |
| `stampTemplate` | `StampTemplate` | the stamp's design | edited on the *Σφραγίδα* page, not here |
| `trashRetentionStart`, `trashLastPurge` | trash | internal dates | not settings |

### Dead keys (nothing reads them)
`safeNewProtocolNum`, `updatesAutoCheck` (hidden, R6d), `needsUpdate`, `alwaysShowFakelosInViewEmails` (R5s: every
e-mail needs a folder), `mysqldumpPath`, `maxRowsInXlsExport`, `timeZone` (R6m, above). Removed rows: `searchField1–3`,
`showUserInfo`, `allowListValuesMatchingInput` (R6c), `gapCheckFromNumber` (moved into `firstProtocolNum`).

## Changing a setting (for developers and AI assistants)
1. Text, type, rules and section: `app/Support/SettingsCatalog.php` (`definitions()` and `SECTIONS`); the page draws
   itself from it. Keep the owner's wording; ask before rewriting it.
2. Behaviour: where the key is read (`grep -rn "'<key>'" app resources`). Change the text and this file with it.
3. Tests: `tests/Feature/SettingsPageTest.php` (sections, validation, every setting has its line and ⓘ) plus the
   feature's own tests; `e2e/tests/settings.spec.js` for the page.
4. Removing a setting: take it out of the catalog, decide what reads it (`.env`, fixed value, nothing), leave or
   annotate its `configs` row with a migration (as `2026_11_27_000002`), and list it above.
