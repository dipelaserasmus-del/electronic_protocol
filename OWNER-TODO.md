# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-16 (after N03).

## ▶ Your next message
Nothing needed: Batch 3 is running (N01, N02, N03 done; N04 next). When it finishes I'll update this list.

**Registration (security):** the code on your server still lets anyone who can reach it create an **administrator**
account (fixed in S00, but you deploy only at the end). The one-line Apache rule that refuses registrations is in
[docs/BLOCK-REGISTRATION.md](docs/BLOCK-REGISTRATION.md) (you decided not to apply it yet, 2026-11-16).

## When you have time
2. **L02, real data on the Mac** (postponed, blocks nothing): `git pull`, then *Real data: load*. Take the database
   and the files from the server at the same time. Reply **"L02 works"**.

## Final deployment (when all tasks are completed and verified)
3. I'll write a step-by-step deployment guide before then. It will include: back up the database and `.env`;
   `php artisan migrate:adopt-legacy` once, then `php artisan migrate`; check the user list for administrator accounts
   you don't recognise; press *Ρυθμίσεις → Backup* once and check the downloaded file opens.
   Until then, export the database with **phpMyAdmin** (the Backup button on the server creates empty files).

## Whenever it happens
4. **Date reset on save (B03d, on hold):** when the date or number changes by itself again, write down the screen, your
   role and what you clicked, and send me that (no data).

## Already decided (no action)
- Long e-mail subjects (over 255 characters) are fixed in Batch 4 (task D01, moved earlier), 2026-11-16.
- Your e-mails in `local-data/emails/`: received (2026-11-16). I read them only on this computer; nothing from them is published.
- Production will run the Docker stack after the rebuild (replaces XAMPP); on the same Windows 11 PC (Docker Desktop); you migrate the old database and files (2026-11-16).
- No deployment to production until all tasks are completed and verified (2026-11-16).
- L02 postponed (2026-11-16).
- R5a and D05 moved earlier (Batch 4), 2026-11-15.
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
