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
  account. To see real e-mails there, put `.eml` files in `local-data/emails/` (see below).
- **Separate database** (`protocol_real`, own Docker volume): the demo stack and a normal installation are not affected.
- `git` ignores `local-data/` and `.env.real`, and CI fails if such files are ever committed.

## 1. Get the data from the production server
1. **Database:** in the app, *Ρυθμίσεις → Backup* creates a `.sql.gz` file in
   `storage/app/arxeio/backups/` on the server. Or run `mysqldump` yourself. Either `.sql` or `.sql.gz` works.
2. **Attachments:** copy the server's whole `storage/app` folder (it contains `arxeio/…` with the files).

## 2. Put them in `local-data/`
```
local-data/
├── db/                 ← the backup file(s); the newest is used
│   └── backup.sql.gz
├── storage/app/        ← the server's storage/app folder (arxeio/, trash/, …)
└── emails/             ← optional: .eml files to show in the local test mailbox
```

## 3. Load and open
```bash
./dev.sh real load
```
This imports the newest backup into the local copy (it replaces the copy's previous content), applies the
database changes made since then, and prints counts only, for example
`Loaded: 4210 protocols, 9 users, 3100 attachments (3100 files found in local-data/storage/app)`.
If "files found" is lower than "attachments", some files were not copied.

Then open **http://localhost:8081** and log in with your usual production username and password.

| Command | Does |
|---|---|
| `./dev.sh real up` | start the copy (after a reboot) |
| `./dev.sh real load [file]` | (re)load the newest backup, or the given file |
| `./dev.sh real down` | stop it |
| `./dev.sh real wipe` | delete the copy's database (your files in `local-data/` are kept) |

## Trying a change against your data
```bash
git fetch && git checkout task/<ID>-...
./dev.sh real load        # fresh copy, then the branch's database changes are applied
```
Report back only what you see ("works" or the error message), never the data itself.

## AI tools in VS Code
If you use AI extensions in VS Code (Copilot, Claude, …), do not let them read `local-data/`: anything they
read is sent to their provider.
