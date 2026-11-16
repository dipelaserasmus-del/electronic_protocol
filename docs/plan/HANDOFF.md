# Hand-off log

Read this first when (re)starting work. Update it at the end of every task.

## Current state — 2026-11-16 14:26 (after Batch 4; times are Greek time)
- **Batch 4 complete:** D05 (#34), D01 (#35), R5a (#36), F08 (#37). Summary: [`docs/reports/BATCH-4.md`](../reports/BATCH-4.md). 232 PHPUnit tests, 25 browser tests. R5a found that inbox e-mails in `public/tmp` are downloadable without login on the current server; the owner does not touch production before the migration (2026-11-16), so the Apache rules (`docs/BLOCK-PUBLIC-EMAIL-COPIES.md`, `docs/BLOCK-REGISTRATION.md`) stay unapplied and the new version closes both gaps. Open: one flaky CI failure of `e2e/tests/attachment-names.spec.js` (upload write failed twice in run 43864641571, passed on rerun); `storeAs()` failures are not checked in the form upload path (row saved with empty path): make it explicit in R1a. **Next: Batch 5** (see BACKLOG).
- **Batch 3 complete** (N01 #27, N02 #28, N03 #31, N04 #33; plus #26, #29, #30, #32 plan/known-bug PRs). Summary: [`docs/reports/BATCH-3.md`](../reports/BATCH-3.md). 186 PHPUnit tests, 18 browser tests.
- **Batch 2 complete** (PRs #14–#21), plus F06 (#23), F07 (#24), B10 (#25). `master` CI green, branch-protected
  (all 9 CI jobs required, admins included, no force push). 127 PHPUnit tests, 9 browser tests.
  Summary: [`docs/reports/BATCH-2.md`](../reports/BATCH-2.md).
- **Owner decision (2026-11-16): no deployment to production until all tasks are completed and verified.**
  So the production server keeps the old code; owner actions "after deploying" are collected for one final
  deployment (see `OWNER-TODO.md`). Consequence: S00 (open admin registration) stays exploitable in production;
  the old code ignores `storage/conf/.denyregister`. Mitigation without deploying code: a web-server rule that refuses
  `POST /register`: `docs/BLOCK-REGISTRATION.md` (owner has it, not applied yet).
- **Production target (owner, 2026-11-16): the Docker stack** after the rebuild (today: Windows 11 + XAMPP, Apache,
  PHP 8.1.25, MariaDB 10.4.32, php-imap). No XAMPP/MariaDB compatibility work. The owner migrates the old DB + files;
  path = `./dev.sh real load` (MariaDB dump → MySQL 8.4 + `migrate:adopt-legacy`). Still to ask before the deployment
  guide: ~~which machine~~ **same Windows 11 PC, Docker Desktop + WSL 2** (owner, 2026-11-16).
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
| Real emails for tests | **Received 2026-11-16: 6 private e-mails in `local-data/emails/`** (owner: Claude may read them locally, never publish; see CLAUDE.md). Structural notes in `local-data/emails/NOTES-claude.md` (git-ignored). Covers: ISO-8859-7 Outlook mail with a 255-char subject, emoji (fails today, D05), winmail.dat-only read receipt (R5b), 21 MB mail with 4 attachments, long RFC 2231 file names. Long subject answered (2026-11-16): subject > 255 chars (posted by the inbox form) → MySQL 1406 on `thema`, inbox just reloads; reproduced by `test_known_bug_*subject_over_255*`; **D01 moved into Batch 4**. Owner's .eml #1 is cut at 255 by Outlook, so it doesn't reproduce it. **Also 2026-11-16:** new private e-mail shows `??` in the sender: name split inside a Greek letter across encoded words (R5a); attachment names probed: long/illegal/`../` handled, but emoji → protocol without attachment + e-mail left in inbox (D05/R5a), long Greek name without spaces → recorded but not written on Linux (F08), empty/extension-less names (F08). All pinned as `known_bug_` tests in `StoreFromEmailTest`. |
| `L01` + `L02` owner's computers | **L01 done (2026-11-16):** Fedora real copy loads after F07 (owner confirmed). Some attachment rows have no file locally: the owner's DB backup and file copy were taken on different days (attachments open fine). Expected; the next real-data set will be in sync, so a gap then is a real problem. **L02 postponed by the owner (2026-11-16):** Mac demo app works; real data on the Mac still to do. Guides: `docs/VSCODE-MACOS.md`, `docs/LOCAL-SETUP.md`. Owner replies "L02 works" → mark done in tasks.toml. |
| `S04` production `.env` | Owner (2026-11-14): the production key is randomly generated and can be recreated; Claude may handle `.env` as needed. Back up `.env` before the next `git pull` on the server. |
| Production server facts | **Answered 2026-11-16:** Windows 11, XAMPP 3.3.0 (Apache), PHP 8.1.25 ZTS x64, php-imap on, MariaDB 10.4.32. Will be replaced by the Docker stack on the same Windows 11 PC (Docker Desktop + WSL 2). |
| Knowledge base | Owner declined (2026-11-13): extra complexity for small gain. Claude may re-suggest it later if it becomes clearly useful. |
| Final deployment (all tasks done) | Owner: back up DB + `.env`; `php artisan migrate:adopt-legacy` once (no `migrations` table, F07), then `php artisan migrate`; check `users` for unknown admin accounts; `which mysqldump`, press Backup once (B10). Claude writes a deployment guide before then. |
| Open registration in production (S00) | Not deployed by owner decision. Web-server rules (Apache/nginx) given in `docs/BLOCK-REGISTRATION.md`; owner chose **not to apply them yet** (2026-11-16). Remind at the final deployment (remove the rule before `git pull`). |

## Decisions log (short)
- No changes on the production server until the migration, also not the Apache security rules; date stamps in our md files include the time, Greek time (owner, 2026-11-16).
- Private e-mails: Claude may read `local-data/emails/` locally, never publish anything from them (2026-11-16).
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
- **Owner's Fedora runs Docker Desktop** (VM file sharing): a file replaced by an atomic rename (Claude's Write/Edit tools) can stay stale *inside the containers* for minutes. Before running tests in the containers, rewrite changed files in place (`cat f > tmp && cat tmp > f`).
- Frontend (N03): `docker/dev/cloud-setup.sh` builds the app image, which now runs `npm ci`; the cloud sandbox must reach the npm registry (not yet tried there).

## Task log
| Date | Task | PR | Notes |
|---|---|---|---|
| 2026-11-16 14:26 | F08 | #37 | `App\Support\FileNames` (`fit`: ≤255 bytes/≤200 chars, char boundary, keeps extension; `clean`: `συνημμένο.<ext>`); `tools/check-attachment-names.php` (PHP 8.1, counts only) + `./dev.sh real check-names` |
| 2026-11-16 14:04 | R5a | #36 | `App\Services\Mail\{Inbox,MailCache,Mailbox,ImapMailbox,MailboxConnector}`: UID list + one fetch for missing, cache `storage/app/mail-cache/<mailbox>/<UIDVALIDITY>/<UID>.eml` (lock, expiry = still in inbox); `EncodedWords` joins split encoded words, `MailParser` also joins RFC 2231 continuations in every part header (owner's sick-leave e-mail, 2026-11-16); `MailParser::parse` everywhere; registration all-or-nothing; no `public/tmp` copies (were public!), `/viewEmailHtml` sandboxed; tests: `FakeMailbox` (`tests/Support`) |
| 2026-11-16 13:44 | D01 | #35 | `thema`, `in_arxi_ekdosis`, `in_perilipsi`, `out_perilipsi` → TEXT; `Protocol::LONG_TEXT_MAX` (16000) in form checks; e-mail registration error shows `E-XXXXXX`, never the SQL |
| 2026-11-16 13:30 | D05 | #34 | connection + all tables → utf8mb4 (`utf8mb4_general_ci`: searches/unique keys as before); legacy COMPACT tables → DYNAMIC; migration irreversible (backup); N04 review fix (confirm dialog in capture phase) merged with #33 |
| 2026-11-16 13:15 | N04 | #33 | `<x-date-input>`, `<x-pill-actions>`, `<x-file-badge>`, `<x-notice>`, toasts, `data-confirm` dialog; date rules in PHP + JS checked against one shared case list; `/dev/ui` (dev/test only) |
| 2026-11-16 12:54 | N03 | #31 | Vite 7 + Bootstrap 5.3 + Livewire 4.4; Node 22 inside the app image; `layouts/modern` + `App\Support\Navigation` (menu per role, tested); `/about` first page; `./dev.sh assets` |
| 2026-11-16 11:55 | N02 | #28 | `App\Models\*`, Laravel 11+ `bootstrap/app.php`, class-based routes in an `auth` group, `HasMiddleware`; route-middleware snapshot test (unchanged); fix: error handler crashed outside web requests |
| 2026-11-16 11:43 | N01 | #27 | mail-mime-parser 4, PHPUnit 13, `php ^8.4.1` + platform pin, watermark pinned (next commit swaps `markpdf` binaries); `StoreFromEmailTest` characterizes e-mail registration. Production → Docker (owner) |
| 2026-11-16 10:58 | B10 | #25 | Backup: mysqldump without shell, errors shown, `--no-tablespaces`, safe file names; stray bytes in `config/imapFolders.php` broke every download; `real load` reads `.zip`. Also L01 done, `master` branch protection (all CI jobs required, admins included) |
| 2026-11-16 10:20 | F07 | #24 | production backup has no `migrations` table → `migrate:adopt-legacy` records existing tables; `real load` runs it before `migrate` |
| 2026-11-16 00:37 | F06 | #23 | nginx re-resolves `app` (Docker DNS, 5 s): 502 after Docker Desktop restart on the Mac; `tests/shell/nginx-reconnect.test.sh` in CI e2e job |
| 2026-11-15 12:28 | Plan change + owner list | #22 | D05, R5a → new Batch 4 (later batches +1); `OWNER-TODO.md` |
| 2026-11-12 19:48 | F03, F01, F02, S00, B03, B08, S02, A02 | #2–#9 | see `docs/reports/BATCH-1.md` |
| 2026-11-12 19:45 | Review fixes | #10 | 9 Codex findings verified + fixed |
| 2026-11-13 09:33 | Tracker | #11 | `tasks.toml` + PERT/CPM `PROGRESS.md`, `CLAUDE.md`, this file |
| 2026-11-14 15:15 | L02 + Batch 2 report | #21 | Mac guide, RPM VS Code, switching guide, `./dev.sh doctor`, e2e in container (Mac: host.docker.internal), CI: dev.sh on macOS + arm64 image |
| 2026-11-14 15:08 | B07 | #20 | `MessageMover` (IMAP move/EXPUNGE bugs), folder lookup by path, invalid search/print filters ignored + reported, update check removed |
| 2026-11-13 15:19 | B04 | #19 | error reference codes `E-XXXXXX`, Greek error pages, details for admins only |
| 2026-11-13 15:15 | S04 | #18 | `.env` untracked, published `APP_KEY` removed |
| 2026-11-13 15:23 | F05 | #17 | `e2e/` Playwright suite, `./dev.sh e2e`, `EVIDENCE=<ID>` screenshots, CI job builds full stack |
| 2026-11-13 14:57 | L01 | #16 | `.vscode/` tasks via `flatpak-spawn --host`, `:z` mounts, shared perms, `docs/VSCODE-FEDORA.md`; waiting owner |
| 2026-11-13 14:53 | F04 | #15 | `./dev.sh real load` (port 8081, mail/IMAP sandboxed); storage/app now git-ignored + CI hygiene check (464 demo PDFs untracked) |
| 2026-11-13 14:43 | B09 | #14 | mail via `OutgoingMail`; found: emoji breaks e-mail registration (D05), IMAP move EXPUNGE error (→B07) |
| 2026-11-13 13:04 | Cloud setup | #12 | `docker/dev/cloud-setup.sh` + `./dev.sh cloud-up`; dedicated cloud environment proposed |
