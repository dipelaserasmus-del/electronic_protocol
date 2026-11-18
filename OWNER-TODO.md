# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-18 11:10 (after Batch 6.6). Times are Greek time.

## ▶ Your next message
Batch 6.6 is done ([summary](docs/reports/BATCH-6.6.md)): your notes on tags, folders, the number and the missing-number
rows. Send more notes on the form or the list whenever you have them; otherwise start a **new conversation** and send:

```
run batch 7
```

Batch 7 (R3a → R3b → R3c) rebuilds the search: the advanced search screen, Gmail-style search words (`από:`,
`φάκελος:` …) and the search box in the top bar.

## Try it (5 minutes)
After *Update: get latest version from GitHub* and *Demo: start*, log in as `admin`:
1. *Νέο*: the number in the middle; type a number to see "Ελεύθερος".
2. *Σχετικοί αριθμοί*: `1` Enter (green ✔), `999999,` (orange ⚠); click a tag's text to change it.
3. *Λέξεις κλειδιά*: type part of a word without accents; choose with ↓ Enter.
4. *Φάκελος*: click it and type a word of a description (e.g. `εκδρομ`).
5. *Λίστα*: a green *ΚΕΝΟ* row (the demo has missing numbers).

## Questions for you
1. **Folders list:** send the full list (code, title, description) as a spreadsheet or text file; I'll add an import so
   nobody types it. Should a folder have a short **title** separate from its long description? Today it has a code, a
   description and remarks (see [D06](docs/reports/D06.md)).
2. **Firefox on Fedora crashes when a file is dropped:** a Firefox/GNOME bug, not the page (see
   [Batch 6.6](docs/reports/BATCH-6.6.md)). On Fedora use *επιλέξτε* in the attachments box; drop works on Windows.
3. **Attachments shown as missing in your real-data copy** (port 8081): run *Real data: load newest backup from
   local-data/db* and send me the two counts it prints (attachments measured / files not found).
4. **The `--` problem** you mentioned: I could not find it in the old code. Saving and showing `--` (and `' " ; % _ \
   < > &`, Greek, emoji) works and is now tested. If it still happens, send the screen and what you typed (no real
   data).
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
