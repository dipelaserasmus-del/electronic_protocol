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
