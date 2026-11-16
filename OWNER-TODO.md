# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-16 (after Batch 3).

## ▶ Your next message
Batch 3 is done ([summary](docs/reports/BATCH-3.md)). Start a **new conversation** in this project (this one is very
long) and send:

```
run batch 4
```

Batch 4 fixes what your e-mails showed: emoji (D05), long subjects (D01), the inbox `??` and the slow inbox (R5a),
and attachment names on Docker (F08). D05 and D01 change database columns, so I check them on your local real-data
copy too.

**Helpful before Batch 4 (optional):** a fresh backup of the server's database **and** a copy of its attachment
files taken on the same day, in `local-data/` (as for L01). Then *Real data: load*. With both from the same day,
any missing file in the copy is a real finding, not a timing gap.

**Registration (security):** the code on your server still lets anyone who can reach it create an **administrator**
account (fixed in S00, but you deploy only at the end). The one-line Apache rule that refuses registrations is in
[docs/BLOCK-REGISTRATION.md](docs/BLOCK-REGISTRATION.md) (you decided not to apply it yet, 2026-11-16).

## Try it (optional)
After `git pull` and *Demo: start* (the first start rebuilds the app image, a few minutes): log in and open
*Περί…* from your name's menu (the new layout with the menu at the top) and http://localhost:8080/dev/ui (the new
date field, buttons, dialogs). Tell me if anything looks or feels wrong.

## When you have time
2. **L02, real data on the Mac** (postponed, blocks nothing): `git pull`, then *Real data: load*. Take the database
   and the files from the server at the same time. Reply **"L02 works"**.

## Final deployment (when all tasks are completed and verified)
3. I'll write a step-by-step guide for moving the server from XAMPP to Docker Desktop on the same Windows 11 PC.
   It will include: back up the database and the files; check the attachment file names (F08); load the database into
   the new stack (`migrate:adopt-legacy`, then `migrate`); check the user list for administrator accounts you don't
   recognise; press *Ρυθμίσεις → Backup* once and check the downloaded file opens.
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
