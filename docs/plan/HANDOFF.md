# Hand-off log

Read this first when (re)starting work. Update it at the end of every task.

## Current state — 2026-11-13
- **Batch 1 complete** and merged (PRs #2–#10), `master` CI green. 94 tests / 260 assertions.
- **Next:** Batch 2 → `B09` (all outgoing email crashes) → `F04` → `L01` (owner VS Code set-up) → `F05` → `S04` → `B04` → `B07`.
  Status of everything: [PROGRESS.md](PROGRESS.md).
- Owner said "run batch N" to start a batch; Claude merges each PR itself when CI is green.

## Open items / waiting on the owner
| Item | Status |
|---|---|
| `B03d` date reset on save | **Pinned (on hold).** Not reproducible by Claude or the owner. Production has *Έλεγχοι καταχώρισης* (`protocolValidate`) = **NO**. Owner will report steps when it happens again. |
| Real emails for tests | Owner will provide some public-safe `.eml` files; remind before email tasks (R5a–R5d, B02 part in R5a). |
| `L01` owner's VS Code set-up | Scheduled right after F04. **Owner's system: Fedora (GNOME), SELinux, Docker installed and running, VS Code as Flatpak.** Needs: `.vscode/tasks.json` + terminal profile through `flatpak-spawn --host` (Flatpak sandbox can't see Docker), `:z` on bind mounts (SELinux), file ownership of files written by containers. Remind owner that AI extensions must not read `local-data/`. |
| Knowledge base | Owner declined (2026-11-13): extra complexity for small gain. Claude may re-suggest it later if it becomes clearly useful. |
| After deploying Batch 1 | Owner: run `php artisan migrate`; check `users` for unknown admin accounts. |

## Decisions log (short)
- Module-by-module rebuild, Livewire + Bootstrap 5, MySQL only (BACKLOG "Decisions").
- Dev image: Ubuntu 26.04 + distro PHP 8.5 (sandbox can't reach Debian/Alpine mirrors; CI builds it too).
- Dev stack isolated: Compose project `electronic-protocol-dev`, containers `ep-dev-*`, volume `devdbdata`.
- Number may be chosen only by admin with `protocolValidate` OFF (same rule as the form).
- Deleting protocols: admins only. Assign: admin + Αναθέτων. Mark done: admin, Αναθέτων, d-assignee, creator.

## Resuming in a fresh Claude Code cloud session
The cloud sandbox can't use Docker Hub, Debian mirrors or GitHub downloads inside containers, so it needs
a one-time preparation (`docker/dev/cloud-setup.sh`: images from the Google mirror, app image built with host
networking, Composer on the host). If the session runs in the **"electronic_protocol"** cloud environment, its
setup script already did this before Claude started. Then:
```bash
./dev.sh cloud-up     # starts Docker + the stack, migrates/seeds on first run (~2 min)
./dev.sh test
```
Without that environment: `bash docker/dev/cloud-setup.sh` first (~7 min on a cold container).
- Playwright: `NODE_PATH=$(npm root -g) node script.js` (Chromium preinstalled).
- Git tags can't be pushed through the sandbox proxy; milestones are recorded in `MILESTONES.md`.
- Never run `artisan db:wipe` without `--env=testing` (it would wipe the dev demo DB).

## Task log
| Date | Task | PR | Notes |
|---|---|---|---|
| 2026-11-12 | F03, F01, F02, S00, B03, B08, S02, A02 | #2–#9 | see `docs/reports/BATCH-1.md` |
| 2026-11-12 | Review fixes | #10 | 9 Codex findings verified + fixed |
| 2026-11-13 | Tracker | #11 | `tasks.toml` + PERT/CPM `PROGRESS.md`, `CLAUDE.md`, this file |
| 2026-11-13 | B09 | #14 | mail via `OutgoingMail`; found: emoji breaks e-mail registration (D05), IMAP move EXPUNGE error (→B07) |
| 2026-11-13 | Cloud setup | #12 | `docker/dev/cloud-setup.sh` + `./dev.sh cloud-up`; dedicated cloud environment proposed |
