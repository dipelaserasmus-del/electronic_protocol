# Running a local copy with your real data

A second, separate local stack for testing changes against a copy of your production data.
**Everything stays on your computer**: the data lives in the git-ignored `local-data/` folder, is never
committed, never uploaded, and is never read by Claude.

| | Demo stack | Real-data copy |
|---|---|---|
| Start | `./dev.sh up` | `./dev.sh real up` |
| Address | http://localhost:8080 | **http://localhost:8081** |
| Data | invented demo data | your production backup |
| Caught e-mails | http://localhost:8025 | http://localhost:8026 |

## Safety built in
- **No e-mail leaves your computer.** Everything the copy "sends" (receipts, assignment notices) is caught
  by Mailpit at http://localhost:8026.
- **Your real mailbox is never touched.** The e-mail page connects to a local test mailbox, not to your real
  account. To see real e-mails there, put `.eml` files in `local-data/emails/` (see below), or connect a
  **temporary test mailbox** of your own ([below](#a-temporary-test-mailbox-optional)).
- **Separate database** (`protocol_real`, own Docker volume): the demo stack and a normal installation are not affected.
- `git` ignores `local-data/` and `.env.real`, and CI fails if such files are ever committed.

## 1. Get the data from the production server
1. **Database:** export it with **phpMyAdmin** on the server: select the database → *Export* → method *Quick*,
   format *SQL* → *Export*. Plain `.sql`, gzip (`.sql.gz`) and zip (`.zip`) all work. A `mysqldump` file also works.
   *Do not use the app's Ρυθμίσεις → Backup for now: the version on the server creates empty files (fixed in task B10,
   reaches the server when you deploy it).*
   Older installations have no `migrations` table; `real load` handles that by itself (F07).
2. **Attachments:** copy the server's whole `storage/app` folder (it contains `arxeio/…` and `trash/…` with the
   files), as it is. A later copy may add only the new files to it (for example with your `copy-recent-folders.sh`).
   Take the database and the files **at the same time**: a file added after the export has no row yet and is only
   counted, and a row added after the file copy has no file.

## 2. Put them in `local-data/`
```
local-data/
├── db/                 ← the backup file(s); the newest is used
│   └── protocol.sql (or .sql.gz / .zip)
├── server-files/       ← the server's storage/app folder (arxeio/, trash/, …) — load only reads it
├── storage/app/        ← the copy's own files: rebuilt from server-files/ on every load (do not put files here)
└── emails/             ← optional: .eml files to show in the local test mailbox
```

## 3. Load and open
```bash
./dev.sh real load        # VS Code: Run Task… → "Real data: load newest backup + files (local-data/db, local-data/server-files)"
```
Load does the whole move to the new version, as the deployment will (D10):
1. imports the newest backup into the local copy (it replaces the copy's previous content) and applies the database
   changes made since then;
2. **files:** empties the copy's `local-data/storage/app/arxeio` and `trash`, then copies every attachment's file from
   `server-files/` to where its row says (`php artisan attachments:import`). `server-files/` is only read. The case of
   the letters does not matter, as on the Windows server (`φ.12` finds `Φ.12`);
3. measures the files (size, SHA-256);
4. applies the folder glossary and the Φ.43 → Φ.46 merge (`folders:glossary --apply`, [FOLDERS.md](FOLDERS.md)).

It prints counts only, for example:
```
Server files: 3150 in the server's folder; 3100 attachments in the database.
  Copied:      3080
  Already here (same file): 0
  Not on the server:        20
  Server files no attachment points to (not copied): 70
…
Folders: 9 added, 74 updated.
Merge Φ.43 → Φ.46: 10 protocols; files moved 20 …
Loaded: 4210 protocols, 9 users, 3100 attachments (3080 files found in local-data/storage/app)
```
*Not on the server* are rows whose file is missing from your copy; *no attachment points to* are server files no row
uses (old leftovers, the server's backups): they are not copied. To see their names, for yourself only:
`./dev.sh real artisan attachments:import local-data/server-files --list` (it copies nothing without `--apply`).

Without a `server-files/` folder, load uses the files already in `local-data/storage/app` as they are (as before D10).

Then open **http://localhost:8081** and log in with your usual production username and password.

| Command | Does |
|---|---|
| `./dev.sh real up` | start the copy (after a reboot) |
| `./dev.sh real load [file]` | (re)load the newest backup, or the given file, with the files from `server-files/` and the folder glossary |
| `./dev.sh real down` | stop it |
| `./dev.sh real wipe` | delete the copy's database (your files in `local-data/` are kept) |

## A temporary test mailbox (optional)
To try the inbox with real e-mails as they arrive, connect a mailbox made **only for testing** (never the
production account): registering an e-mail moves it out of that mailbox's inbox, as it does on the server.

1. Run `./dev.sh real up` once: it creates `local-data/test-mailbox.env` (git-ignored, stays on your computer).
2. Fill it in (the file explains each line):
   ```
   TEST_IMAP_HOST=imap.example.org
   TEST_IMAP_PORT=993
   TEST_IMAP_ENCRYPTION=ssl
   TEST_IMAP_VALIDATE_CERT=true
   TEST_IMAP_USERNAME=test-mailbox@example.org
   TEST_IMAP_PASSWORD=its-password
   ```
   Use `ssl` with port 993, or `tls` with 143 as on the production server. Gmail needs an *app password* (Google account → Security → 2-Step Verification → App passwords).
3. Run `./dev.sh real up` again. It prints *Test mailbox: set*.
4. In the copy (http://localhost:8081): *Ρυθμίσεις → E-mail → Λογαριασμός εισερχομένων* →
   **Δοκιμαστικός λογαριασμός (…)** → *Αποθήκευση*. *Εισερχόμενα* now shows that mailbox.

**Sending through the test mailbox (optional).** Fill in `TEST_SMTP_HOST` and `TEST_SMTP_PORT` in the same file
(465 = SSL, 587 = STARTTLS; the username and password default to the IMAP ones) and run `./dev.sh real up`.
For safety, e-mails then reach **only** the addresses in `TEST_MAIL_ONLY_TO` (default: the test mailbox itself).
Anyone else, such as the real staff in your copy who get assignment notices, is taken off. The message goes to the
first allowed address instead, with *«[ΔΟΚΙΜΗ] προς: …»* and the original recipients in its subject. To send to a
colleague's test address too, add it to `TEST_MAIL_ONLY_TO` (comma-separated). Empty `TEST_SMTP_HOST` = Mailpit again.

Back to the local test mailbox: choose `default` there. `./dev.sh real load` always switches back to `default`.
To remove the test account, empty `TEST_IMAP_HOST` (or delete the file) and run `./dev.sh real up`.
Without `TEST_SMTP_HOST`, receipts and notices go to Mailpit (http://localhost:8026), never out.

## Trying a change against your data
```bash
git fetch && git checkout task/<ID>-...
./dev.sh real load        # fresh copy, then the branch's database changes are applied
```
Report back only what you see ("works" or the error message), never the data itself.

## AI tools in VS Code
If you use AI extensions in VS Code (Copilot, Claude, …), do not let them read `local-data/`: anything they
read is sent to their provider.
