# Batch 1 — summary (2026-11-12)

All 8 tasks done, each merged to `master` only after CI was green (PHPUnit on MySQL 8.4 with
PHP 8.4 and 8.5, dependency audit, Docker build). Test suite: **0 → 87 tests / 243 assertions**.

| Task | Result | PR | Report |
|---|---|---|---|
| F01 CI | GitHub Actions on every PR | [#3](https://github.com/dipelaserasmus-del/electronic_protocol/pull/3) | [F01](F01.md) |
| F03 Docker stack | `./dev.sh up`: app + MySQL 8.4 + mock mailbox + Mailpit; old Dockerfile couldn't build | [#2](https://github.com/dipelaserasmus-del/electronic_protocol/pull/2) | [F03](F03.md) |
| F02 Tests | 50 characterization tests; confirmed 4 holes + new bug B09 | [#4](https://github.com/dipelaserasmus-del/electronic_protocol/pull/4) | [F02](F02.md) |
| S00 Registration | anyone could create an **administrator** → now 403 | [#5](https://github.com/dipelaserasmus-del/electronic_protocol/pull/5) | [S00](S00.md) |
| B03 Manual number | typed number was replaced → now kept; clear message if taken | [#6](https://github.com/dipelaserasmus-del/electronic_protocol/pull/6) | [B03](B03.md) |
| B08 Numbering race | 20 simultaneous saves: 15 stored → now 20/20 (and 50/50) | [#7](https://github.com/dipelaserasmus-del/electronic_protocol/pull/7) | [B08](B08.md) |
| S02 Access control | 10 holes closed with one policy; autocomplete repaired | [#8](https://github.com/dipelaserasmus-del/electronic_protocol/pull/8) | [S02](S02.md) |
| A02 Gap reminder | banner + settings + fill-the-gap link | [#9](https://github.com/dipelaserasmus-del/electronic_protocol/pull/9) | [A02](A02.md) |

## Things you need to do
1. **Date reset (B03):** I could not reproduce it — please tell me the screen, your role and whether
   *Έλεγχοι καταχώρισης* is on.
2. **When you deploy:** run `php artisan migrate` (S00 changes a column default) and check the `users`
   table for administrator accounts you don't recognise.
3. **Try it locally:** `git pull && ./dev.sh up` → http://localhost:8080 (`admin` / `password`).

## New findings added to the backlog
- **B09 (P0):** every outgoing email (receipts, assignment notifications, "send receipt") crashes with 500 —
  first task of Batch 2.
- **D05:** tables use 3-byte `utf8`; emoji/symbols cause save errors → convert to `utf8mb4`.
- B07 inputs: update check calls GitHub on every page load (timeouts without internet); settings read with
  ~8 queries per request; non-numeric search input → 500.
- Edits overwrite the protocol's creator; edit time window enforced only in the browser (R1a).
- Autocomplete builds JavaScript with unescaped stored values (S05).

## Sandbox notes (not affecting you)
Docker Hub was rate-limited and Debian/Alpine mirrors blocked in my environment, so the app image is based
on Ubuntu 26.04 (PHP 8.5 from Ubuntu, supported to 2031). GitHub CI builds the same image from scratch.
