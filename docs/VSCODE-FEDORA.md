# VS Code on Fedora (Flatpak) — run the app locally, with demo or real data

Written for: **Fedora (GNOME), SELinux enforcing, Docker installed and running, VS Code installed as a Flatpak.**
Everything runs on your computer; nothing here sends data anywhere.

## 0. One-time checks (Terminal app, not VS Code)
```bash
docker info --format '{{.ServerVersion}}'
```
It must print a version. If it says "permission denied", add yourself to the `docker` group and log out/in:
```bash
sudo usermod -aG docker "$USER"
```
VS Code's Flatpak must be allowed to run commands on the host. Check it:
```bash
flatpak info --show-permissions com.visualstudio.code | grep -i org.freedesktop.Flatpak
```
If that prints nothing, allow it:
```bash
flatpak override --user --talk-name=org.freedesktop.Flatpak com.visualstudio.code
```

## 1. Get the code
```bash
git clone https://github.com/dipelaserasmus-del/electronic_protocol.git ~/electronic_protocol
```
Then in VS Code: **File → Open Folder… → `~/electronic_protocol`**, and click **"Trust"** when asked.
Accept the recommended extensions it suggests (PHP, Blade, Mermaid preview…).

## 2. Start the demo app
**Terminal → Run Task… → "Demo: start (http://localhost:8080)"**.
The first time takes a few minutes (it builds the image and installs PHP packages). Then open
http://localhost:8080 and log in as `admin` / `password`. Caught e-mails: http://localhost:8025.

## 3. Your real data
1. From the production server copy:
   - the database backup (`.sql.gz` from *Ρυθμίσεις → Backup*, or a `mysqldump`) → `~/electronic_protocol/local-data/db/`
   - the whole `storage/app` folder → `~/electronic_protocol/local-data/storage/app/`
   - (optional) e-mails as `.eml` → `~/electronic_protocol/local-data/emails/`
2. **Terminal → Run Task… → "Real data: load newest backup from local-data/db"**
3. Open http://localhost:8081 and log in with your normal production account.

Details and safety notes: [LOCAL-REAL-DATA.md](LOCAL-REAL-DATA.md). In short: e-mails are only caught locally
(http://localhost:8026), your real mailbox is never contacted, and `local-data/` can't be committed.

## 4. Everyday use
| I want to… | Run Task… |
|---|---|
| get the latest version | "Update: get latest version from GitHub", then "Demo: start" |
| try a task branch with my data | in the terminal: `git checkout task/<ID>-…`, then "Real data: load…" |
| run the tests | "Tests: run all" |
| stop everything | "Demo: stop" and "Real data: stop" |

The integrated terminal (**Terminal → New Terminal**) opens on your host ("Host bash (Flatpak)"), so `docker`
and `./dev.sh` work there too.

## Why the extra settings
| Fedora specific | What the repository does |
|---|---|
| VS Code Flatpak can't see Docker | tasks and terminal run through `flatpak-spawn --host` (`docker/dev/host-run.sh`, `.vscode/settings.json`) |
| SELinux blocks containers from reading your home folder | all folder mounts carry the `:z` label in the compose files |
| files created by containers owned by another user | `./dev.sh` uses shared write permissions and installs PHP packages as your user |

## AI extensions
If you use Copilot, Claude or similar in VS Code: `local-data/` is hidden from the explorer and search, which
keeps it out of most automatic context — but an extension could still read it if asked. Don't open those files
with an AI chat active.

## If something goes wrong
| Symptom | Fix |
|---|---|
| Task says `flatpak-spawn: not found` / Docker not found | step 0, Flatpak permission |
| `permission denied` on `/var/www/html/...` in the logs | SELinux: make sure you started with the repository's compose files (`./dev.sh …`), not your own |
| Port 8080/8081 already in use | change `APP_PORT` in `.env.development` / `.env.real` |
| Anything else | copy the **error message only** (no data) and send it to me |
