# CLAUDE.md — working rules for this repository

Ηλεκτρονικό Πρωτόκολλο: Laravel 13, PHP 8.4/8.5, **MySQL only**. Being rebuilt module by module
(Livewire + Bootstrap 5) while staying usable after every merge.

**Start every session by reading [`docs/plan/HANDOFF.md`](docs/plan/HANDOFF.md)** — current state, next task,
environment set-up and open questions. Plan: [`BACKLOG.md`](docs/plan/BACKLOG.md) (what/why),
[`tasks.toml`](docs/plan/tasks.toml) (status/dependencies), [`PROGRESS.md`](docs/plan/PROGRESS.md) (generated).

## Workflow (agreed with the owner)
1. One branch per task: `task/<ID>-<slug>` from up-to-date `master`.
2. Write/adjust tests first where possible. Characterization tests named `known_bug_*` / `known_vulnerability_*`
   document current bugs; the fixing task replaces them with tests asserting correct behaviour.
3. Verify in the running app for anything user-visible: add/extend a spec in `e2e/tests/` and run
   `EVIDENCE=<ID> ./dev.sh e2e` (screenshots go to `docs/reports/img/<ID>/`; CI runs the same suite).
4. Evidence report `docs/reports/<ID>.md`: what was wrong, what changed, how verified (tests + browser), owner actions.
5. Update `docs/plan/tasks.toml` (status, `pr`) and run `python3 docs/plan/tools/pert.py`; update `HANDOFF.md`.
6. Open a PR to `master`, wait for **all CI jobs green**, read automated review comments (Codex bot): verify each,
   fix real ones (with tests), reply on the thread. Then merge. The owner allowed Claude to run the **whole PR procedure
   on its own** (commit, push, review replies, merge on green CI) without asking.
7. Each batch ends with `docs/reports/BATCH-<n>.md`.

## Hard rules
- **Real data never reaches Claude or GitHub.** Production DB dumps, attachments and private emails stay in the
  owner's git-ignored `local-data/`; only the owner runs them. Claude uses synthetic data / row-less schema dumps.
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
