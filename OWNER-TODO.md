# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-23 14:28 (R2l list; F09 CI; R5o inbox form and colours; R5n inbox from cache; R5m inbox loading; L03 test mailbox; after Batch 9; R2e, R6d, R2f, R2g, R2h, R5e; D07 your folder list; R1j; D08 folder codes). Times are Greek time.

## ▶ Your next message
Batch 9 is done ([summary](docs/reports/BATCH-9.md)): users, folders, settings, backups and printing are rebuilt.

**No hurry.** Below is everything to try, batch by batch. Take the time you need. Batch 10 waits until you say so,
and whatever you report I fix first. When you are ready, start a **new conversation** and send:

```
run batch 10
```

Batch 10 (A01–A06, X01–X02, Z01–Z03) is the last one: integrity checks, a log of who changed what, removing the old
code, and a final security review. After it, I write the deployment guide.

## Try it, batch by batch (optional)
Before you start: *Update: get latest version from GitHub*, then *Demo: start*. Open http://localhost:8080 and log in
as `admin` (password `password`) unless a step says otherwise.

- Try them in any order, a few at a time.
- Report each by its number: `12 ok`, or `14: nothing happens on Enter`.
- I tick it off here and fix anything wrong.
- Screenshots are welcome **from the demo (8080) only**, never from your real copy (8081).

### Batch 3: the new layout
- [ ] **1.** Your name (top right) → *Περί…*: the page has the new layout, with the menu at the top.
- [ ] **2.** Open http://localhost:8080/dev/ui: the date field, buttons and dialogs that every new page uses.

### Batch 4: the inbox's speed, Greek names
- [ ] **3.** Open *Εισερχόμενα*, then open it again: the second time is much faster.
- [ ] **4.** Senders' names in Greek show without `??`. E-mails with emoji or a very long subject register.

*(Batch 5 has nothing to try: you sent its numbers on 2026-11-16.)*

### Batch 6: the protocol form, the list, the trash
- [ ] **5.** *Νέο*: type a number that already exists, then a free one: it says *Ελεύθερος*. Press TAB to reach the
  date and type `9`: it becomes the 9th of this month.
- [ ] **6.** Drag a PDF onto the window, leave *Θέμα* empty and save: the subject is taken from the file's name. (On
  Fedora use *επιλέξτε* instead of dragging: see question 3.)
- [ ] **7.** *Λίστα*: try the filter pills, click a column title (it sorts), switch on *Αναλυτικά* and reload the page:
  your choice stays.
- [ ] **8.** Delete that PDF from the protocol, then *Διαχείριση → Κάδος* → *Επαναφορά*: it is back on the protocol.

### Batches 6.5 and 6.6: tags, folders, missing numbers
- [ ] **9.** *Σχετικοί αριθμοί*: type `1` and Enter (green ✔: it exists), then `999999,` (orange ⚠: not found). Click a
  tag's text to change it.
- [ ] **10.** *Λέξεις κλειδιά*: type part of a word without accents; choose with ↓ and Enter.
- [ ] **11.** *Φάκελος*: click it and type a word of a description (e.g. `εκδρομ`).
- [ ] **12.** *Λίστα*: a *ΚΕΝΟ* row stands for a missing number (the demo has some).

### Batch 7: search
- [ ] **13.** Press `/` on any page and type a word of a subject without accents (e.g. `εκδρομη`): matching protocols
  appear. ↓ and Enter open one; Enter alone opens the results page.
- [ ] **14.** Type `φακ` in the box: it offers `φάκελος:`. Try `φάκελος:κανένας`, then `είναι:εκκρεμές`.
- [ ] **15.** On the results page, *Σύνθετη αναζήτηση* opens the full search screen with the same search.
- [ ] **16.** *Αναζήτηση* → *Έλεγχος εκκρεμοτήτων* for a past month: it lists protocols without a folder or with
  ΕΚΚΡΕΜΟΤΗΤΑ in the subject; with none, it says so in green. Worth trying on your real copy (8081) too.

