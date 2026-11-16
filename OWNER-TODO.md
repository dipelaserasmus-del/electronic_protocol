# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-16 14:26 (after Batch 4). Times are Greek time.

## ▶ Your next message
Batch 4 is done ([summary](docs/reports/BATCH-4.md)). Start a **new conversation** in this project and send:

```
run batch 5
```

Batch 5 (D02 → D03 → D04) changes how dates and numbers are stored in the database, so it is checked on your local
real-data copy as well.

## Try it (optional)
After `git pull` and *Demo: start*, then *Real data: load* (it converts your local copy to the new format, a few
seconds): open *Εισερχόμενα Email*. The second visit should be much faster than the first, names in Greek
should show without `??`, and e-mails with emoji or very long subjects should register.

## When you have time
1. **Attachment names (optional, no server needed):** on your Fedora copy, `./dev.sh real check-names` and send me
   only the 3 numbers it prints (details in [F08](docs/reports/F08.md)).
2. **L02, real data on the Mac** (postponed, blocks nothing): `git pull`, then *Real data: load*. Reply **"L02 works"**.

## Final deployment (when all tasks are completed and verified)
3. I'll write a step-by-step guide for moving the server from XAMPP to Docker Desktop on the same Windows 11 PC:
   back up the database and the files; check the attachment file names on the server
   (`php tools\check-attachment-names.php …`, F08); load the database (`migrate:adopt-legacy`,
   then `migrate`, which also converts to utf8mb4 and long subjects);
   check the user list for unknown administrators; press *Ρυθμίσεις → Backup* once and check the file opens.
   Two security gaps of the **current** server stay open until then, by your decision (2026-11-16): anyone who can
   reach it can create an administrator account (S00) and can download inbox e-mails left in `public/tmp` (R5a).
   The new version closes both by itself, so the Apache rules in [docs/BLOCK-REGISTRATION.md](docs/BLOCK-REGISTRATION.md)
   and [docs/BLOCK-PUBLIC-EMAIL-COPIES.md](docs/BLOCK-PUBLIC-EMAIL-COPIES.md) are only needed if you change your mind
   before then.
   Until then, export the database with **phpMyAdmin** (the Backup button on the server creates empty files).

## Whenever it happens
4. **Date reset on save (B03d, on hold):** when the date or number changes by itself again, write down the screen, your
   role and what you clicked, and send me that (no data).

## Already decided (no action)
- No changes on the production server until the move to the new version, also not the two Apache security rules (2026-11-16).
- Your e-mails in `local-data/emails/` (now 9, incl. the sick-leave and emoji ones of 2026-11-16): I read them only on this computer; nothing from them is published. Tests copy only their structure.
- Production will run the Docker stack after the rebuild (replaces XAMPP); on the same Windows 11 PC (Docker Desktop); you migrate the old database and files (2026-11-16).
- No deployment to production until all tasks are completed and verified (2026-11-16).
- L02 postponed (2026-11-16).
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
