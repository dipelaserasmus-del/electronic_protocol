# Batch 3 — summary (2026-11-16 13:01)

All 4 planned tasks done (plus B10, which you added), each merged to `master` only after all CI jobs were green and
the automated review had no open findings. Nothing changes yet for the people who use the app every day: this batch
prepares the ground for the rebuilt screens. PHPUnit: **127 → 186 tests**; browser tests: **9 → 18**.

| Task | Result | PR | Report |
|---|---|---|---|
| B10 Backup (added) | the Backup button made empty files; now complete dumps, errors shown | [#25](https://github.com/dipelaserasmus-del/electronic_protocol/pull/25) | [B10](B10.md) |
| N01 Packages | e-mail library v4 (with a new test for registering an e-mail), PHPUnit 13, PHP 8.4+; the PDF-stamp library pinned (its update swaps the programs it runs on your PDFs) | [#27](https://github.com/dipelaserasmus-del/electronic_protocol/pull/27) | [N01](N01.md) |
| N02 Code structure | current Laravel layout; every page except login/registration now requires login in one place; a test records which roles may open each of the 64 pages, and it was identical before and after. Fixed: errors outside web pages (commands) were lost | [#28](https://github.com/dipelaserasmus-del/electronic_protocol/pull/28) | [N02](N02.md) |
| N03 New frontend | Bootstrap 5 + Livewire + Vite; new layout with the **menu at the top** (same items per role as today), first used by *Περί…*; the frontend is built inside Docker (no Node on your PC or server) | [#31](https://github.com/dipelaserasmus-del/electronic_protocol/pull/31) | [N03](N03.md) |
| N04 Shared components | date field (`9` → full date, ◀ ▶, Σήμερα, calendar), pill buttons with ⋯ on phones, file badges, inline notices, toasts, confirm dialog; try them at `/dev/ui` | [#33](https://github.com/dipelaserasmus-del/electronic_protocol/pull/33) | [N04](N04.md) |

## Decisions you made this batch
- **Production moves to Docker** after the rebuild, on the same Windows 11 PC (Docker Desktop). XAMPP (PHP 8.1,
  MariaDB 10.4) is replaced; you migrate the database and files. No work spent on XAMPP compatibility.
- **No deployment until everything is done** (from earlier today).
- Your **private e-mails** may be read by me locally, never published ([CLAUDE.md](../../CLAUDE.md)).

## What your e-mails showed (all reproduced with invented data as `known_bug_` tests)
| Problem | Today | Fixed in |
|---|---|---|
| Subject over 255 characters | not registered, the inbox just reloads, the error shows raw database text | D01 (Batch 4, moved earlier) |
| Emoji in the e-mail | not registered | D05 (Batch 4) |
| Emoji in an attachment name | protocol created **without** the attachment, e-mail stays in the inbox, a retry duplicates it | D05 + R5a (Batch 4) |
| Sender name with `??` | some mail programs split a Greek letter in two; both decoders show `??` | R5a (Batch 4) |
| Long Greek attachment name (no spaces) | after the move to Docker: listed but **not saved**, reported as success | F08 (Batch 4, new) |
| Attachment without a usable name | saved with an empty name / without extension | F08 |

## Automated review (Codex) findings this batch
- N03: the production setup in the README would have shown the new pages without styles and scripts (the source
  folder hides the files built into the image). Fixed: the app container builds them at start when they changed.
  Verified with a fresh checkout. Other PRs: no findings.
- Also fixed: the plan tool could pick a different "critical path" on each run when two were equally long, so CI
  disagreed with local runs at random.

## Things you need to do
Nothing for Batch 4 to start. Your list: [OWNER-TODO.md](../../OWNER-TODO.md).
