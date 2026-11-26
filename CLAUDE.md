# CLAUDE.md — working rules for this repository

Ηλεκτρονικό Πρωτόκολλο: Laravel 13, PHP 8.4/8.5, **MySQL only**. Being rebuilt module by module
(Livewire + Bootstrap 5) while staying usable after every merge.

**Start every session by reading [`docs/plan/HANDOFF.md`](docs/plan/HANDOFF.md)** — current state, next task,
environment set-up and open questions. Plan: [`BACKLOG.md`](docs/plan/BACKLOG.md) (what/why),
[`tasks.toml`](docs/plan/tasks.toml) (status/dependencies), [`PROGRESS.md`](docs/plan/PROGRESS.md) (generated).
Feature guides (how a feature works, the rules its code keeps, what to check before changing it): read the one
for the feature you touch and update it in the same PR, e.g. [`docs/RELATED-NUMBERS.md`](docs/RELATED-NUMBERS.md).

## Workflow (agreed with the owner)
1. One branch per task: `task/<ID>-<slug>` from up-to-date `master`.
2. Write/adjust tests first where possible. Characterization tests named `known_bug_*` / `known_vulnerability_*`
   document current bugs; the fixing task replaces them with tests asserting correct behaviour.
3. Verify in the running app for anything user-visible: add/extend a spec in `e2e/tests/` and run
   `EVIDENCE=<ID> ./dev.sh e2e` (screenshots go to `docs/reports/img/<ID>/`; CI runs the same suite).
4. Evidence report `docs/reports/<ID>.md`: what was wrong, what changed, how verified (tests + browser), owner actions.
5. **In every task's own PR** (owner, 2026-11-24; also for stacked PRs — never collected into one docs PR at the end):
   update `docs/plan/tasks.toml` (status, `pr`), run `python3 docs/plan/tools/pert.py` (PROGRESS.md), update
   `HANDOFF.md` (state + task log) and `OWNER-TODO.md` (its try-it step, questions, owner actions, `Last update:`), so
   `master` is current after each merge.
6. Open a PR to `master`, wait for **all CI jobs green**, read automated review comments (Codex bot): verify each,
   fix real ones (with tests), reply on the thread. Then merge. The owner allowed Claude to run the **whole PR procedure
   on its own** (commit, push, review replies, merge on green CI) without asking.
7. Each batch ends with `docs/reports/BATCH-<n>.md` and an updated [`OWNER-TODO.md`](OWNER-TODO.md) (the owner's list:
   next suggested prompt, open questions, owner actions — keep it short).
8. Date stamps in the md files for the owner and Claude (`Last update:`, `Current state —`, batch summaries, the
   HANDOFF task log) include the time, Greek time: `2026-11-16 14:26` (owner, 2026-11-16).
9. No changes on the **production server** until the migration to the new version (owner, 2026-11-16): no rules,
   scripts or checks there; put such steps in the final-deployment guide instead.

## Hard rules
- **Real data never reaches Claude or GitHub.** Production DB dumps, attachments and private emails stay in the
  owner's git-ignored `local-data/`; only the owner runs them. Claude uses synthetic data / row-less schema dumps.
  **Exception (owner, 2026-11-16):** Claude may read the e-mails in `local-data/emails/` locally (private, not
  secret), but nothing from them (text, names, addresses, attachments, screenshots) goes into commits, PRs, fixtures or
  reports; committed tests copy only their structure. The real database stays off-limits (not even counts).
- The repository is **public**: commit only invented test data and owner-approved public emails.
- Never weaken or skip a test to get CI green. Never force-push `master`.
- Do not put model names/IDs in commits, PRs or code.

## Commands
```bash
./dev.sh up        # dev stack: http://localhost:8080 (admin/password), Mailpit :8025
./dev.sh cloud-up  # same, inside a Claude Code cloud sandbox (see HANDOFF.md)
./dev.sh test      # PHPUnit on MySQL (fresh `testing` DB)
./dev.sh e2e       # Playwright browser tests (EVIDENCE=<ID> saves screenshots to docs/reports/img/<ID>/)
./dev.sh doctor    # set-up status, no data (owner can send it)
sh tests/shell/dev-sh.test.sh   # dev.sh checks (fake docker; CI runs it on macOS bash 3.2 too)
python3 docs/plan/tools/pert.py   # regenerate PROGRESS.md
```