### Batch 7.5: folder codes, the top bar, your own layout
- [ ] **17.** *Νέο* → *Φάκελος*: type `12` (Φ.12 comes first), then `φ11` (only Φ.11, never Φ.1.1).
- [ ] **18.** The top bar has four items with icons, and the organisation's name under your name. *Διαχείριση* holds
  *Εκτύπωση* and *Διατήρηση*. The second button in the search box (sliders) opens *Σύνθετη αναζήτηση*.
- [ ] **19.** Your name → *Πλάτος σελίδας* → *Στενό, στο κέντρο*, then back to *Όλη η οθόνη*.
- [ ] **20.** *Νέο*: drag a section by ⠿ (left of its title) into the other column; ▴ collapses it. Try *Μία στήλη*,
  then *Επαναφορά διάταξης*. Log in on another computer: your layout is there too.

### Batch 8: the inbox
Open *Εισερχόμενα*.
- [ ] **21.** The list on the left: click the last e-mail and the page goes to it. Close *Κείμενο* in one e-mail: it
  closes in all of them, and stays closed after a reload.
- [ ] **22.** An e-mail with a PDF: press *Καταχώριση* without a *Φάκελος*, and the reason appears under *Φάκελος*.
  Choose one and press again: *«Καταχωρίστηκε με αριθμό …»* appears in the e-mail's place, with a link.
- [ ] **23.** *Λέξεις κλειδιά*: click a suggestion under the field (*+ …*): it becomes a tag.
- [ ] **24.** Give an e-mail the subject of one you just registered and press *Καταχώριση*: it first asks *«Να
  καταχωριστεί ούτως ή άλλως;»*.
- [ ] **25.** The e-mail *«Αίτηση μετακίνησης (Outlook winmail)»* shows its text and two files instead of
  `winmail.dat`.

### Batch 9: users, folders, settings, printing
- [ ] **26.** *Διαχείριση → Χρήστες*: untick *Ενεργός* for `reader` and save. In another browser, `reader` cannot log
  in. Tick it again.
- [ ] **27.** *Διαχείριση → Φάκελοι και διατήρηση → Εισαγωγή λίστας*: copy a few rows of your folder spreadsheet
  (code, description, Διατήρηση, remarks), paste them and press *Έλεγχος*. Nothing is saved until *Εισαγωγή*.
- [ ] **28.** Open a folder (*Επεξεργασία*), tick a user under *Αυτόματη ανάθεση → για Διεκπεραίωση* and save. Log in
  as `writer` and make a protocol in that folder: under *Φάκελος* it says who will get it.
- [ ] **29.** *Ρυθμίσεις*: read the explanations, then type `abc` in *Γραμμές ανά σελίδα* and save: it is refused at
  the field.
- [ ] **30.** *Εκτύπωση πρωτοκόλλου*: choose one month (dates) and press *Εκτύπωση*. Compare with an old printout.

### Batch 9.5: your notes after Batch 9
- [ ] **31.** *Λίστα*: the pager is above the list as well as below it. Try *Επόμενη ›* and *‹ Προηγούμενη*, and choose
  a page from *Σελίδα [ ▾ ] από …*: both pagers go to that page. Reload: you stay on it. (On a phone: the buttons and
  the page list only.)
- [ ] **32.** *Ρυθμίσεις*: move the mouse over (or tap) an **ⓘ** next to a setting: its fuller explanation appears.
  *Αριθμός πρωτοκόλλου για ξεκίνημα* and *Έλεγχος κενών αριθμών* are now in one frame that says how they combine.
  *Έλεγχος για νέα έκδοση*, *Βήμα των κουμπιών Προηγούμενο / Επόμενο* and *Ασφαλής νέος αριθμός* are gone (they did
  nothing).
- [ ] **33.** Untick *Ελεύθερη επιλογή χρόνου διατήρησης* and save. *Νέο*: choose a folder; *Διατήρηση* takes the
  folder's and cannot be changed. Tick it again: it can.
