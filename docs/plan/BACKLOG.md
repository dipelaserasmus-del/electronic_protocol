# Backlog — electronic_protocol improvements

Status: **v2 — owner decisions recorded** (2026-11-12). Open questions at the bottom.
Source: owner's requirements document (Greek) + findings from code review (marked 🔎).

Legend
- **Cat**: SEC security · BUG bug · DATA data/schema · BE backend · UI · EMAIL email/IMAP · AUTO automation/checks · MOD modernization · INFRA
- **Pri**: P0 urgent · P1 high · P2 normal · P3 nice-to-have
- **Size**: S (< ~1h agent work) · M (one session) · L (one session, may be split further when started)

## Decisions (owner)

| Topic | Decision |
|-------|----------|
| Database | **MySQL only** is the target (multi-user). CI tests run on MySQL 8. SQLite code paths are not maintained further. |
| PHP | Latest *safe* stable PHP (in active support) that all dependencies support — verified in M01. |
| Workflow | One branch `task/<ID>-slug` per task → PR to `master` → **Claude merges when CI is green** → evidence report `docs/reports/<ID>.md`. |
| Real data | Stays on owner's machine only (git-ignored `local-data/`). Claude works with synthetic data / schema dumps without rows. |
| Errors | Clear message for everyone; technical details only for administrators. |
| Global search | Gmail-style box in top bar with filters (`από:`, `φάκελος:`, `έτος:` …); results on a **separate results page**. |
| "Δεσμευμένος" | = number already used by another protocol. |
| Subject from attachment | Filename (without extension) shown as grey placeholder; applied on save if field left empty; click to edit it as real text. |
| Keywords | Extracted from significant words of the email text (subject + body), Greek stop-words removed. |
| Auto-assignment | Admin defines per folder (Φ.) users for διεκπεραίωση (d) and/or ενημέρωση (e); any protocol added to that folder is auto-assigned. |
| Duplicates | Compare subject, incoming number, sender, attachment contents (hash). All equal → "identical"; some equal → "partially identical". |
| Collapsed sections | Remembered per browser (localStorage). |

## Ordering strategy

Modernize **before** features, but only after a safety net of tests exists:

1. **Phase 0 – Foundation**: tests, CI, Docker, evidence tooling. Without these, an upgrade can't be proven safe.
2. **Phase 1 – Critical security fixes** that are exploitable today (small, targeted).
3. **Phase 2 – Backend modernization** (PHP, Composer libs, IMAP stack, Laravel structure).
4. **Phase 3 – Server-side bugs & data** (independent of the frontend).
5. **Phase 4 – Frontend modernization** (jQuery 1.9 / Bootstrap 3 → current, build tooling). Done *before* the UI work so every UI task is built once, on the new stack.
6. **Phases 5-8 – Features**: form, search, UI shell, email, automation.

---

## Phase 0 — Foundation

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| F01 | GitHub Actions CI: composer install, PHPUnit on **MySQL 8**, PHP lint, `composer audit`, `npm audit` | INFRA | P0 | S | — |
| F02 | Test foundation: factories, synthetic Greek seeders (protocols, users/roles, folders, attachments, assignments). **Characterization tests** that lock current behavior: login, register, create/edit/copy protocol, list, search, download, role restrictions | INFRA | P0 | L | F01 |
| F03 | Local Docker dev stack: fix Dockerfile (🔎 PHP 8.4 no longer bundles `ext-imap`, so the current build very likely fails), MySQL, dev compose with **mock IMAP/SMTP server** loaded with synthetic emails (Greek charsets, winmail.dat, large attachments) | INFRA | P0 | M | — |
| F04 | Local real-data loader: imports a production MySQL dump + attachments from git-ignored `local-data/`; never committed, never read by Claude | INFRA | P0 | S | F03 |
| F05 | Evidence tooling: Playwright E2E/screenshot harness against the Docker stack, report template | INFRA | P0 | M | F02, F03 |

