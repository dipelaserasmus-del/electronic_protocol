# Batch 2 — summary (2026-11-13)

All 7 planned tasks done, each merged to `master` only after CI was green. CI now also runs **browser tests on the
full Docker stack** (app + MySQL + mock mailbox + Mailpit) and a **data-hygiene check** (fails if real data,
`storage/app` files or `.env` files are ever committed). L01 and the added L02 are ready and wait for you to try them.
PHPUnit: **94 → 114 tests**; browser tests: **0 → 8**.

| Task | Result | PR | Report |
|---|---|---|---|
| B09 Outgoing e-mail | every e-mail (receipts, assignment notices, "send receipt") crashed with 500 → sent through one service; a mail-server failure is now a message, not a 500; 10 s timeout | [#14](https://github.com/dipelaserasmus-del/electronic_protocol/pull/14) | [B09](B09.md) |
| F04 Real-data copy | `./dev.sh real load`: your backup in a separate local copy on port 8081; e-mail and mailbox sandboxed; prints counts only | [#15](https://github.com/dipelaserasmus-del/electronic_protocol/pull/15) | [F04](F04.md) |
| L01 Your VS Code | tasks run on the host through `flatpak-spawn --host`; SELinux labels on all mounts; guide for Fedora | [#16](https://github.com/dipelaserasmus-del/electronic_protocol/pull/16) | [L01](L01.md) |
| F05 Browser tests | Playwright suite `./dev.sh e2e`; `EVIDENCE=<ID>` saves screenshots for reports; runs in CI | [#17](https://github.com/dipelaserasmus-del/electronic_protocol/pull/17) | [F05](F05.md) |
| S04 Secrets | `.env` and the published `APP_KEY` removed from the repository | [#18](https://github.com/dipelaserasmus-del/electronic_protocol/pull/18) | [S04](S04.md) |
| B04 Error pages | every unexpected error gets a code `E-XXXXXX` (also in the log); Greek pages; details only for administrators | [#19](https://github.com/dipelaserasmus-del/electronic_protocol/pull/19) | [B04](B04.md) |
| L02 Two computers (added) | Mac guide, RPM VS Code on Fedora, switching between them; `./dev.sh doctor`; CI checks `dev.sh` on macOS and builds the image on arm64 | this PR | [L02](L02.md) |
| B07 Timeouts / 500s | 7 causes fixed (e-mail registration error, folder lookup, unprotected move, search/print inputs, update check to a foreign GitHub on login) | [#20](https://github.com/dipelaserasmus-del/electronic_protocol/pull/20) | [B07](B07.md) |

## Things you need to do
1. **L01/L02:** on Fedora follow [VSCODE-FEDORA.md](../VSCODE-FEDORA.md) (move to the RPM VS Code); on the Mac
   [VSCODE-MACOS.md](../VSCODE-MACOS.md); then [LOCAL-SETUP.md](../LOCAL-SETUP.md). Reply "L01/L02 works" or send
   the output of the task "Check: set-up status".
2. **Before the next `git pull` on the production server (S04):** copy your `.env` somewhere safe first —
   it is no longer in the repository. You said your key is randomly generated and can be recreated: if you ever
   want a new one, follow the steps in [S04](S04.md) (`key:generate --force`, then `config:cache`).
3. **Real-data copy (optional):** put a backup in `local-data/db/` and run `./dev.sh real load`
   ([guide](../LOCAL-REAL-DATA.md)). Report only "works" / error messages.
4. **E-mails for tests:** before Batch 7 (e-mail tasks) I'll ask for a few public-safe `.eml` files.

## Recommendation: move two tasks earlier
- **R5a (e-mail cache):** the inbox downloads every e-mail on the page in full on every visit — 1.8 s with 9
  small test e-mails, tens of seconds with a real mailbox. This is the most likely remaining cause of the
  "random" timeouts.
- **D05 (utf8mb4):** e-mails with emoji in the subject cannot be registered.

Both could run right after Batch 3. Say "move R5a and D05 earlier" and I'll update the plan.

## Automated review (Codex) findings this batch
All verified against the code; the valid ones were fixed with tests before merging:

| PR | Finding | Fix |
|---|---|---|
| F04 | import failure was hidden (the script continued) | stops with `IMPORT FAILED` |
| F04 | `real up` didn't install PHP dependencies on a fresh checkout | added |
| L01 | real-data copy shared `public/tmp` and file permissions with the demo stack | own temp folder, shared permissions |
| S04 | after a new `APP_KEY` the cached config still held the old key | `config:cache` step added to the procedure |
| B07 | a printout with an invalid filter silently covered a wider range | the printout now states which criteria were ignored (+4 tests) |

## Caught by our own checks before reaching you
- The browser tests caught a change that would have broken **login** (B07) — fixed before the PR.
- The browser inbox test depended on the order of the test e-mails, which differs on GitHub — made order-independent.
- 464 demo PDFs and a test e-mail had been committed in Batch 1 → removed; the CI hygiene check prevents a repeat.

## Process change
You allowed me to complete the whole pull-request procedure on my own: commit, push, answer reviews, and merge on
green CI. It's recorded in `CLAUDE.md`.

## Next: Batch 3 — platform
N01 PHP/Composer upgrade (drops the old `imap` extension) → N02 Laravel structure → N03 Vite + Bootstrap 5 +
Livewire with the top navigation → N04 shared UI components.
