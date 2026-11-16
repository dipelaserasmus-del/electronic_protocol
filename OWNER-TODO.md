# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-16 20:49 (after Batch 5). Times are Greek time.

## ▶ Your next message
Batch 5 is done ([summary](docs/reports/BATCH-5.md)). Start a **new conversation** in this project and send:

```
run batch 6
```

Batch 6 (R1a → R1b → R1c → R2 → R4a → R4b) rebuilds the protocol form, the protocol list and the attachments.

## Try it (recommended, a few minutes)
After `git pull` and *Demo: start*, run **Real data: load**: it converts your local copy (assignments, attachment
details, dates). Send me **only the numbers** it prints at the end:
- *Attachments measured: N; file not found: M* (D03), and
- *Dates that were not real dates …: N* (D04). If instead it stops with *"… protocol(s) have a registration date that
  is not a real date"*, send only that first sentence **without the ids**: nothing was changed and I'll handle it.

## Final deployment (when all tasks are completed and verified)
1. I'll write a step-by-step guide for moving the server from XAMPP to Docker Desktop on the same Windows 11 PC:
   back up the database and the files; check the attachment file names on the server
   (`php tools\check-attachment-names.php …`, F08); load the database (`migrate:adopt-legacy`,
   then `migrate`, which also converts to utf8mb4, long subjects, the assignments table and real dates); measure the
   attachments (`php artisan attachments:fill-metadata`, D03);
   check the user list for unknown administrators; press *Ρυθμίσεις → Backup* once and check the file opens.
   Two security gaps of the **current** server stay open until then, by your decision (2026-11-16): anyone who can
   reach it can create an administrator account (S00) and can download inbox e-mails left in `public/tmp` (R5a).
   The new version closes both by itself, so the Apache rules in [docs/BLOCK-REGISTRATION.md](docs/BLOCK-REGISTRATION.md)
   and [docs/BLOCK-PUBLIC-EMAIL-COPIES.md](docs/BLOCK-PUBLIC-EMAIL-COPIES.md) are only needed if you change your mind
   before then.
   Until then, export the database with **phpMyAdmin** (the Backup button on the server creates empty files).

## Whenever it happens
2. **Date reset on save (B03d, on hold):** when the date or number changes by itself again, write down the screen, your
   role and what you clicked, and send me that (no data).

## Already decided (no action)
- No changes on the production server until the move to the new version, also not the two Apache security rules (2026-11-16).
- Your e-mails in `local-data/emails/` (now 9, incl. the sick-leave and emoji ones of 2026-11-16): I read them only on this computer; nothing from them is published. Tests copy only their structure.
- Production will run the Docker stack after the rebuild (replaces XAMPP); on the same Windows 11 PC (Docker Desktop); you migrate the old database and files (2026-11-16).
- No deployment to production until all tasks are completed and verified (2026-11-16).
- L02 done: real data loads on the Mac too (2026-11-16). The Mac is secondary: I bring its code up to date before working there.
- Attachment names on your Fedora copy (F08): 7162 files, none too long (2026-11-16). The final check runs on the server at migration time.
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
