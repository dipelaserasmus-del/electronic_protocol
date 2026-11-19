# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-19 14:10 (after Batch 9; R2e, R6d, R2f, R2g, R2h, R5e; D07 your folder list). Times are Greek time.

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
