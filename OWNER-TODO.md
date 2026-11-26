# Your to-do list

Kept up to date by Claude after every task. Most important first. Last update: 2026-11-26 10:44 (R4m old server never stamped; N06 Symfony security updates; R2s printed book for paper; R5v inbox order per user, range once; R1o time left in days, hours, minutes; R3k no «Νέο» for readers; R6l related numbers to existing protocols; R2r e-mail file last, by its date; R2q Από / Προς tooltip on lines; R6k logs: export, masked by default; R6j logs: Σήμερα; R2p ΑΔΑ pill in the list; R2o «Χωρίς φάκελο» in full; R4l shorter trash notice; R1n fix: «leave page?» when saving; R1m ΑΔΑ-only attachment row; R1l the form shows a refused save; R6i folder rules to existing protocols; R6h fix: saving folder Φ12; R6g folders: sort, a code shows its protocols; R6f Ρυθμίσεις as GitHub's, approved; R5u the inbox look only in the inbox's menu; R2n no folder flagged in the list; R5t stamp the files after registering; R4k the stamp's number and date on white; R5s a folder for every e-mail; R5r a refused Καταχώριση is seen; R4j grey 👁 where there is no preview; R2m list header, ↻ in the top bar, ↑ to the top; R5q inbox look per user, with «Καμία»; R4i the stamp for everyone, designer for administrators; R3j − / + for the narrow page; Batch 9.6 done: direct links, search by when / who, rename, preview, stamp, protocol trash, envelopes, notepad, logs; Batch 9.5 summary written; before: D07–D11 your folder list and real copy, the inbox, the list). Times are Greek time.

## ▶ Your next message
Batch 9.6 is done ([summary](docs/reports/BATCH-9.6.md)): direct links, search by *when* and *who*, renaming and
previewing files, the stamp (editable, put when files are shown, your official stamp ready locally), a trash for
deleted protocols, envelopes in the inbox, a notepad and the log viewer. Batch 9.5 (your notes after Batch 9) now has
its summary too: [BATCH-9.5](docs/reports/BATCH-9.5.md).

**One thing only you can decide** (see *Questions*, 5): optionally, the preview of Word / Excel files. (The envelope
look is now each user's own choice, R5q.)

**No hurry.** Try the new steps 63–83 below (74–83: your requests of 2026-11-25) at your own pace. When you are ready, start a **new conversation** and
send your answers and anything you found, or:

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

### Batch 9.5: your notes after Batch 9 ([summary](docs/reports/BATCH-9.5.md))
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

### Batch 9.6: your requests of 2026-11-23 and 2026-11-24 ([summary](docs/reports/BATCH-9.6.md))
- [ ] **63.** Open a protocol and press *Σύνδεσμος* (right, next to *Απόδειξη*): it says *Αντιγράφηκε*. Paste the link
  in a new tab (e.g. `http://localhost:8080/protocol/1`): the protocol opens. `…/protocol/1/2025` opens no. 1 of 2025;
  a number that does not exist says *Δεν υπάρχει* (the same as for a protocol you may not see).
- [ ] **64.** In the search box at the top: `πότε:σήμερα`, `πότε:χθες`, `πότε:εβδομάδα` (since Monday), `πότε:7μέρες`,
  `πότε:μήνας`, `πότε:πέρσι`; `χρήστης:` + part of a name = registered by that user (e.g.
  `πότε:7μέρες χρήστης:Συγγραφέας`). On *Σύνθετη αναζήτηση*: the buttons under the dates (*Σήμερα … Πέρσι*) and
  *Καταχωρίστηκε από*.
- [ ] **65.** In a protocol's *Συνημμένα*, the ✎ next to a file's name: type a new name (the `.pdf` stays) and press
  *Enter*; *Escape* leaves it. The file opens / downloads under the new name, and `συνημμένο:` finds it.
- [ ] **66.** In a protocol's *Συνημμένα*, the 👁 next to a file: it opens in a large window; ‹ › (or ← →) go to the
  protocol's other files; PDF, pictures, text, Word and Excel / LibreOffice files are shown, others offer *Λήψη*. In
  *Εισερχόμενα* the same for an e-mail's attachments.
- [ ] **67.** *Διαχείριση → Σφραγίδα αρχείων* (administrators only, R4i): change lines, colour, width, the
  emblem, the corner; the preview follows. On your real copy you can load your official stamp:
  `./dev.sh real artisan stamp:import local-data/stamp --preview` (look at `local-data/stamp/preview.png`), then without
  `--preview`; fine-tune it on the page.
- [ ] **68.** Add a PDF to a protocol; in *Συνημμένα* turn *Με σφραγίδα* on and open it with 👁 or download it: it
  has the stamp, top right of the first page. Turn *Με σφραγίδα* off (in *Συνημμένα* or in the preview window): the
  file as you uploaded it. Older files say *η σφραγίδα είναι μέσα στο αρχείο*.
- [x] **69.** Optional, real copy: `./dev.sh real artisan stamp:old` and tell me the numbers it prints (no names).
  Done 2026-11-26: 0 with the old stamp, 5336 PDFs without, 183 left as they are, 164 Word, 2 missing; the old server
  never stamped (R4m).
- [ ] **70.** With *Έλεγχοι καταχώρισης* off, *Διαγραφή* on a test protocol: it goes to *Κάδος → Πρωτόκολλα* and its
  number is free. *Επαναφορά*: back with its number (or a new one, with a note in *Παρατηρήσεις*, if the number was
  given meanwhile). As Συγγραφέας you see only your own deleted protocols.
- [ ] **71.** *Εισερχόμενα*: each e-mail in an envelope. Each user chooses the look: your name → *Εμφάνιση των e-mail*
  (step 76).
- [ ] **72.** *Σημειώσεις* on the right edge of any page: write `# Σήμερα`, `- [ ] κάτι`, `**έντονα**`; *Προβολή*
  shows them formatted, a to-do ticks with a click; it saves by itself and stays private to you.
- [ ] **73.** *Διαχείριση → Αρχεία καταγραφής*: the application's log a day at a time, levels, search, a click shows
  the details.

### Why a server file has no attachment (D11, 2026-11-24)
- [ ] **62.** Optional, on your machine only (it prints file names: do not send them):
  ```
  ./dev.sh real artisan attachments:import local-data/server-files --list
  ```
  Files that are not attachments (`dummy`, backups, `protocol.sqlite`, the copy tool's `.hist/`) are now listed apart.
  Next to every other file without a row it says what the database has: *a row has this name at …* (the same file in
  another folder, e.g. `Φ43/` and `Φ.43/`), *protocol N/2026 is not in the database* (saved after the export), or
  *protocol N/2026 (folder …) has no attachment with this name*. Tell me, in words and without names, if one kind needs
  more than a look (e.g. «many are the same file in another folder»). Also look on the server for the **2 attachments
  that are not in the trash and have no file** (*Not on the server* in the same list).

### Your requests of 2026-11-25 (R3j, R4i, R5q, R2m, R4j, R5r, R5s, R4k, R5t, R2n, R5u)
- [ ] **74.** Your name → *Στενό, στο κέντρο*: under it **− 50% + ↺**. Press **+** or **−** a few times: the page gets
  wider or narrower at once, behind the open menu. Reload the page, or log in on another computer: the width stays.
  **↺** brings back the usual width.
- [ ] **75.** Log in as `writer`: *Σφραγίδα αρχείων* is no longer in the menu (only administrators). Open a protocol
  with a PDF: *Με σφραγίδα* is in *Συνημμένα* and in the 👁 window, whatever *Ρυθμίσεις → Σφραγίδα στα συνημμένα
  (αρχική επιλογή)* says; that setting only decides it for users who have not chosen yet.
- [ ] **76.** *Εισερχόμενα* open; your name → *Εμφάνιση των e-mail* (only in the inbox's menu, R5u): choose *Καμία (μόνο μια γραμμή)*: the envelopes go
  and a thin line with «↓ 2 από N ↓» separates the e-mails, at once. Try the other three; log in as another user: they
  keep their own (or the one in *Ρυθμίσεις*, if they have not chosen).
- [ ] **77.** *Λίστα*: the title is *Λίστα Πρωτοκόλλων* (with the filter, e.g. *· προς Διεκπεραίωση*); no *Νέο* and
  *Ανανέωση* on the page. *Νέο* is in the top bar, which stays at the top while you scroll, on every page; ↻ right after
  *Λίστα ▾* refreshes the list. Scroll down: the small round ↑ at the bottom right brings you back to the top.
- [ ] **78.** A protocol with a file that has no preview (e.g. a `.zip` or an old `.doc`): its 👁 is grey and does
  nothing (the mouse over it says why); ‹ › in the preview window skip it. PDF, Word (docx), Excel (xlsx), txt and
  pictures keep their 👁.
- [ ] **79.** *Εισερχόμενα*, an e-mail with a PDF: leave *Φάκελος* empty, scroll down to the files and press
  *Καταχώριση*. The page goes up to *Φάκελος* (the cursor in it, the reason under it), and above the buttons it says
  *Δεν καταχωρίστηκε.* with *Μετάβαση στο πεδίο*. No «Καταχωρίζεται…» flashes before it.
- [ ] **80.** *Εισερχόμενα*: an e-mail **without** attachments (or remove every ✓): *Καταχώριση* without *Φάκελος* is
  refused (*Για να καταχωρίσετε ένα email επιλέξτε Φάκελο.*). The inbox's folder list no longer has *— Χωρίς φάκελο*.
  Protocol 11334/2026 (saved before, without a folder) you can open and give it a folder.
- [ ] **81.** Open (👁, *Με σφραγίδα*) a PDF whose top right corner has text or a logo: the number and date lines of
  the stamp are on white and always readable; the other lines (e.g. the office's name) let what is under them show.
  *Σφραγίδα αρχείων* shows it on its sample page too.
- [ ] **82.** *Εισερχόμενα*, an e-mail with a PDF: its 👁 opens the window **without** *Με σφραγίδα* (no number yet).
  Register it: under *Καταχωρίστηκε με αριθμό …* the protocol's files are listed, each with 👁, and *Με σφραγίδα*: the
  PDF opens (and prints) with the stamp.
- [ ] **83.** *Λίστα*: a protocol without a folder (e.g. 11334/2026 on your copy) shows ⚠ *Χωρίς* in *Φάκελος*, and its
  row is tinted like a wrong date; the mouse over ⚠ says what to do. Give it a folder: the flag goes.
- [ ] **84.** *Διαχείριση → Ρυθμίσεις*: the menu on the left, one section at a time; change something and press that
  section's *Αποθήκευση*.
- [ ] **85.** *Φάκελοι και διατήρηση*: press *Πρωτ.* (most used first, again: the other way) and *Κωδικός*. Click a
  folder's code: its protocols, as the top bar's search shows them.
- [ ] **86.** On your real copy, *Φάκελοι και διατήρηση* → *Επεξεργασία* on Φ12 (the folder you tried): choose a user
  for Διεκπεραίωση and *Αποθήκευση*: it saves; the code stays «Φ12».
- [ ] **87.** *Φάκελοι και διατήρηση* → *Επεξεργασία* on Φ12 (it has your rule for Διεκπεραίωση) → *Εφαρμογή και στα 606
  υπάρχοντα πρωτόκολλα…*: choose *Όσα δεν έχουν διεκπεραιωθεί*, *Όλα* or *Του ενεργού έτους* (each says how many change)
  → *Εφαρμογή*. No e-mails are sent; the user sees them in *προς Διεκπεραίωση*.
- [ ] **88.** Open a protocol with a file, choose *— Χωρίς φάκελο*, scroll down and press *Αποθήκευση*: the page goes up
  to *Φάκελος* (red, the reason under it, the list open to choose one).
- [ ] **89.** A protocol with an ΑΔΑ and no file (*ΑΔΑ (Διαύγεια, χωρίς αρχείο)*): in *Συνημμένα* the pill says *ΑΔΑ* and the code is on the left, a link to Διαύγεια; no «Μόνο ΑΔΑ».
- [ ] **90.** *Νέο*: type a *Θέμα* and press *Αποθήκευση* at once (without leaving the field): it goes to the saved protocol, no «leave page?».
- [ ] **91.** Delete a test protocol (*Διαγραφή*): the green notice only says *Το πρωτόκολλο N/YYYY μεταφέρθηκε στον κάδο.*, and *κάδο* opens *Κάδος → Πρωτόκολλα*.
- [ ] **92.** *Λίστα*: a protocol without a folder says ⚠ *Χωρίς φάκελο* (in full).
- [ ] **93.** *Λίστα*: a protocol with an ΑΔΑ and no file shows in *Συνημμένα* the pill *ΑΔΑ* and its code, a link to Διαύγεια.
- [ ] **94.** *Διαχείριση → Αρχεία καταγραφής*: choose another day, then *Σήμερα* (next to the day): back to today.
- [ ] **95.** *Αρχεία καταγραφής* → *Εξαγωγή*: a text file of the day's entries as shown (levels, search); e-mail addresses and subjects are masked unless you turn off *Απόκρυψη προσωπικών στοιχείων* (then a warning says why to keep the file safe).
- [ ] **96.** *Λίστα*: the mouse over a sender (Από / Προς): the tooltip has one line each for the sender, *Αρ. εισερχομένου* and *Ημ/νία*.
- [ ] **97.** *Λίστα*: a protocol from the inbox: its real files first, the e-mail last as *12/11/2026 · 10:36:26* (the file's name on hover). *Ρυθμίσεις → Συνημμένα → Το αρχείο του e-mail πρώτο*: as before, for everyone.
- [ ] **98.** *Ρυθμίσεις → Καταχώριση*: with *Σχετικοί αριθμοί και στα δύο πρωτόκολλα* on, *Εφαρμογή και στα υπάρχοντα πρωτόκολλα…* says how many change; *Εφαρμογή σε N* writes them (take a backup first on real data). How it works: [docs/RELATED-NUMBERS.md](docs/RELATED-NUMBERS.md).
- [ ] **99.** Log in as a reader (*Αναγνώστης*): no *Νέο* in the top bar or on *Αναζήτηση*.
- [ ] **100.** As a writer with a time limit (*Ρυθμίσεις → Δικαιώματα*), open your own recent protocol: *για ακόμη 4 μέρες, 2 ώρες, 56 λεπτά* instead of minutes.
- [ ] **101.** *Εισερχόμενα*: the heading shows *παλαιότερα πρώτα ↑* (no *11–20 από 55*, that is at the pager); click it: *νεότερα πρώτα ↓*, first page; it stays so for you only.
- [ ] **102.** *Εκτύπωση → Πρωτόκολλο → Άνοιγμα*: a sheet on screen; *Εκτύπωση* (or save as PDF): A4 landscape with *Σελίδα N από M* at the foot (Chrome / Edge).
- [ ] **103.** On your real copy, once: `./dev.sh real artisan stamp:old --none-stamped`. Then the PDF from before shows the stamp with *Με σφραγίδα* on (and no *η σφραγίδα είναι μέσα στο αρχείο*).

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
5. **Preview of Word / Excel files (R4c):** they are converted on the server with libraries the application already
   has; the layout is approximate. A browser library would look closer to the original but adds new packages. Keep it
   as it is (my suggestion), or ask for the browser libraries.

## Final deployment (when all tasks are completed and verified)
1. I'll write a step-by-step guide for moving the server from XAMPP to Docker Desktop on the same Windows 11 PC:
   - back up the database, the files and the server's `.env`;
   - check the attachment file names on the server (`php tools\check-attachment-names.php …`, F08);
   - load the database: `migrate:adopt-legacy`, then `migrate`. This also converts to utf8mb4, long subjects, the
     assignments table, real dates and the trash columns;
   - copy the attachment files into the new app, by the database: `php artisan attachments:import <the old
     storage\app folder> --apply` (D10; only reads the old folder; the same as *Real data: load* rehearses);
   - measure the attachments (`php artisan attachments:fill-metadata`, D03);
   - check the user list for unknown administrators, and that everyone who works with the protocol is *Ενεργός*
     (inactive users cannot log in, R6a);
   - press *Διαχείριση → Backup → Νέο αντίγραφο* once and check the file opens (it needs `mysqldump`, which the Docker
     stack has);
   - choose how many days the trash keeps deleted attachments (30 if left empty; the old trash gets these days from
     the deployment day);
   - set *Αρ.Πρωτοκόλλου για ξεκίνημα* to the first number to check for missing numbers (R2c);
   - apply the folder glossary and the Φ.43 → Φ.46 merge: `php artisan folders:glossary --apply` (D07, after the files
     are copied: it moves them);
   - load your official stamp: `php artisan stamp:import <the stamp folder>` (R4e);
   - **old files and the stamp (R4m; you, 2026-11-26: the old server never stamped):** `migrate` marks no file as
     «stamp inside» when the old setting *putStamp* is off. Check with `php artisan stamp:old`: «PDF όπου η παλιά
     σφραγίδα αφαιρείται: 0» and, if any file is still counted, run `php artisan stamp:old --none-stamped` (no file is
     touched). Then every old file follows *Με σφραγίδα* like the new ones;
   - write the log one file per day: `LOG_CHANNEL=daily` (and `LOG_DAILY_DAYS`, default 14) in `.env` (R6e);
   - if you applied the Apache rules below in the meantime, remove them before the move.

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
- A folder's automatic assignment applies to protocols saved from then on; for the ones already in it, *Εφαρμογή και
  στα υπάρχοντα…* asks which each time and sends no e-mail (2026-11-25, R6i). Only the administrator changes folders.
- *Ρυθμίσεις* as GitHub's settings (R6f): approved from the screenshots (2026-11-25 11:35).
- Inbox (2026-11-25, R5s): every e-mail needs a *Φάκελος*, with or without attachments; the setting *Ο Φάκελος
  χρειάζεται για κάθε e-mail* is gone. The protocol form keeps *— Χωρίς φάκελο* for protocols on hold.
- The inbox's look (2026-11-25, R5q): each user chooses in their menu (Καμία, air mail, paper, plain); *Ρυθμίσεις →
  Εμφάνιση των e-mail (αρχική επιλογή)* is for users who have not chosen. Question 5 of Batch 9.6 is closed.
- Your fresh real copy with its files (2026-11-24, D10): it loads complete; the folder glossary and Φ.43 → Φ.46 are
  applied (38 and 61 done). The few files without a row are explained by D11 (e.g. a protocol saved after the export).
- *Εισερχόμενα*: *Αριθ. εισερχ.* starts with the e-mail's time (e.g. 14:01:36), on purpose: it stands in when the
  attachment has no protocol number of the sender; when it has one, type that number over it (2026-11-20).
- Same folder, other code (2026-11-23, [D09](docs/reports/D09.md)): `Φ32.β` = `Φ.32β`, `Φ29Α` = `Φ.29`, `Φ33.β` = `Φ.33.1`.
  The glossary updates your folder and keeps its code (no second folder). Φ.43 has no sub-folders.
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
