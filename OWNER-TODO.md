# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-16 (after Batch 4).

## ▶ Do now (security, 5 minutes)
1. **Inbox e-mails can be downloaded without logging in** on your server: the old inbox leaves a copy of every
   e-mail it shows in `public/tmp/u<user>/<number>.eml`, and those addresses are easy to guess. Fixed in the new code
   (Batch 4), but you deploy only at the end. Add the one Apache line from
   [docs/BLOCK-PUBLIC-EMAIL-COPIES.md](docs/BLOCK-PUBLIC-EMAIL-COPIES.md) and run its check (tested on Apache).
2. **Registration:** the server still lets anyone who can reach it create an **administrator** account (fixed in S00,
   deployed only at the end). The matching one-line rule is in [docs/BLOCK-REGISTRATION.md](docs/BLOCK-REGISTRATION.md)
   (you decided not to apply it yet, 2026-11-16). Both lines go in the same file; consider adding both now.

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
3. **Attachment names on the server** (for the final move): run, on the server,
   `php tools\check-attachment-names.php C:\…\storage\app\arxeio` (details in [F08](docs/reports/F08.md)) and send me
   only the 3 numbers it prints.
4. **L02, real data on the Mac** (postponed, blocks nothing): `git pull`, then *Real data: load*. Reply **"L02 works"**.

## Final deployment (when all tasks are completed and verified)
5. I'll write a step-by-step guide for moving the server from XAMPP to Docker Desktop on the same Windows 11 PC:
   back up the database and the files; check the attachment file names (F08); load the database (`migrate:adopt-legacy`,
   then `migrate`, which also converts to utf8mb4 and long subjects); remove the two Apache lines before `git pull`;
   check the user list for unknown administrators; press *Ρυθμίσεις → Backup* once and check the file opens.
   Until then, export the database with **phpMyAdmin** (the Backup button on the server creates empty files).

## Whenever it happens
6. **Date reset on save (B03d, on hold):** when the date or number changes by itself again, write down the screen, your
   role and what you clicked, and send me that (no data).

## Already decided (no action)
- Your e-mails in `local-data/emails/` (now 9, incl. the sick-leave and emoji ones of 2026-11-16): I read them only on this computer; nothing from them is published. Tests copy only their structure.
- Production will run the Docker stack after the rebuild (replaces XAMPP); on the same Windows 11 PC (Docker Desktop); you migrate the old database and files (2026-11-16).
- No deployment to production until all tasks are completed and verified (2026-11-16).
- L02 postponed (2026-11-16).
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
