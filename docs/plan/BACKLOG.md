# Backlog — electronic_protocol improvements

Status: **DRAFT v1 — pending owner review** (open questions at the bottom).
Source: owner's requirements document (Greek), plus findings from code review (marked 🔎).

Legend
- **Cat**: SEC security · BUG bug · DATA data/schema · BE backend · UI · EMAIL email/IMAP · AUTO automation/checks · INFRA
- **Pri**: P0 urgent · P1 high · P2 normal · P3 nice-to-have
- **Size**: S (< ~1h agent work) · M (one session) · L (one session, may need split)
- Each task = one branch `task/<ID>-slug` → one PR → evidence report `docs/reports/<ID>.md`

---

## Phase 0 — Foundation (prerequisite for evidence reports)

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| F01 | GitHub Actions CI: composer install, PHPUnit on SQLite **and** MySQL, PHP lint, `composer audit` | INFRA | P0 | S | — |
| F02 | Test foundation: model factories, synthetic Greek seeders (protocols, users/roles, folders, attachments, assignments). **Characterization tests** that lock current behavior: login, create/edit/copy protocol, list, search, download, roles | INFRA | P0 | L | F01 |
| F03 | Local Docker dev stack: fix Dockerfile (🔎 PHP 8.4 no longer bundles `ext-imap`, current build likely fails), dev compose with **mock IMAP/SMTP server** (e.g. GreenMail/Mailpit) loaded with synthetic emails incl. Greek charsets, winmail.dat, large attachments | INFRA | P0 | M | — |
| F04 | Local real-data loader: script that imports a production backup into the local stack from a git-ignored `local-data/` folder. Real data never committed, never read by Claude | INFRA | P0 | S | F03 |
| F05 | Evidence tooling: Playwright screenshot/E2E harness against the Docker stack, report template, `make evidence TASK=<ID>` | INFRA | P0 | M | F02, F03 |

## Phase 1 — Security & critical bugs

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| S01 | Upgrade dependencies: PHP image, Laravel patch level, `webklex/php-imap` (latest), remove `ext-imap`/c-client (unmaintained, known CVEs), JS libs. Run full suite | SEC | P0 | M | F02, F03 |
| S02 | Access control audit: reader/writer can open protocols not assigned to them by number (owner report) despite `limitProtocolAccessList`. 🔎 `download/{attachment}` has **no access check** (any logged-in user can fetch any attachment by ID). Audit all routes, add policy + tests | SEC | P0 | M | F02 |
| S03 | 🔎 Destructive actions over GET (`delprotocol`, `attach/del`, `users/del`, `deleteBackup`, `delExpired`, `delDeleted`) → CSRF-able. Convert to POST/DELETE + CSRF | SEC | P1 | M | F02 |
| S04 | 🔎 `.env` with `APP_KEY` is committed. Remove from git, keep `.env.example`, document key rotation | SEC | P1 | S | — |
| B01 | Input sanitation: `--` in summary breaks protocol registration; fuzz all form fields with special characters (`-- ' " ; % _ \ < > &`, emoji, Greek) and fix root cause | BUG | P0 | M | F02 |
| B02 | Characters replaced by `??` in email form fields: charset decoding (ISO-8859-7 / Windows-1253 / RFC2047 headers) | BUG/EMAIL | P1 | M | F03 |
| B03 | Manually entered protocol number changes on save. Keep it (or reject with a clear message if taken) | BUG | P0 | S | F02 |
| D01 | `thema`, `in_perilipsi`, `out_perilipsi` VARCHAR(255) → TEXT (migration for MySQL + SQLite, validation limits, UI) | DATA | P1 | S | F02 |
| B04 | Replace bare "500 Server Error" with descriptive error (friendly message + error reference; technical detail to admins/log) | BE | P1 | S | — |
| B05 | Email that fails to register: show specific inline error (AJAX), no list reload, email stays visibly un-registered | EMAIL | P1 | M | F03 |
| B06 | Email pagination: empty page 2/3… → redirect to page 1 | EMAIL | P2 | S | — |

