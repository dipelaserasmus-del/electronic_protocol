# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-16 (after B10).

## ▶ Your next message
Start a **new conversation** in this project and send (fill in the brackets):

```
Production server: OS [..], PHP [..], php-imap installed [yes/no], MySQL/MariaDB [..], web server [Apache/nginx],
I update it with [git pull / copying files / other].
Block registration in production now: [yes/no]
run batch 3
```
On the **production server**, these commands print the versions and no data:
```bash
php -v | head -1
```
```bash
php -m | grep -i imap
```
```bash
mysql --version
```
```bash
head -2 /etc/os-release
```

**Why first:** Batch 3 upgrades PHP and its packages and removes the need for `php-imap`. You deploy only at the end,
but the finished app must run on that server (or the server gets upgraded then), so I pick the target from its versions.

**Registration (security):** the code on your server still lets anyone who can reach it create an **administrator**
account (fixed in S00, but you deploy only at the end). Answer "yes" and I'll give you a one-line web-server rule
that refuses new registrations until the final deployment. It changes no app code and is easy to undo.

## Before Batch 4 (D05 emoji support, R5a e-mail cache)
1. **E-mails for testing R5a.** Prepare a few `.eml` files (in Thunderbird: select the message → *Save As*):
   - Greek subjects/text that show as `??` today
   - e-mails from Outlook / Exchange, Gmail and the ministry's e-mail system
   - attachments: PDF, Word, a large one (> 10 MB)
   - a subject with an emoji
   - optionally a `winmail.dat` attachment (needed later, Batch 8)

   And tell me **where** they go:
   - **public-safe** ones: I add them to the repository as test files, and everyone can see them;
   - **private** ones: in your `local-data/emails/`. I never see these; you run the check and tell me the result.

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
- No deployment to production until all tasks are completed and verified (2026-11-16).
- L02 postponed (2026-11-16).
- R5a and D05 moved earlier (Batch 4), 2026-11-15.
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