- [ ] **34.** *Ρυθμίσεις → Ο Συγγραφέας αλλάζει την ημερομηνία πρωτοκόλλου*: on. *Νέο*: type a date some days
  before today. Under the date: ⚠ *«Η ημερομηνία δεν ταιριάζει με τη σειρά των αριθμών …»*. Save anyway: it is saved.
  In *Λίστα* its row is coloured, with ⚠ next to the date; the mouse over ⚠ explains why. Correct the date: the warning
  goes. A date after today is flagged the same way.
- [ ] **35.** The same pager as *Λίστα* (above and below, ‹ ›, *Σελίδα [ ▾ ]*) on the search results (the box and
  *Σύνθετη αναζήτηση*), *Κάδος*, *Χρήστες* and *Εκκαθάριση*, wherever there is more than one page. Choosing a page
  from the lower pager brings the upper one into view.
- [ ] **36.** *Λίστα*: at the end of each row, **⋮** instead of the *Αντιγραφή* / *Απόδειξη* buttons. Click it: the menu
  has *Αντιγραφή ως νέο* and *Απόδειξη* (opens in a new tab). The other columns have more room.
- [ ] **37.** *Εισερχόμενα*: in *Ημ/νία εισερχ.* type `9` and TAB: 09/this month. ◀ ▶, *Σήμερα* and 📅 work as on
  *Νέο*. Under *Λέξεις κλειδιά*, the suggestions now include words of the attachments' names (e.g. *κατάσταση* from
  *κατάσταση.csv*), and a name in capitals gets its accents when earlier subjects have the word.
- [ ] **39.** Open a protocol: one **Αποθήκευση** stays at the bottom right of the screen while you scroll; it saves
  as before. *Νέο* and *Λίστα* are no longer under the number (they are in the top bar). *Αντιγραφή ως νέο* and
  *Απόδειξη* are at the far right of that row, with icons. In the top bar, click the word *Λίστα*: you are back in the
  list; its **▾** opens the other lists, as before. As a *reader*: no *Αντιγραφή ως νέο*, in the protocol or in the
  list's ⋮ menu.