## Phase 2 — Protocol form (quick wins)

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| P01 | Date fields: typing a single number = day of current month/year; `9` → `09` auto-pad instead of error | UI | P1 | S | — |
| P02 | Protocol date: calendar picker (like in_date) + prev/next/today buttons under it | UI | P2 | S | P01 |
| P03 | TAB from protocol number → day part of the date | UI | P2 | S | P02 |
| P04 | Indicator under protocol number: "reserved/taken" (see Q-2) | UI | P2 | S | — |
| P05 | Copy protocol also copies the date | BE | P2 | S | — |
| P06 | Attachments input always open on new/copy (no button click) | UI | P2 | S | — |
| P07 | Replace YES/NO dialog for existing thema / in_num with non-blocking inline notice | UI | P2 | S | — |
| P08 | Manual number exists but its thema is ΕΛΕΥΘΕΡΟ → prompt, then replace that placeholder | BE | P2 | M | B03 |
| P09 | Empty `thema` → pre-filled suggestion from selected attachment filename | UI | P3 | S | — |
| P10 | Writer role may edit protocol date when edit setting is enabled | BE | P2 | S | S02 |
| P11 | Remove green "N attachments" popup after email registration | UI | P3 | S | — |

## Phase 3 — Search

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| Q01 | Search form: year optional, prefilled with current year; Enter submits; same for "Σχετικοί αριθμοί" | UI | P1 | S | — |
| Q02 | `in_num` partial ("contains") search. 🔎 SQLite uses a different code path than MySQL, likely the cause | BUG | P1 | S | F02 |
| Q03 | Folder autocomplete: ignore accents, symbols, case (Greek-aware normalization) | BE | P2 | S | — |
| Q04 | Global Gmail-style search box in top bar on every page (see Q-3) | UI/BE | P2 | L | U02, Q02, Q03 |

## Phase 4 — UI shell

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| U01 | Icon action buttons → pills with text + margin; single non-wrapping row (overflow menu on narrow screens) | UI | P2 | M | F05 |
| U02 | Main navigation moved to a top bar on all screens (shared layout) | UI | P2 | M | F05 |
| U03 | Whole-window drag-and-drop for attachments | UI | P2 | M | P06 |
| U04 | File-type icons next to attachments | UI | P3 | S | — |
| U05 | Attachment file size (stored in DB at upload + backfill command, no runtime cost; email parts use size from MIME structure) | DATA/UI | P3 | S | D01 |

## Phase 5 — Email inbox

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| E01 | Left sidebar with inbox message list; click scrolls to message | UI | P2 | M | U02 |
| E02 | Collapsible plain-text / HTML sections, remembered preference | UI | P2 | S | — |
| E03 | Local EML cache: key by mailbox + UIDVALIDITY + UID, fetch headers only on revisit, file locking for concurrent users, cleanup/expiry policy | EMAIL | P1 | L | F03, S01 |
| E04 | winmail.dat (TNEF): extract body + inner attachments, show and register them instead of the .dat | EMAIL | P2 | L | F03 |
| E05 | Keyword suggestion from email subject/body during registration | BE | P3 | M | — |
| E06 | Auto-assignment rules: folder → user(s) + type (ενημέρωση/διεκπεραίωση), admin settings UI, applied on registration | BE | P2 | M | F02 |

## Phase 6 — Integrity checks (automation)

One shared check engine, run on list refresh + an "Έλεγχοι" page with visual flags.

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| A01 | Check engine + results page + banner at top of protocol list | AUTO | P2 | M | F02 |
| A02 | Rule: missing numbers (gaps). Banner on list refresh; dedicated last-30-days report | AUTO | P2 | S | A01 |
| A03 | Rule: protocol dates must not go backwards as numbers increase | AUTO | P2 | S | A01 |
| A04 | Rule: contains "εκκρεμότητα" (accent/case-insensitive) | AUTO | P3 | S | A01 |
| A05 | Rule: duplicates with identical content and no `sxetiko` → flagged | AUTO | P2 | M | A01 |
| A06 | Rule: no attachment and no `sxetiko` / "εκκρεμότητα" / "ΕΛΕΥΘΕΡΟ" → flagged | AUTO | P2 | S | A01 |

---

## Suggested execution order
F01 → F03 → F02 → F04 → F05 → S01 → S02 → B01 → B03 → D01 → S03 → S04 → Q02 → Q01 → B04 → B05 → B02 → B06 → P01…P11 → Q03 → A01…A06 → U02 → U01 → E02 → E01 → U03…U05 → E06 → E03 → E04 → Q04 → E05

## Open questions
See the conversation thread; answers will be recorded here.
