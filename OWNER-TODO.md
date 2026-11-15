# Your to-do list

Kept up to date by Claude at the end of every batch. Most important first. Last update: 2026-11-15 (after Batch 2).

## ▶ Your next message
Start a **new conversation** in this project and send (fill in the brackets):

```
Production server: OS [..], PHP [..], php-imap installed [yes/no], MySQL/MariaDB [..], web server [Apache/nginx],
I update it with [git pull / copying files / other]. Deployed so far: [nothing / Batch 1 / …].
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

**Why first:** Batch 3 upgrades PHP and its packages and removes the need for `php-imap`. I have to know which PHP
version your server runs, so that the update won't break the live app.

## Before Batch 4 (D05 emoji support, R5a e-mail cache)
1. **Set up your computers:** follow [docs/LOCAL-SETUP.md](docs/LOCAL-SETUP.md)
   ([Fedora RPM](docs/VSCODE-FEDORA.md) · [Mac](docs/VSCODE-MACOS.md)). Reply **"L01/L02 works"** or paste the output
   of the task "Check: set-up status".
   *Needed because D05 changes how texts are stored, and you must check it on your real data locally before it is merged.*
2. **E-mails for testing R5a.** Prepare a few `.eml` files (in Thunderbird: select the message → *Save As*):
   - Greek subjects/text that show as `??` today
   - e-mails from Outlook / Exchange, Gmail and the ministry's e-mail system
   - attachments: PDF, Word, a large one (> 10 MB)
   - a subject with an emoji
   - optionally a `winmail.dat` attachment (needed later, Batch 8)

   And tell me **where** they go:
   - **public-safe** ones: I add them to the repository as test files, and everyone can see them;
   - **private** ones: in your `local-data/emails/`. I never see these; you run the check and tell me the result.

## Production
3. **Before the next `git pull` on the server:** back up `.env` (it's no longer in the repository) and the database.
4. **Fixes reach your users only after you deploy them.** Batches 1–2 fix the e-mail 500 errors, numbering,
   access control and more. If you want, ask me: **"write a production update guide"**.
5. **After deploying Batch 1:** run `php artisan migrate`, and check the user list for administrator accounts
   you don't recognise. Until Batch 1 is deployed, anyone could register as an administrator.

## Whenever it happens
6. **Date reset on save (B03d, on hold):** when the date or number changes by itself again, write down the screen, your
   role and what you clicked, and send me that (no data).

## Already decided (no action)
- R5a and D05 moved earlier (Batch 4), 2026-11-15.
- Knowledge base: declined; I may suggest it again if it becomes clearly useful.
- `.env` / `APP_KEY`: random and can be recreated; I handle it as needed.
- I complete pull requests on my own (commit, push, reviews, merge on green CI).
