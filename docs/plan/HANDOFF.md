# Hand-off log

Read this first when (re)starting work. Update it at the end of every task.

## Current state — 2026-11-13
- **Batch 1 complete** and merged (PRs #2–#10), `master` CI green. 94 tests / 260 assertions.
- **Next:** Batch 2 → `B09` (all outgoing email crashes) → `F04` → `F05` → `S04` → `B04` → `B07`.
  Status of everything: [PROGRESS.md](PROGRESS.md).
- Owner said "run batch N" to start a batch; Claude merges each PR itself when CI is green.

## Open items / waiting on the owner
| Item | Status |
|---|---|
| `B03d` date reset on save | **Pinned (on hold).** Not reproducible by Claude or the owner. Production has *Έλεγχοι καταχώρισης* (`protocolValidate`) = **NO**. Owner will report steps when it happens again. |
| Real emails for tests | Owner will provide some public-safe `.eml` files; remind before email tasks (R5a–R5d, B02 part in R5a). |
| After deploying Batch 1 | Owner: run `php artisan migrate`; check `users` for unknown admin accounts. |

## Decisions log (short)
- Module-by-module rebuild, Livewire + Bootstrap 5, MySQL only (BACKLOG "Decisions").
- Dev image: Ubuntu 26.04 + distro PHP 8.5 (sandbox can't reach Debian/Alpine mirrors; CI builds it too).
- Dev stack isolated: Compose project `electronic-protocol-dev`, containers `ep-dev-*`, volume `devdbdata`.
- Number may be chosen only by admin with `protocolValidate` OFF (same rule as the form).
- Deleting protocols: admins only. Assign: admin + Αναθέτων. Mark done: admin, Αναθέτων, d-assignee, creator.

## Resuming in a fresh Claude Code cloud session (sandbox recipe)
The cloud sandbox has quirks that do not exist on the owner's machine:
```bash
# 1. Docker daemon is not running by default
(dockerd > /tmp/dockerd.log 2>&1 &) ; sleep 8
# 2. Docker Hub is rate-limited: pull from the Google mirror and re-tag
for i in library/mysql:8.4 library/nginx:stable library/ubuntu:26.04 greenmail/standalone:2.1.3 axllent/mailpit:latest; do
  docker pull -q mirror.gcr.io/$i; done
docker tag mirror.gcr.io/library/mysql:8.4 mysql:8.4
docker tag mirror.gcr.io/library/nginx:stable nginx:stable
docker tag mirror.gcr.io/library/ubuntu:26.04 ubuntu:26.04
docker tag mirror.gcr.io/greenmail/standalone:2.1.3 greenmail/standalone:2.1.3
docker tag mirror.gcr.io/axllent/mailpit:latest axllent/mailpit:latest
# 3. apt inside builds only works with host networking (Ubuntu mirrors reachable, Debian not)
docker build --network host -t electronic-protocol-app .
# 4. Composer can't download from GitHub inside containers: install on the host (PHP 8.3 there)
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-scripts --ignore-platform-req=php
# 5. Start without rebuilding (./dev.sh up would rebuild without host networking)
cp -n .env.development.example .env.development
C="docker compose -p electronic-protocol-dev --env-file .env.development -f docker-compose.yml -f docker-compose.dev.yml"
$C up -d && $C exec -T app php artisan package:discover
$C exec -T app chown -R www-data:www-data storage bootstrap/cache public/tmp
$C exec -T app php artisan key:generate --force
$C exec -T app php artisan migrate --force --seed && $C exec -T app php artisan db:seed --force --class=DevSeeder
./dev.sh mail && ./dev.sh test
```
- Playwright: `NODE_PATH=$(npm root -g) node script.js` (Chromium preinstalled).
- Git tags can't be pushed through the sandbox proxy; milestones are recorded in `MILESTONES.md`.
- Never run `artisan db:wipe` without `--env=testing` (it would wipe the dev demo DB).

## Task log
| Date | Task | PR | Notes |
|---|---|---|---|
| 2026-11-12 | F03, F01, F02, S00, B03, B08, S02, A02 | #2–#9 | see `docs/reports/BATCH-1.md` |
| 2026-11-12 | Review fixes | #10 | 9 Codex findings verified + fixed |
| 2026-11-13 | Tracker | — | `tasks.toml` + PERT/CPM `PROGRESS.md`, `CLAUDE.md`, this file |
