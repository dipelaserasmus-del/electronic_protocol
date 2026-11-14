# Hand-off log

Read this first when (re)starting work. Update it at the end of every task.

## Current state — 2026-11-14
- **Batch 2 complete** and merged (PRs #14–#20, report + L02 in #21), `master` CI green. 114 PHPUnit tests, 8 browser tests.
  Summary: [`docs/reports/BATCH-2.md`](../reports/BATCH-2.md).
- **Next:** Batch 3 → `N01` (PHP/Composer upgrade, drop ext-imap) → `N02` → `N03` → `N04`.
  Recommended to the owner (no answer yet): move `R5a` (inbox cache, the main remaining timeout cause) and `D05`
  (utf8mb4, emoji) right after Batch 3.
- Owner says "run batch N" to start a batch. **Claude completes the whole PR procedure on its own** (commit, push,
  answer reviews, merge on green CI).

## Open items / waiting on the owner
| Item | Status |
|---|---|
| `B03d` date reset on save | **Pinned (on hold).** Not reproducible by Claude or the owner. Production has *Έλεγχοι καταχώρισης* (`protocolValidate`) = **NO**. Owner will report steps when it happens again. |
| Real emails for tests | Owner will provide some public-safe `.eml` files; remind before email tasks (R5a–R5d, B02 part in R5a). |
| `L01` + `L02` owner's computers | **Prepared, waiting for owner.** Fedora (GNOME, SELinux, Docker) moving from Flatpak to the **RPM VS Code**, plus a **Mac**. Guides: `docs/VSCODE-FEDORA.md`, `docs/VSCODE-MACOS.md`, then `docs/LOCAL-SETUP.md`. Owner replies "L01/L02 works" or the output of `./dev.sh doctor` → mark both done in tasks.toml. |
| `S04` production `.env` | Owner (2026-11-14): the production key is randomly generated and can be recreated; Claude may handle `.env` as needed. Back up `.env` before the next `git pull` on the server. |
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
| 2026-11-14 | L02 + Batch 2 report | #21 | Mac guide, RPM VS Code, switching guide, `./dev.sh doctor`, e2e in container (Mac: host.docker.internal), CI: dev.sh on macOS + arm64 image |
| 2026-11-13 | B07 | #20 | `MessageMover` (IMAP move/EXPUNGE bugs), folder lookup by path, invalid search/print filters ignored + reported, update check removed |
| 2026-11-13 | B04 | #19 | error reference codes `E-XXXXXX`, Greek error pages, details for admins only |
| 2026-11-13 | S04 | #18 | `.env` untracked, published `APP_KEY` removed |
| 2026-11-13 | F05 | #17 | `e2e/` Playwright suite, `./dev.sh e2e`, `EVIDENCE=<ID>` screenshots, CI job builds full stack |
| 2026-11-13 | L01 | #16 | `.vscode/` tasks via `flatpak-spawn --host`, `:z` mounts, shared perms, `docs/VSCODE-FEDORA.md`; waiting owner |
| 2026-11-13 | F04 | #15 | `./dev.sh real load` (port 8081, mail/IMAP sandboxed); storage/app now git-ignored + CI hygiene check (464 demo PDFs untracked) |
| 2026-11-13 | B09 | #14 | mail via `OutgoingMail`; found: emoji breaks e-mail registration (D05), IMAP move EXPUNGE error (→B07) |
| 2026-11-13 | Cloud setup | #12 | `docker/dev/cloud-setup.sh` + `./dev.sh cloud-up`; dedicated cloud environment proposed |
