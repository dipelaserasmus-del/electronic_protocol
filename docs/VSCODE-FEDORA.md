# Fedora: VS Code + Docker

For: **Fedora (GNOME), SELinux enforcing, Docker installed.** Then continue with [LOCAL-SETUP.md](LOCAL-SETUP.md)
(same steps as on the Mac). Nothing here sends your data anywhere.

## Which VS Code: use Microsoft's RPM (recommended)
You can switch between this computer and your Mac at any time. That works best with the **RPM version of VS Code**:

| | RPM (Microsoft's repository) | Flatpak |
|---|---|---|
| Terminal and tasks see Docker | directly, as on the Mac | only through `flatpak-spawn --host` |
| Extra permission override | none | needed (`--talk-name=org.freedesktop.Flatpak`) |
| Same behaviour as on the Mac | yes | no (different terminal profile) |
| Sandbox protection | — | none in practice: that override lets VS Code run any command on your computer |
| Updates | with `dnf` / GNOME Software | with Flatpak |

The repository still works with the Flatpak (tasks detect it), so nothing breaks if you keep it.

## 1. Docker (one-time check, in the Terminal app)
```bash
docker info --format '{{.ServerVersion}}'
```
It must print a version. If it says "permission denied", add yourself to the `docker` group, then log out and in:
```bash
sudo usermod -aG docker "$USER"
```
`docker compose version` must also work. If it doesn't, install the Compose plugin:
```bash
sudo dnf install docker-compose-plugin
```

## 2. Install VS Code from Microsoft's repository
These are Microsoft's official instructions for Fedora:
```bash
sudo rpm --import https://packages.microsoft.com/keys/microsoft.asc
```
```bash
echo -e "[code]\nname=Visual Studio Code\nbaseurl=https://packages.microsoft.com/yumrepos/vscode\nenabled=1\nautorefresh=1\ntype=rpm-md\ngpgcheck=1\ngpgkey=https://packages.microsoft.com/keys/microsoft.asc" | sudo tee /etc/yum.repos.d/vscode.repo > /dev/null
```
```bash
sudo dnf install code
```

### Moving from the Flatpak (keeps your settings and extensions)
1. **In the Flatpak VS Code first:** Accounts icon (bottom left) → **Backup and Sync Settings…**
   (older versions: *Turn on Settings Sync…*) → sign in with GitHub → keep everything ticked.
2. Install the RPM (step 2 above), open it, Accounts icon → **Backup and Sync Settings…** → same GitHub account.
   Your settings and extensions come back.
3. Remove the Flatpak:
   ```bash
   flatpak uninstall com.visualstudio.code
   ```
   Also remove its permission override:
   ```bash
   flatpak override --user --reset com.visualstudio.code
   ```
Your clone `~/electronic_protocol` stays where it is: you don't need to clone again or download anything again.

Settings Sync stores only VS Code's own settings and the list of extensions on Microsoft's servers. It never
stores the project, `local-data/` or anything from the app.

## 3. Continue
→ [LOCAL-SETUP.md](LOCAL-SETUP.md) (get the code, start the demo, load your real data, switch computers).

## Fedora specifics (already handled by the repository)
| Fedora | What the repository does |
|---|---|
| SELinux blocks containers from reading your home folder | every folder mount has the `:z` label |
| files created by containers would belong to another user | `./dev.sh` uses shared write permissions and installs PHP packages as your user |
| Flatpak VS Code can't see Docker | tasks go through `docker/dev/host-run.sh`. In the terminal, pick **Host bash (Flatpak)** from the **+ ⌄** menu. |

| Symptom | Fix |
|---|---|
| `permission denied` on `/var/www/html/...` in the logs | SELinux: always start with `./dev.sh …` (the repository's compose files), not with your own compose command |
| Terminal opens but `docker` is not found (Flatpak only) | pick **Host bash (Flatpak)**, or move to the RPM |
