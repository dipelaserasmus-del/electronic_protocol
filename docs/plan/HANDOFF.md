# Hand-off log

Read this first when (re)starting work. Update it at the end of every task.

## Current state — 2026-11-16
- **Batch 2 complete** (PRs #14–#21), plus F06 (#23), F07 (#24), B10 (#25). `master` CI green, branch-protected
  (all 9 CI jobs required, admins included, no force push). 127 PHPUnit tests, 9 browser tests.
  Summary: [`docs/reports/BATCH-2.md`](../reports/BATCH-2.md).
- **Owner decision (2026-11-16): no deployment to production until all tasks are completed and verified.**
  So the production server keeps the old code; owner actions "after deploying" are collected for one final
  deployment (see `OWNER-TODO.md`). Consequence: S00 (open admin registration) stays exploitable in production;
  the old code ignores `storage/conf/.denyregister`. Mitigation without deploying code: a web-server rule that refuses
  `POST /register`: `docs/BLOCK-REGISTRATION.md` (owner has it, not applied yet).
- **Next:** Batch 3 → `N01` (PHP/Composer upgrade, drop ext-imap) → `N02` → `N03` → `N04`.
  **Before N01, get the production server's OS/PHP/MySQL versions** (asked in `OWNER-TODO.md`): no deploy until the
  end, but the final deploy must run on that server (or the server is upgraded then), so N01 picks its target from it.
- **Batch 4 = D05 → R5a**, moved earlier by the owner on 2026-11-15. Needs: L01 done ✅ (D05 is verified on real
  data locally, Fedora) and e-mails from the owner for R5a. R5a plugs its cache into the current inbox page.
- **L02 (real data on the Mac) postponed by the owner (2026-11-16)**; not blocking anything.
- Owner's own list: [`OWNER-TODO.md`](../../OWNER-TODO.md). Update it at the end of every batch.
- Owner says "run batch N" to start a batch. **Claude completes the whole PR procedure on its own** (commit, push,
  answer reviews, merge on green CI).

## Open items / waiting on the owner
| Item | Status |
|---|---|
| `B03d` date reset on save | **Pinned (on hold).** Not reproducible by Claude or the owner. Production has *Έλεγχοι καταχώρισης* (`protocolValidate`) = **NO**. Owner will report steps when it happens again. |
| Real emails for tests | Owner will provide some public-safe `.eml` files; **needed before Batch 4 (R5a)**, also R5b/R5c/R5d. Ask public (commit) vs private (`local-data/emails/`, owner runs checks). |
| `L01` + `L02` owner's computers | **L01 done (2026-11-16):** Fedora real copy loads after F07 (owner confirmed). Some attachment rows have no file locally: the owner's DB backup and file copy were taken on different days (attachments open fine). Expected; the next real-data set will be in sync, so a gap then is a real problem. **L02 postponed by the owner (2026-11-16):** Mac demo app works; real data on the Mac still to do. Guides: `docs/VSCODE-MACOS.md`, `docs/LOCAL-SETUP.md`. Owner replies "L02 works" → mark done in tasks.toml. |
| `S04` production `.env` | Owner (2026-11-14): the production key is randomly generated and can be recreated; Claude may handle `.env` as needed. Back up `.env` before the next `git pull` on the server. |
| Production server facts | Asked 2026-11-15 (OWNER-TODO): OS, PHP, php-imap, MySQL, web server, update method, what is deployed. Needed before N01. |
| Knowledge base | Owner declined (2026-11-13): extra complexity for small gain. Claude may re-suggest it later if it becomes clearly useful. |
| Final deployment (all tasks done) | Owner: back up DB + `.env`; `php artisan migrate:adopt-legacy` once (no `migrations` table, F07), then `php artisan migrate`; check `users` for unknown admin accounts; `which mysqldump`, press Backup once (B10). Claude writes a deployment guide before then. |
| Open registration in production (S00) | Not deployed by owner decision. Web-server rules (Apache/nginx) given in `docs/BLOCK-REGISTRATION.md`; owner chose **not to apply them yet** (2026-11-16). Remind at the final deployment (remove the rule before `git pull`). |

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
| 2026-11-16 | B10 | #25 | Backup: mysqldump without shell, errors shown, `--no-tablespaces`, safe file names; stray bytes in `config/imapFolders.php` broke every download; `real load` reads `.zip`. Also L01 done, `master` branch protection (all CI jobs required, admins included) |
| 2026-11-16 | F07 | #24 | production backup has no `migrations` table → `migrate:adopt-legacy` records existing tables; `real load` runs it before `migrate` |
| 2026-11-15 | F06 | #23 | nginx re-resolves `app` (Docker DNS, 5 s): 502 after Docker Desktop restart on the Mac; `tests/shell/nginx-reconnect.test.sh` in CI e2e job |
| 2026-11-15 | Plan change + owner list | #22 | D05, R5a → new Batch 4 (later batches +1); `OWNER-TODO.md` |
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
