# macOS: VS Code + Docker

For any Mac with macOS 14 or later, with an Apple chip (M1…M4) or Intel. Then continue with
[LOCAL-SETUP.md](LOCAL-SETUP.md), which has the same steps as on Fedora. Nothing here sends your data anywhere.

Not sure which chip you have? Apple menu → **About This Mac** → "Chip" (Apple M…) or "Processor" (Intel).

## 1. Install the tools
1. **Git:** in the Terminal app (Applications → Utilities → Terminal):
   ```bash
   xcode-select --install
   ```
   Click *Install* in the window that opens. Skip it if `git --version` already prints a version.
2. **Docker Desktop:** download it from docker.com → *Docker Desktop for Mac* (choose **Apple chip** or **Intel chip**).
   Open it once and accept the terms. Then:
   - Settings → General: tick **Start Docker Desktop when you sign in to your computer**.
   - Settings → Resources: give it at least **4 GB** of memory.

   Docker Desktop is free for personal use, education and small organisations (Docker's terms).
3. **VS Code:** download it from code.visualstudio.com and drag it to *Applications*.
   Optional: in VS Code press ⌘⇧P → **Shell Command: Install 'code' command in PATH**.
4. **Same settings as on Fedora:** in VS Code, Accounts icon (bottom left) → **Backup and Sync Settings…** → sign in
   with the same GitHub account you use on Fedora. Your settings and extensions appear here too.

Check in the Terminal:
```bash
docker info --format '{{.ServerVersion}}'
```
It must print a version. If not, Docker Desktop isn't running: open it and wait for the whale icon in the menu
bar to stop animating.

## 2. Where to put the code — important for your real data
Clone into your **home folder** (`~/electronic_protocol`), as [LOCAL-SETUP.md](LOCAL-SETUP.md) shows.

Do **not** put it in **Desktop**, **Documents**, **iCloud Drive**, Dropbox, OneDrive or Google Drive. If iCloud
syncs Desktop & Documents (System Settings → your name → iCloud → iCloud Drive → *Desktop & Documents Folders*),
anything in those folders is uploaded to Apple, including `local-data/` with the production backup.
Your home folder itself is not synced.

**Time Machine** also backs up `local-data/`. That is fine if your backup disk is encrypted. Otherwise exclude it:
System Settings → General → Time Machine → Options → **+** → `~/electronic_protocol/local-data`.

## 3. Continue
→ [LOCAL-SETUP.md](LOCAL-SETUP.md). On the Mac, use ⌘ where the guide says Ctrl.

## Mac specifics (already handled by the repository)
| Mac | What the repository does |
|---|---|
| Apple chip (arm64) | every image used exists for arm64; CI builds the app image on arm64 too |
| macOS has an old bash (3.2) and BSD tools | `./dev.sh` is tested with them on a macOS machine in CI |
| containers can't use the Mac's network directly | browser tests reach the app through `host.docker.internal` automatically |
| no SELinux | the `:z` mount labels are simply ignored |

| Symptom | Fix |
|---|---|
| `Cannot connect to the Docker daemon` | open Docker Desktop and wait until it says "running" |
| First start takes 5–10 minutes | normal: the app image is built for your chip once |
| Pages slower than on Fedora | normal for Docker on a Mac (files are shared through a virtual machine) |
