# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-18 13:57 (after Batch 7.5). Times are Greek time.

## ▶ Your next message
Batch 7.5 is done ([summary](docs/reports/BATCH-7.5.md)): your notes on folders, the top bar and the protocol page.
Answer the two e-mail questions below when you can; otherwise start a **new conversation** and send:

```
run batch 8
```

Batch 8 (R5c → R5d → R5b) rebuilds the inbox: the e-mail screen, registering a protocol from an e-mail (with
keywords) and `winmail.dat` attachments. 📧 If you have new e-mails that show a problem, put them in
`local-data/emails/` first (they stay on your computer).

## Try it (5 minutes)
After *Update: get latest version from GitHub* and *Demo: start*, log in as `admin`:
1. *Νέο* → *Φάκελος*: type `12` (Φ.12 first), then `φ11` (only Φ.11, never Φ.1.1).
2. The top bar: four items with icons, the organisation under the name. *Διαχείριση* has Εκτύπωση and Διατήρηση; the
   second button in the search box (sliders) opens *Σύνθετη αναζήτηση*.
3. Your name (top right) → *Πλάτος σελίδας* → *Στενό, στο κέντρο*; and back to *Όλη η οθόνη*.
4. *Νέο*: drag a section by ⠿ (left of its title) into the other column; ▴ collapses it; try *Μία στήλη*; then
   *Επαναφορά διάταξης*. Log in on another computer: your layout is there too.

## Questions for you
1. **`??` in `ΑΔΕΙΑ ΚΩΝΣΤΑΝΤΙΝΙΔΟΥ`:** on the current code the inbox shows its sender, subject, text and the three
   attachment names correctly. Where did you see `??`: which screen, which field, and on which copy (8081, the old
   server, an e-mail program)? A description is enough; no screenshot of the e-mail needed.
2. **E-mails of the `ΑΔΕΙΑ` sender not registered:** on the current code both register (with attachments) once a
   *Φάκελος* is chosen. Without a folder, the old inbox page says *«Για να καταχωρίσετε email με συνημμένα αρχεία
   είναι απαραίτητο να επιλέξετε Φάκελο»* and the e-mail stays. Was that the message? If not, send its exact words.
   (This is probably the `--` problem too: that e-mail has `--` before its signature.)
3. **Folders list:** send the full list (code, title, description) as a spreadsheet or text file; I'll add an import so
   nobody types it. Should a folder have a short **title** separate from its long description? Today it has a code, a
   description and remarks (see [D06](docs/reports/D06.md)).
4. **Firefox on Fedora crashes when a file is dropped:** a Firefox/GNOME bug, not the page (see
   [Batch 6.6](docs/reports/BATCH-6.6.md)). On Fedora use *επιλέξτε* in the attachments box; drop works on Windows.
5. **Writers and the protocol date:** new setting *Ρυθμίσεις → Ο Συγγραφέας μπορεί να αλλάξει την ημερομηνία
   πρωτοκόλλου* (off). Turn it on if writers should set the date (e.g. a document received yesterday).

## Final deployment (when all tasks are completed and verified)
1. I'll write a step-by-step guide for moving the server from XAMPP to Docker Desktop on the same Windows 11 PC:
   - back up the database and the files;
   - check the attachment file names on the server (`php tools\check-attachment-names.php …`, F08);
   - load the database: `migrate:adopt-legacy`, then `migrate`. This also converts to utf8mb4, long subjects, the
     assignments table, real dates and the trash columns;
   - measure the attachments (`php artisan attachments:fill-metadata`, D03);
   - check the user list for unknown administrators;
   - press *Ρυθμίσεις → Backup* once and check the file opens;
   - choose how many days the trash keeps deleted attachments (30 if left empty; the old trash gets these days from
     the deployment day);
   - set *Αρ.Πρωτοκόλλου για ξεκίνημα* to the first number to check for missing numbers (R2c).

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
- Your real copy after Batch 7 (2026-11-18): 3653 protocols, 24 users, 7630 attachments, 7148 files found (482 missing:
  the known gap between your backup and your file copy, L01). Thanks: nothing to do.
- Your screen choices (page width, the protocol page's sections) are saved per user, on every computer (2026-11-18).
- Real data after Batch 5, on your Fedora copy: 7183 attachments measured, 646 files not found (the known gap between
  your backup and file copy, L01), 0 dates that were not real dates (2026-11-16). Thanks: nothing to do.
- No changes on the production server until the move to the new version, also not the two Apache security rules (2026-11-16).
- Your e-mails in `local-data/emails/` (now 9, incl. the sick-leave and emoji ones of 2026-11-16): I read them only on this computer; nothing from them is published. Tests copy only their structure.
- Production will run the Docker stack after the rebuild (replaces XAMPP); on the same Windows 11 PC (Docker Desktop); you migrate the old database and files (2026-11-16).
- No deployment to production until all tasks are completed and verified (2026-11-16).
- L02 done: real data loads on the Mac too (2026-11-16). The Mac is secondary: I bring its code up to date before working there.
- Attachment names on your Fedora copy (F08): 7162 files, none too long (2026-11-16). The final check runs on the server at migration time.
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
