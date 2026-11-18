# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-18 23:35 (after Batch 9). Times are Greek time.

## ▶ Your next message
Batch 9 is done ([summary](docs/reports/BATCH-9.md)): users, folders, settings, backups and printing are rebuilt. Try
it (below) and send notes if something looks wrong; otherwise start a **new conversation** and send:

```
run batch 10
```

Batch 10 (A01–A06, X01–X02, Z01–Z03) is the last one: integrity checks, a log of who changed what, removing the old
code, and a final security review. After it, I write the deployment guide.

## Try it (10 minutes)
After *Update: get latest version from GitHub* and *Demo: start*, log in as `admin`:
1. *Διαχείριση → Χρήστες*: untick *Ενεργός* for `reader` and save. In another browser, `reader` cannot log in. Tick it
   again.
2. *Διαχείριση → Φάκελοι και διατήρηση → Εισαγωγή λίστας*: copy a few rows of your folder spreadsheet (code,
   description, Διατήρηση, remarks), paste them and press *Έλεγχος*. Nothing is saved until *Εισαγωγή*.
3. Open a folder (*Επεξεργασία*), tick a user under *Αυτόματη ανάθεση → για Διεκπεραίωση* and save. Log in as
   `writer` and make a protocol in that folder: under *Φάκελος* it says who will get it.
4. *Ρυθμίσεις*: read the explanations; type `abc` in *Γραμμές ανά σελίδα* and save: it is refused at the field.
5. *Εκτύπωση πρωτοκόλλου*: choose one month (dates) and press *Εκτύπωση*; compare with an old printout.

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
- Your folder list: no need to send it; paste it yourself in *Φάκελοι και διατήρηση → Εισαγωγή λίστας* (Batch 9).
  A short title separate from the description was not added: tell me if you want one.
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
