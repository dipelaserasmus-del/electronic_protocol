# Milestones

## M0 — Planning complete (2026-11-12)
Tag: `milestone-M0-planning`

Agreed in the planning conversation:
- Backlog v3 (`docs/plan/BACKLOG.md`): module-by-module rebuild, Laravel + Livewire + Bootstrap 5, MySQL only.
- Workflow: one branch `task/<ID>-slug` per task → PR to `master` → Claude merges when CI is green → evidence report in `docs/reports/`.
- **Data policy**
  - Production database and files stay on the owner's machine (git-ignored `local-data/`), used only offline by the owner through the local Docker stack (task F04).
  - Claude never reads, uploads or commits production data. Claude works with synthetic data and, if needed, schema dumps without rows.
  - Emails: only owner-approved, public-safe `.eml` files are committed (repo is a public fork). Sensitive emails stay in `local-data/emails/`.
- Next: Batch 1 = F01 → F03 → F02 → S00 → B03 → B08 → S02 → A02.

## M1 — Rebuild feature-complete, before Batch 10 (2026-11-28 00:34)
Tag: `milestone-M1-batch-9.7` (on `master` after PR #166 and this milestone's PR). The M0 tag above is pushed
at the same time (it could not be pushed from the cloud sandbox in 2026-11).

Where the project stands:
- **148 of 161 tasks done** (Batches 1–9, 9.5, 9.6, 9.7; PRs #2–#166). Every module of the old application has its
  new Livewire + Bootstrap 5 screen: protocol form, list, search, attachments (preview, stamp, trash), e-mail inbox,
  administration (users, folders, settings, logs, backup), printing. Summaries: `docs/reports/BATCH-*.md`.
- **Platform:** Laravel 13, PHP 8.5 (Ubuntu 26.04 image), MySQL only, utf8mb4; `composer audit` clean
  (versions: `docs/reports/VERSIONS.md`).
- **Tests:** 722 PHPUnit tests on MySQL, 148 Playwright browser tests in 33 specs; CI runs both (plus `dev.sh` on
  Linux and macOS).
- **Feature guides:** `docs/SETTINGS.md`, `docs/RELATED-NUMBERS.md`, `docs/FOLDERS.md`.
- **Still to do (Batch 10):** A01, A03–A06 (integrity checks), X01–X02 (audit log), Z01 (remove the old code),
  Z02 (auth hardening), Z03 (final security review), V02 (versions report), then the deployment guide and Z04
  (end-user manual, details from the owner). B03d stays pinned until the owner can reproduce it.
- The old code (jQuery / Bootstrap 3 / Vue 2 pages) is still in the tree until Z01; production still runs the old
  version and is untouched until the migration.