- [ ] **40.** Click your name at the top right: under *Αρχική σελίδα* choose *Όλα τα πρωτόκολλα*, *προς Διεκπεραίωση*
  or *προς Ενημέρωση*. The application's name and *Λίστα* now lead there; log out and in: you start there too.
  Another user keeps their own. Make the window narrower (down to a tablet's width): *Νέο*, *Λίστα*, *Εισερχόμενα*,
  *Διαχείριση* and the search box stay visible; your name moves into the tooltip of the person icon.
- [ ] **41.** *Λίστα*: the rows alternate white and light blue; the columns keep the same widths from page to page.
  A long subject shows two lines ending in …; a protocol with more than two files shows two and *+N ακόμη*. Click
  **▾** at the end of a row: that row opens in full (all the files, the details of *Αναλυτικά*); again: it closes.
  *Αναλυτικά* opens every row.
- [ ] **42.** *Σύνθετη αναζήτηση*: in *Αρ. εισερχομένου* type a number that appears inside longer ones too (e.g.
  `120`). Without the switch: also 234120, 1205 … Turn on **Ολόκληρες λέξεις** and search again: only 120 (and e.g.
  120/3, Φ.2/120/Δ1). Same without the switch: `=120` in the field, or `εισ:=120` in the search box at the top. It works
  for words too (`θέμα:=άδεια` finds «Άδεια», not «αδειούχος»). The help under the results says it.
- [ ] **43.** Open a protocol and change something (e.g. the subject): a **red dot** appears on *Αποθήκευση*; undo the
  change: it goes. With a change, click *Νέο*, *Λίστα* or *Επόμενο ▶*: a window lists what changed (before / now) and
  asks: *Ακύρωση* (stay), *Χωρίς αποθήκευση* (go, nothing saved) or *Αποθήκευση* (save, then go). Closing or
  reloading the tab asks too (the browser's own question, without the list).
- [ ] **44.** *Λίστα*, column *Ανάθεση*: each assigned user is a pill: blue with ✎ for *Διεκπεραίωση*, white with 👁
  for *Ενημέρωση* (the mouse over it says which). The *Λίστα* menu's *προς Διεκπεραίωση* / *προς Ενημέρωση* have the
  same icons.
- [ ] **45.** With e-mails in the inbox, the top bar shows a red number next to *Εισερχόμενα* (all of them, read or
  not), on every page. Two users at once (two browsers): one registers or marks e-mails as read until the inbox is
  empty; the other's number goes down and disappears within a minute (at once when they come back to the tab).
- [ ] **46.** *Εισερχόμενα* with more e-mails than *Email ανά σελίδα*: the same pager as *Λίστα* above the e-mails
  (and below the last one), with *Σελίδα [ ▾ ] από …*; the list on the left says *Σελίδα 1 από 4 · 1–5 από 16* with
  ‹ › to the other pages.
- [ ] **47.** The top bar has *Νέο*, *Λίστα*, *Εισερχόμενα*; *Διαχείριση* is now in the menu under your name (top
  right): on the left *Διαχείριση* (*Εκτύπωση*, *Φάκελοι*, and for administrators *Διαχείριση*: Χρήστες, Ρυθμίσεις,
  Backup, Εκκαθάριση, Κάδος), on the right your *Προτιμήσεις* (page width, home page); *Περί…* and *Έξοδος* below.
- [ ] **48.** *Λίστα*: every row has the same height (two lines). *Από / Προς* shows the sender only: the mouse over
  it shows the whole name, *Αρ. εισερχομένου* and the date. *Ανάθεση* shows the first person and *+N*, *Συνημμένα* the
  first file and *+N*. ▾ (or *+N* on the files) opens the row with everything.
- [ ] **49.** *Εισερχόμενα*: click e-mails in the list on the left, several times, also ones with HTML text: the page
  scrolls to each and stays there (no jump back), and the folder's list does not open by itself.
- [ ] **50.** *Εισερχόμενα*: an e-mail sent to many (e.g. all the schools) shows three addresses in *Προς* and *και
  άλλοι N ▾*; click it for all of them, *λιγότεροι ▴* to fold them again.
- [ ] **51.** *Εισερχόμενα*: *Συνημμένα* is right above *Καταχώριση*; the e-mail below (Από, Προς, Θέμα, text, HTML)
  has a light blue background and a blue edge, set apart from the form.
- [ ] **52.** *Εισερχόμενα*: press *Καταχώριση* (or the yellow *Ναι, καταχώριση*): at once a blue *«Καταχωρίζεται…»*
  appears above the buttons and the button turns; meanwhile the buttons of every e-mail are greyed out; when it is
  done the e-mail says *«Καταχωρίστηκε με αριθμό …»*.
- [ ] **53.** After *Καταχώριση* or *Αναγνωσμένο*, the e-mail folds to its result line. *Εμφάνιση email ▾* opens it
  again (*Απόκρυψη ▴* folds it); *Επόμενο email ↓* goes to the next e-mail still waiting on the page.

### Your test mailbox (L03, 2026-11-23)
- [ ] **54.** Real copy with a test mailbox: run `./dev.sh real up`, fill in `local-data/test-mailbox.env` (the
  mailbox made for testing, never the production one), run `./dev.sh real up` again, then on http://localhost:8081
  *Ρυθμίσεις → E-mail → Λογαριασμός εισερχομένων* → *Δοκιμαστικός λογαριασμός (…)* → *Αποθήκευση*. *Εισερχόμενα*
  shows its e-mails. Optional: fill in `TEST_SMTP_HOST` / `TEST_SMTP_PORT` too; a receipt or assignment notice
  then arrives in the test mailbox with *«[ΔΟΚΙΜΗ] προς: …»* (never at the real recipient). Steps: [docs/LOCAL-REAL-DATA.md](docs/LOCAL-REAL-DATA.md#a-temporary-test-mailbox-optional).

### The inbox on a slow mail server (R5m, 2026-11-23)
- [ ] **55.** Open *Εισερχόμενα*: the page appears at once with *«Σύνδεση με τον διακομιστή email… Παρακαλώ
  περιμένετε…»* and a seconds counter. If the server gives up, the same banner turns yellow with a plain message and
  *Δοκιμή ξανά* (no dark popup). Next to *Ανανέωση*: *Τελευταία ανανέωση: ΩΡΑ*. Best seen on your real copy (8081) early
  in the morning (after `git pull`, the copy's nginx needs `./dev.sh real down && ./dev.sh real up`).
- [ ] **56.** Two users, the same e-mail: open *Εισερχόμενα* as two users (two browsers), press *Καταχώριση* on the
  same e-mail in both: the second says it was already registered, by whom and with which number; no second protocol.

- [ ] **57.** Open *Εισερχόμενα* once, then again: the second time the e-mails are there at once, with *«Μέχρι τότε
  βλέπετε τα email της τελευταίας ανανέωσης (ΩΡΑ)»* under *Παρακαλώ περιμένετε…*; then the fresh list replaces them. If
  the server fails, the e-mails stay, marked as possibly not current.

- [ ] **58.** *Εισερχόμενα*: on the third e-mail of a page press *Κείμενο* (and *Εμφάνιση HTML*): the e-mail stays where
  it was. The ↗ next to *Εμφάνιση HTML* opens it in a new tab.
- [ ] **59.** The form: green *Εισερχόμενο*, blue *Εξερχόμενο* (folded; open it for an outgoing document), the e-mail
  below in light purple, each header lavender. The list on the left: *«N από M»* on each e-mail; after *Καταχώριση*,
  *✓ number* under its time. Between two e-mails: a thin line with *«↓ N από M ↓»*.

- [ ] **60.** *Λίστα*: the header has *Νέο* and *Ανανέωση* only; search from the box at the top (its sliders button
  opens *Σύνθετη αναζήτηση*).

### Your folder list (D07, 2026-11-19)
- [ ] **38.** On your **real copy**: *Update: get latest version from GitHub*, *Real data: start*, then in the VS Code
  terminal:
  ```
  ./dev.sh real artisan folders:glossary
  ```
  It only **shows** what would change: how many folders get the full description, which are new, which of yours are
  not in the list (kept as they are, e.g. Φ.37–Φ.42), and how many protocols and files move from Φ.43 to Φ.46.
  Nothing is changed yet. If it looks right:
  ```
  ./dev.sh real artisan folders:glossary --apply
  ```
  Then *Φάκελοι και διατήρηση*: search a word (e.g. *αναρρωτικές* → Φ.12.1); Φ.43 is gone and its protocols are under
  Φ.46 with their files. Send me the two lines starting *Folders:* and *Merge*, plus any line about *Sub-folders of
  Φ.43* (counts and codes only).

## Questions for you
1. **`??` in `ΑΔΕΙΑ ΚΩΝΣΤΑΝΤΙΝΙΔΟΥ`:** the new inbox shows this e-mail correctly (sender, subject, text, three
   attachments), and it registers. If you still see `??` somewhere, tell me where: which screen, which field, and on
   which copy (8081, the old server, an e-mail program).
2. **Inactive users can no longer log in (new, R6a):** before the move to the new version, check on *Χρήστες* that
   everyone who still works with the protocol is *Ενεργός*. Is there anyone marked inactive who still logs in today?
3. **Firefox on Fedora crashes when a file is dropped:** a Firefox/GNOME bug, not the page (see
   [Batch 6.6](docs/reports/BATCH-6.6.md)). On Fedora use *επιλέξτε* in the attachments box; drop works on Windows.
4. **Writers and the protocol date:** setting *Ρυθμίσεις → Δικαιώματα → Ο Συγγραφέας αλλάζει την ημερομηνία
   πρωτοκόλλου* (off). Turn it on if writers should set the date (e.g. a document received yesterday).
5. **Sub-folders of Φ.43 (D07):** if your list has Φ.43.1, Φ.43.2 …, where do they go: into Φ.46 itself, to new
   Φ.46.x folders, or stay as they are? Try-it 38's preview names them, if any.

## Final deployment (when all tasks are completed and verified)
1. I'll write a step-by-step guide for moving the server from XAMPP to Docker Desktop on the same Windows 11 PC:
   - back up the database and the files;
   - check the attachment file names on the server (`php tools\check-attachment-names.php …`, F08);
   - load the database: `migrate:adopt-legacy`, then `migrate`. This also converts to utf8mb4, long subjects, the
     assignments table, real dates and the trash columns;
   - measure the attachments (`php artisan attachments:fill-metadata`, D03);
   - check the user list for unknown administrators, and that everyone who works with the protocol is *Ενεργός*
     (inactive users cannot log in, R6a);
   - press *Διαχείριση → Backup → Νέο αντίγραφο* once and check the file opens;
   - choose how many days the trash keeps deleted attachments (30 if left empty; the old trash gets these days from
     the deployment day);
   - set *Αρ.Πρωτοκόλλου για ξεκίνημα* to the first number to check for missing numbers (R2c);
   - apply the folder glossary and the Φ.43 → Φ.46 merge: `php artisan folders:glossary --apply` (D07).

   Two security gaps of the **current** server stay open until then, by your decision (2026-11-16):
   - anyone who can reach it can create an administrator account (S00);
   - anyone can download inbox e-mails left in `public/tmp` (R5a).

   The new version closes both by itself. The Apache rules in [docs/BLOCK-REGISTRATION.md](docs/BLOCK-REGISTRATION.md)
   and [docs/BLOCK-PUBLIC-EMAIL-COPIES.md](docs/BLOCK-PUBLIC-EMAIL-COPIES.md) are only needed if you change your mind
   before then. Until then, export the database with **phpMyAdmin** (the Backup button on the server creates empty
   files).

## Whenever it happens
2. **Date reset on save (B03d, on hold):** when the date or number changes by itself again, write down the screen, your
   role and what you clicked, and send me that (no data).

## Already decided (no action)
- *Εισερχόμενα*: *Αριθ. εισερχ.* starts with the e-mail's time (e.g. 14:01:36), on purpose: it stands in when the
  attachment has no protocol number of the sender; when it has one, type that number over it (2026-11-20).
- Folder codes (2026-11-20): the dot after Φ is only for readability. `Φ12`, `Φ.12` and `φ12` are the same folder in
  search, in the folder picker and in the Φ.43 → Φ.46 merge; your protocols keep their code as written ([D08](docs/reports/D08.md)).
- Your folder list (2026-11-19) is the folders glossary: [docs/FOLDERS.md](docs/FOLDERS.md), from
  `database/data/folders.json`, public in the repository. Heading and text stay together in one description; Φ.13 is
  kept with «(Καταργήθηκε)»; Φ.43 is merged into Φ.46 (protocols and files move), now and in the move to the new
  version; Φ.37–Φ.42 stay as they are. Four scan typos corrected (see [D07](docs/reports/D07.md)).
- The "e-mails of the `ΑΔΕΙΑ` sender not registered" question: answered by the new inbox (Batch 8): without a folder it
  now says so under *Φάκελος*; all your e-mails register (2026-11-18).
- Your real copy after Batch 7 (2026-11-18): 3653 protocols, 24 users, 7630 attachments, 7148 files found (482 missing:
  the known gap between your backup and your file copy, L01). Thanks: nothing to do.
- Your screen choices (page width, the protocol page's sections) are saved per user, on every computer (2026-11-18).
- Real data after Batch 5, on your Fedora copy: 7183 attachments measured, 646 files not found (the known gap between
  your backup and file copy, L01), 0 dates that were not real dates (2026-11-16). Thanks: nothing to do.
- No changes on the production server until the move to the new version, also not the two Apache security rules (2026-11-16).
- Your e-mails in `local-data/emails/` (now 12, incl. the sick-leave and emoji ones of 2026-11-16): I read them only on this computer; nothing from them is published. Tests copy only their structure.
- Production will run the Docker stack after the rebuild (replaces XAMPP); on the same Windows 11 PC (Docker Desktop); you migrate the old database and files (2026-11-16).
- No deployment to production until all tasks are completed and verified (2026-11-16).
- L02 done: real data loads on the Mac too (2026-11-16). The Mac is secondary: I bring its code up to date before working there.
- Attachment names on your Fedora copy (F08): 7162 files, none too long (2026-11-16). The final check runs on the server at migration time.
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
