# Running the app on your computers (Fedora and Mac)

First install the tools for each computer: [Fedora](VSCODE-FEDORA.md) · [macOS](VSCODE-MACOS.md).
Everything below is the same on both. On the Mac, use ⌘ where it says Ctrl.

## 1. Get the code (once per computer)
In a terminal:
```bash
git clone https://github.com/dipelaserasmus-del/electronic_protocol.git ~/electronic_protocol
```
In VS Code: **File → Open Folder… → `electronic_protocol`** in your home folder, then click **Trust**. Accept the
recommended extensions.

Check the set-up with **Terminal → Run Task… → "Check: set-up status"**. It prints versions and which pages
answer, and never any data, so you can paste its output to me if something is wrong.

## 2. Demo app (invented data)
**Terminal → Run Task… → "Demo: start (http://localhost:8080)"**. The first time takes a few minutes. Then open
http://localhost:8080 and log in as `admin` / `password`. E-mails it "sends" are caught at http://localhost:8025.

## 3. Your real data (local copy)
1. Copy into `~/electronic_protocol/local-data/`:
   - the database backup (`.sql.gz` from *Ρυθμίσεις → Backup*, or a `mysqldump`) into `db/`
   - the server's whole `storage/app` folder into `storage/app/`
   - optional `.eml` e-mails into `emails/`
2. **Run Task… → "Real data: load newest backup from local-data/db"**
3. Open http://localhost:8081 and log in with your normal production account.

Safety details: [LOCAL-REAL-DATA.md](LOCAL-REAL-DATA.md). In short: e-mails are only caught locally
(http://localhost:8026), your real mailbox is never contacted, and git refuses to commit `local-data/`.

## 4. Everyday tasks
| I want to… | Terminal → Run Task… |
|---|---|
| get the latest version | "Update: get latest version from GitHub", then "Demo: start" |
| try a task branch with my data | in the terminal `git checkout task/<ID>-…`, then "Real data: load…" |
| run the tests | "Tests: run all" · "Tests: browser tests" |
| stop everything | "Demo: stop" and "Real data: stop" |
| check the set-up | "Check: set-up status" |

## 5. Switching between Fedora and the Mac
Each computer has its own full copy: code, demo database and real-data copy. You can stop on one and continue
on the other at any time.

| What | How it gets to the other computer |
|---|---|
| **Code** | GitHub. Run "Update: get latest version" on the computer you switch to. |
| **VS Code settings and extensions** | VS Code's *Backup and Sync Settings* (same GitHub account on both) |
| **Demo data** | not copied: each computer has its own invented data ("Demo: reset" makes it fresh) |
| **Real data** | **only by you, offline** — see below. Each computer keeps its own `local-data/` and its own local copy. |

**Resume on the other computer:**
1. Open VS Code there.
2. "Update: get latest version from GitHub".
3. "Demo: start" (and/or "Real data: start").

What you changed in a local copy on one computer is not on the other. Both copies are throw-away test copies,
so to make them identical, load the same backup on both.

**Moving the real data between your computers.** Pick one:
- copy it from the production server again, the same way as the first time (also the freshest)
- a USB stick, preferably encrypted: copy `local-data/db/…` and `local-data/storage/app/`
- your home network only, e.g. from Fedora to the Mac with *Remote Login* turned on on the Mac
  (System Settings → General → Sharing):
  ```bash
  rsync -a ~/electronic_protocol/local-data/ YOUR-MAC-USER@YOUR-MAC-NAME.local:electronic_protocol/local-data/
  ```

**Never** move it through iCloud, Dropbox, Google Drive, OneDrive, e-mail or chat apps: those upload it to the internet.

## AI extensions
If you use Copilot, Claude or similar in VS Code: `local-data/` is hidden from the explorer and search, which keeps
it out of most automatic context, but an extension could still read it if asked. Don't open those files with an
AI chat active.

## If something goes wrong
Run "Check: set-up status" and send me its output, or the **error message only** (no data).