## Phase 1 — Critical security fixes (exploitable today)

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| S00 | 🔎 **Open admin registration**: `POST /register` is always routed and `create()` doesn't check `.denyregister`; new users get `role_id` default `1` (= Διαχειριστής). An anonymous visitor can likely create an **administrator** account. Confirm with test, then fix | SEC | **P0** | S | F02 |
| S02 | Access control: reader/writer opens protocols not assigned to them via search by number (owner report). 🔎 `download/{attachment}` has **no access check** (any logged-in user fetches any attachment by ID). Central policy for all protocol/attachment routes + tests | SEC | P0 | M | F02 |
| S03 | 🔎 Destructive actions over GET (`delprotocol`, `attach/del`, `users/del`, `deleteBackup`, `delExpired`, `delDeleted`) → CSRF. Convert to POST/DELETE with CSRF token | SEC | P1 | M | F02 |
| S04 | 🔎 `.env` with `APP_KEY` committed to git. Remove, keep `.env.example`, document key rotation for production | SEC | P1 | S | — |
| S05 | 🔎 XSS audit: ~50 unescaped `{!! !!}` outputs in Blade views (protocolList, getFindData, layout …). Escape or sanitize each | SEC | P1 | M | F02 |
| S06 | 🔎 File handling: path traversal in backup `{name}` routes, header injection via attachment filename in `Content-Disposition`, upload type/size validation | SEC | P1 | S | F02 |
| S07 | 🔎 Auth hardening: login/password-reset rate limiting, secure session cookie flags, security headers (CSP, X-Frame-Options …) | SEC | P2 | S | F02 |

## Phase 2 — Backend modernization

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| M01 | PHP: move Docker/CI to latest safe stable PHP; fix deprecations | MOD | P1 | S | F01-F03 |
| M02 | Composer: update all packages to latest supported; replace abandoned ones; drop `ext-imap`/c-client (unmaintained, known CVEs); latest `webklex/php-imap` in native socket mode | MOD/SEC | P1 | M | M01 |
| M03 | Laravel structure to current conventions: `App\Models\*`, `bootstrap/app.php` instead of Kernel, class-based routes, route groups with `auth` middleware | MOD | P2 | M | M02 |
| M04 | Split `ProtocolController` (2,800 lines) into services (numbering, search, email import, attachments). Incremental, behavior locked by F02 tests | MOD | P2 | L | M03 |
| D01 | `thema`, `in_perilipsi`, `out_perilipsi` VARCHAR(255) → TEXT (migration, validation, UI) | DATA | P1 | S | F02 |

## Phase 3 — Server-side bugs

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| B01 | Input sanitation: `--` in summary breaks registration; fuzz all fields with special chars (`-- ' " ; % _ \ < > &`, emoji, Greek), fix root cause | BUG | P0 | M | F02 |
| B03 | Manually edited protocol **number and date** are overwritten on save (always next number + today). 🔎 Cause found: setting *Ασφαλής νέος Αρ.Πρ* (`safeNewProtocolNum`) ignores the submitted number. Fix: respect manual edits (form flag), keep the auto number only when the user didn't touch it; if a manual number is taken → inline error, nothing lost. Find why the date resets too | BUG | P0 | M | F02 |
| B08 | 🔎 Numbering race: next number = `MAX()+1` with no lock/transaction. Two users saving at once → unique-key collision → **likely source of random 500s**. Allocate numbers inside a locked transaction + retry; concurrency test | BUG | P0 | M | F02 |
| B07 | Random page timeouts / 500s: structured error logging with request IDs, slow-query log and timing in the local stack, then find and fix the top causes (IMAP fetch on page load is a suspect, see E03) | BUG | P1 | M | F03, B04 |
| B02 | Letters replaced by `??` in email form fields: charset decoding (ISO-8859-7, Windows-1253, RFC 2047) | BUG/EMAIL | P1 | M | F03, M02 |
| B04 | Descriptive errors instead of bare 500: friendly message + error ID for all, details for admins | BE | P1 | S | — |
| B05 | Email that fails to register: specific inline error (AJAX), no list reload, email visibly not registered | EMAIL | P1 | M | F03 |
| B06 | Email pagination: empty page → redirect to page 1 | EMAIL | P2 | S | — |
| Q02 | `in_num` partial ("contains") search; remove broken SQLite-only branch | BUG | P1 | S | F02 |

## Phase 4 — Frontend modernization

🔎 Current: **jQuery 1.9.1** (2013, known XSS CVEs), **Bootstrap 3.3.7** (EOL, known XSS CVEs), mixed with a Bootstrap 4 build, Laravel Mix 4, Vue 2 (EOL), old datepicker/select/fileinput plugins.

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| W01 | Build tooling: Laravel Mix 4 → Vite; npm deps; remove unused Vue 2 | MOD | P1 | M | F05 |
| W02 | jQuery 1.9 → 3.x + replace/upgrade plugins (datepicker, select, fileinput, toastr) | MOD/SEC | P1 | M | W01 |
| W03 | Bootstrap 3 → 5: shared layout + **top navigation bar on all screens** (= owner's U02) | MOD/UI | P1 | M | W02 |
| W04 | Bootstrap 5: protocol form | MOD | P1 | M | W03 |
| W05 | Bootstrap 5: protocol list, search, print pages | MOD | P1 | M | W03 |
| W06 | Bootstrap 5: email inbox | MOD | P1 | M | W03 |
| W07 | Bootstrap 5: admin pages (users, folders, settings, backups, archive) | MOD | P2 | M | W03 |

## Phase 5 — Protocol form

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| P01 | Date fields: single number = day of current month/year; `9` → `09` auto-pad | UI | P1 | S | W04 |
| P02 | Protocol date: calendar picker (like in_date) + prev/next/today buttons | UI | P2 | S | P01 |
| P03 | TAB from protocol number → day part of the date | UI | P2 | S | P02 |
| P04 | Indicator under protocol number when number is already used by another protocol | UI | P2 | S | W04 |
| P05 | Copy protocol also copies the date | BE | P2 | S | — |
| P06 | Attachments input always open on new/copy | UI | P2 | S | W04 |
| P07 | Existing thema / in_num: non-blocking inline notice instead of YES/NO dialog | UI | P2 | S | W04 |
| P08 | Manual number taken by a ΕΛΕΥΘΕΡΟ placeholder → prompt, then replace it | BE | P2 | M | B03 |
| P09 | Empty `thema` → grey placeholder from attachment filename, applied on save; editable on click | UI | P3 | S | W04 |
| P10 | Writer may edit protocol date when edit setting enabled | BE | P2 | S | S02 |
| P11 | Remove green "N attachments" popup after email registration | UI | P3 | S | — |

## Phase 6 — Search

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| Q01 | Search form: year optional, prefilled with current year; Enter submits; same for "Σχετικοί αριθμοί" | UI | P1 | S | W05 |
| Q03 | Folder autocomplete ignores accents, symbols, case (Greek normalization) | BE | P2 | S | — |
| Q04a | Search query parser: free text + filters `από:`, `προς:`, `φάκελος:`, `έτος:`, `αρ:`, `θέμα:` … (with unit tests) | BE | P2 | M | Q02, Q03, S02 |
| Q04b | Global search box in top bar + separate results page (respects access control) | UI | P2 | M | Q04a, W03 |

## Phase 7 — UI & email

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| U01 | Icon action buttons → pills with text + margin, single row (overflow menu on narrow screens) | UI | P2 | M | W04-W06 |
| U03 | Whole-window drag-and-drop for attachments | UI | P2 | M | P06 |
| U04 | File-type icons next to attachments | UI | P3 | S | W05 |
| U05 | Attachment file size (stored in DB at upload + backfill command → no runtime cost) | DATA/UI | P3 | S | D01 |
| T01 | Attachment trash with retention (owner request). 🔎 Already partly there: deleting moves the file to `trash/` + soft-deletes the DB row; admin can only empty it all at once. Add: setting "keep deleted attachments for N days" + daily auto-purge (scheduler), trash page listing file, original protocol (number/year/subject, link), deleted by/when, **restore** and delete-now actions; handle attachments of deleted protocols too | DATA/BE | P1 | M | F02, S03 |
| E01 | Inbox left sidebar with message list; click scrolls to message | UI | P2 | M | W06 |
| E02 | Collapsible plain/HTML sections, remembered per browser | UI | P2 | S | W06 |
| E03 | Local EML cache (mailbox + UIDVALIDITY + UID), headers-only on revisit, locking for concurrent users, expiry | EMAIL | P1 | L | M02 |
| E04 | winmail.dat (TNEF): extract body + inner attachments; show and register those instead | EMAIL | P2 | L | F03, M02 |
| E05 | Keyword suggestions from significant words of email subject/body | BE | P3 | M | — |
| E06 | Auto-assignment rules: admin UI folder → d-users / e-users; applied to every new protocol in that folder | BE | P2 | M | F02 |

## Phase 8 — Integrity checks

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| A01 | Check engine + "Έλεγχοι" page + banner at top of protocol list (absorbs A02) | AUTO | P2 | M | F02, A02 |
| A07 | Optional: when deleting a protocol, offer "mark as ΕΛΕΥΘΕΡΟ" instead of deleting, so no gap is created (see Q-2) | AUTO | P2 | S | A02 |
| A02 | Missing-number banner (owner request, **high importance**): on the main protocol list, show at the top every number missing between the configurable **"check gaps from number"** setting and the current max of the active year (e.g. 999 and 1001 exist, 1000 doesn't). Each gap links to create/fill it. Built standalone early; A01 later reuses it. Plus last-30-days report | AUTO | **P1** | M | F02, B08 |
| A03 | Dates must not go backwards as numbers increase | AUTO | P2 | S | A01 |
| A04 | Contains "εκκρεμότητα" (accent/case-insensitive) | AUTO | P3 | S | A01 |
| A05 | Duplicates: identical / partially identical (subject, in_num, sender, attachment hash) without `sxetiko` | AUTO | P2 | M | A01, U05 |
| A06 | No attachment and none of `sxetiko` / "εκκρεμότητα" / "ΕΛΕΥΘΕΡΟ" | AUTO | P2 | S | A01 |

---

## Execution order
F01 → F03 → F02 → **S00** → B03 → B08 → S02 → A02 → F04 → F05 → S03 → S04 → S05 → S06 → S07 →
M01 → M02 → D01 → T01 → B01 → B07 → Q02 → B04 → B05 → B02 → B06 → M03 → M04 →
W01 → W02 → W03 → W04 → W05 → W06 → W07 →
P01…P11 → Q01 → Q03 → Q04a → Q04b → U01 → E02 → E01 → U03 → U04 → U05 → E06 → A01, A03…A07 → E03 → E04 → E05

## Answered questions
- **Q-1** (B03): The number and date at the top of the form can be edited, but on save the date always becomes today and the number the next free one; the user has to re-enter both and save again. → Handled in B03 (+ B08).

## Context
- Owner can supply some real-world emails (not every case, due to sensitivity; the rest synthetic) (.eml) that are safe to handle online → test fixtures in `tests/fixtures/emails/` (used by F03 mock IMAP, B02, B05, E03, E04).
- App runs on the local network only (not internet-exposed), MySQL, multiple concurrent users. Symptoms today: random page timeouts, 500 errors, functional and UI bugs.

## Open questions
- **Q-2** (A07): Is deleting a protocol ever legitimate, or should deletion always leave a ΕΛΕΥΘΕΡΟ placeholder?
- **Q-3** (A02): With yearly numbering, should the check run per year (current year only, or all years)?
