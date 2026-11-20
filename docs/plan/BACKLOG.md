# Backlog — electronic_protocol rebuild

Status: **v3 — module-by-module rebuild plan, approved approach** (2026-11-12).

📊 **Live progress (PERT/CPM, diagrams): [PROGRESS.md](PROGRESS.md)** · status/dependencies are kept in [tasks.toml](tasks.toml) · hand-off notes: [HANDOFF.md](HANDOFF.md)
Source: owner's requirements document (Greek) + code-review findings (🔎). Every owner request is mapped in the **Traceability** table at the end.

Legend
- **Cat**: SEC security · BUG · DATA schema/data · BE backend · UI · EMAIL · AUTO checks · AUDIT · MOD modernization · INFRA
- **Pri**: P0 urgent · P1 high · P2 normal · P3 low
- **Size**: S (< ~1h agent work) · M (one session) · L (one session, split further if needed when started)
- 👤 = needs owner action before/after (e.g. provide emails, run a data migration on real data locally)

## Decisions (owner)

| Topic | Decision |
|-------|----------|
| Approach | **Module-by-module rebuild inside the running app.** Old code for a module is deleted once the new module passes tests. App stays usable after every merge. Upstream (g-theodoroy) changes will no longer be merged. |
| Stack | Laravel (current) · PHP latest safe stable · **MySQL only** · **Blade + Livewire + Alpine + Bootstrap 5** · Vite |
| Workflow | Branch `task/<ID>-slug` → PR to `master` → **Claude merges when CI is green** → evidence report `docs/reports/<ID>.md` |
| Production | (2026-11-16) After the rebuild production runs the **Docker stack** (same image + MySQL 8.4 as dev/CI), replacing XAMPP (PHP 8.1, MariaDB 10.4), on the same Windows 11 PC via Docker Desktop + WSL 2. Owner migrates the old DB and files into it; no XAMPP compatibility work. |
| Real data | Stays on owner's machine (git-ignored `local-data/`). Claude uses synthetic data / row-less schema dumps. |
| Emails | Repo is a public fork (can't be private). Commit only public-safe `.eml`; sensitive ones stay in owner's `local-data/emails/`. 📧 **Remind owner before every email task.** |
| Errors | Clear message for everyone; technical details only for administrators. |
| Global search | Gmail-style box in top bar with filters (`από:`, `φάκελος:`, `έτος:` …); results on a **separate page**. |
| "Δεσμευμένος" | Number already used by another protocol. |
| Subject from attachment | Filename (no extension) as grey placeholder; applied on save if left empty; click to edit. |
| Keywords | Significant words of email subject + body (Greek stop-words removed). |
| Auto-assignment | Admin maps folder (Φ.) → users for διεκπεραίωση and/or ενημέρωση; every protocol added to that folder is auto-assigned. |
| Duplicates | Subject, incoming number, sender, attachment contents: all equal → "identical", some equal → "partially identical". |
| Gaps | Reminder banner only, no auto-fill. Scope: "check from number" + "last N months" (default 6), per year. Saving onto an existing number keeps failing (correct). Deleting a protocol is only done to replace it. |
| Collapsed sections | Remembered per browser. |
| Audit log | Required, low priority: who did what and when. |
| Folders | (2026-11-19) The owner's list is the folders glossary ([`docs/FOLDERS.md`](../FOLDERS.md)). Heading + text in one description. Φ.13 kept, «(Καταργήθηκε)». **Φ.43 is merged into Φ.46 — also in every later data migration / the move to the new version** (`merges` in `folders.json`). |

## Plan overview

| Phase | Purpose |
|-------|---------|
| 0 Foundation | Tests, CI, Docker, evidence tooling — the safety net |
| 1 Urgent patches | Exploitable / data-corrupting issues, fixed in current code now |
| 2 Platform | Upgraded backend, new frontend stack, shared layout & components |
| 3 Data model | Schema fixes, each verified 👤 on real data locally before merge |
| 4 Module rebuilds | Each module rewritten with its requested features built in |
| 5 Finish | Integrity checks, audit log, removal of all legacy code, final security review |

---

## Phase 0 — Foundation

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| F01 | GitHub Actions CI: composer install, PHPUnit on MySQL 8, PHP lint, `composer audit`, `npm audit` | INFRA | P0 | S | — |
| F03 | Local Docker dev stack: fix Dockerfile (🔎 PHP 8.4 no longer bundles `ext-imap`; current build very likely fails), MySQL, mock IMAP/SMTP server with synthetic emails. 📧 | INFRA | P0 | M | — |
| F02 | Test foundation: factories, synthetic Greek seeders, **characterization tests** of current behavior (login, register, create/edit/copy, list, search, download, roles, settings). These become the spec of legacy rules | INFRA | P0 | L | F01 |
| F04 | Real-data loader for owner: import production MySQL dump + attachments from `local-data/` into the local stack 👤 | INFRA | P0 | S | F03 |
| L01 | 👤 Owner's computer: VS Code + Docker, `./dev.sh up`, load the real DB/attachments with the F04 loader, open the app locally. Claude prepares `.vscode/` tasks + a step-by-step guide; the owner runs it (real data never leaves the owner's machine). Ask the owner's OS at start | INFRA | P1 | S | F04 |
| L02 | 👤 Owner's second computer (macOS) + Fedora VS Code from RPM; switch between them at any time (`docs/LOCAL-SETUP.md`) | INFRA | P1 | S | L01 |
| F05 | Evidence tooling: Playwright E2E + screenshots against Docker stack, report template | INFRA | P0 | M | F02, F03 |
| F08 | 🔎 (from the owner's e-mails, 2026-11-16) Attachment file names must fit **255 bytes** on Linux (Docker production, WSL 2): today names are cut at 120 *characters*, but Greek letters take 2 bytes, so a long Greek name + the `num-date_key_` prefix fails to save. Cut by bytes on a character boundary for new files; `./dev.sh real check-names` counts existing files whose names are too long, before the owner copies files from Windows (NTFS allows them). Confirmed 2026-11-16: the attachment is **recorded but the file is not written** and registration reports success (`test_known_bug_long_greek_attachment_name_*`); also names of only illegal characters lose the extension and nameless attachments get an empty name (`test_known_bug_attachment_names_without_usable_characters`) | BUG/INFRA | P1 | S | F04 |
| F06 | nginx looks up the `app` container through Docker DNS while running, so a recreated app container (new IP, e.g. after Docker Desktop restarts) no longer causes 502 until nginx is restarted. Found by the owner on the Mac | INFRA | P1 | S | F03 |

## Phase 1 — Urgent patches (current code)

Written as small reusable services so the rebuilt modules reuse them instead of redoing them.

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| S00 | 🔎 **Open admin registration**: `POST /register` always routed, no `.denyregister` check, default `role_id = 1` (Διαχειριστής). Confirm with test, fix | SEC | **P0** | S | F02 |
| B03 | Manually edited protocol number **and date** overwritten on save. 🔎 `safeNewProtocolNum` ignores submitted number. Respect manual edits; taken number → inline error, nothing lost; find date reset cause | BUG | P0 | M | F02 |
| B08 | 🔎 Numbering race (`MAX()+1`, no lock) → duplicate-key 500s with concurrent users. Locked transaction + retry; concurrency test | BUG | P0 | M | F02 |
| S02 | Access control: users find protocols not assigned to them by number (owner report); 🔎 `download/{attachment}` has no check. Central Laravel Policy for protocols/attachments + tests | SEC | P0 | M | F02 |
| A02 | Missing-number banner on the main list (owner: **important**). Settings "check from number" + "last N months". Gap service reused later by R2/A01 | AUTO | P1 | M | B08 |
| S04 | 🔎 `.env` with `APP_KEY` committed. Remove from git, `.env.example`, rotation instructions 👤 | SEC | P1 | S | — |
| B04 | Descriptive error pages instead of bare 500: friendly message + error ID; details for admins | BE | P1 | S | — |
| B09 | 🔎 (from F02, confirmed by test) **All outgoing email crashes (HTTP 500)**: receipts, "send receipt" button, assignment notifications use the SwiftMailer API (`setBody`, `Mail::failures`) removed in Laravel 9. Protocol is saved, user sees an error. Port to Laravel Mail/Mailables | BUG | **P0** | S | F02 |
| B07 | Diagnose random timeouts/500s: request IDs, structured logs, slow-query log; fix top causes that aren't solved by later modules 📧 | BUG | P1 | M | F03, B04 |
| B10 | 🔎 (owner, 2026-11-16) **Ρυθμίσεις → Backup creates empty files and says "success"**: `mysqldump | gzip` checked only gzip's exit code; no `--no-tablespaces` (PROCESS privilege), host fixed to localhost, password unquoted in a shell command, `mysqldumpPath` run by the shell, wrong date (`m` for minutes). Also download/delete accept any name (part of R6c). Until deployed: export with phpMyAdmin | BUG/SEC | P1 | S | — |

## Phase 2 — Platform

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| N01 | PHP latest safe stable in Docker/CI; update all Composer packages; replace abandoned ones; drop `ext-imap`/c-client; `webklex/php-imap` latest (native sockets) | MOD/SEC | P1 | M | F01–F03 |
| N02 | Laravel structure: `App\Models\*`, `bootstrap/app.php`, class-based routes, route groups with `auth`, policies registered | MOD | P1 | M | N01 |
| N03 | Frontend toolchain: Vite, Bootstrap 5, Livewire, Alpine. New app layout with **top navigation bar** and global-search slot, used by all rebuilt pages (legacy pages keep old layout until rebuilt) | MOD/UI | P1 | M | N02, F05 |
| N04 | Shared UI components: **pill action buttons** (text + margin, single row, overflow menu on narrow screens); **date input** (single number → day of current month/year, `9`→`09`, calendar picker, prev/next/today buttons); file-type icon + size badge; inline notices; toasts; confirm dialog | UI | P1 | M | N03 |

## Phase 3 — Data model (each 👤 verified on real data locally via F04 before merge)

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| D01 | `thema`, `in_perilipsi`, `out_perilipsi` VARCHAR(255) → TEXT. 🔎 Owner (2026-11-16): an e-mail with a subject over 255 characters is not registered (MySQL 1406 "Data too long for column 'thema'"), the inbox just reloads; the error shown is the raw SQL with the e-mail's data. Replace `test_known_bug_*subject_over_255*` in `StoreFromEmailTest`; also check `in_arxi_ekdosis` (long sender names) and show a clear message, never the SQL. Moved to Batch 4 | DATA/BUG | P1 | S | F02 |
| D02 | 🔎 Assignments stored as text `"d14,e10,"` searched with `LIKE` → proper `protocol_assignments` table (user, type d/e, done date); backfill; enables E06 and fast, correct filtering | DATA | P1 | M | F04 |
| D03 | Attachment metadata: size, SHA-256 hash, `deleted_by`; backfill command (for U05, A05, T01) | DATA | P2 | S | F04 |
| D05 | 🔎 (from F03) Convert tables from `utf8` (3-byte) to `utf8mb4` so emoji/symbols can be stored without errors 👤. Real examples from the owner: an e-mail with an emoji fails to register; an emoji in an attachment name leaves a protocol without that attachment | DATA | P1 | S | F04 |
| D07 | (owner, 2026-11-19) **Folder glossary**: the owner's official folder list with full descriptions (`database/data/folders.json`, `docs/FOLDERS.md`) applied with `php artisan folders:glossary [--apply]` (only descriptions; retention/remarks kept; Φ.37–Φ.42 untouched). **Merge Φ.43 → Φ.46**: protocols, attachment files, rules; Φ.43 removed. 👤 runs it on the real copy, then at deployment | DATA | P2 | S | R6b |
| D04 | 🔎 Dates stored as integers `Ymd` → real DATE columns; backfill | DATA | P2 | M | F04 |

## Phase 4 — Module rebuilds

Each module: new services + Livewire screens on N03/N04, POST/DELETE + CSRF for all actions (🔎 S03), escaped output (🔎 S05), policy checks (S02), tests, legacy code for that module deleted.

### R1 Protocol entry
| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| R1a | Protocol service: numbering (B03/B08 services), validation, **input sanitation** (`--` bug + fuzz `' " ; % _ \ < > &`, emoji, Greek), copy **incl. date**, writer may edit date when setting enabled, replace ΕΛΕΥΘΕΡΟ placeholder with confirmation; write assignments to `protocol_assignments` and drop the text column `diekperaiosi` (D02 keeps it in sync until then; also receipts/prints still read the text) | BE/BUG | P0 | M | D01, D02 |
| R1b | Protocol form screen: number with "**already used**" indicator, TAB → day field, date components, inline non-blocking notices for existing subject / incoming number (replaces YES/NO dialog), pill actions | UI | P1 | M | R1a, N04 |
| R1c | Attachments panel: always open on new/copy, **whole-window drag-and-drop**, file icons + size, subject placeholder from filename | UI | P1 | M | R1b, D03 |
| R1d | Auto-assignment on save using folder rules (rules UI in R6b) | BE | P2 | S | R1a, R6b |

### R2 Protocol list
| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| R2 | Main list rebuilt: filters (mine / assigned / pending / done), sorting, sticky header, pill actions, gap banner (A02) integrated | UI | P1 | M | N04, D02 |

### R3 Search
| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| R3a | Advanced search screen: year optional prefilled with current year, Enter submits (also "Σχετικοί αριθμοί"), **partial match** on incoming number and text fields, accent/case/symbol-insensitive folder autocomplete. 👤 Owner (2026-11-18): folder choice **«Χωρίς φάκελο»** (protocols on hold / placeholders) and a **pending check** for a date range: protocols without a folder **or** with ΕΚΚΡΕΜΟΤΗΤΑ in the subject (added as a suffix while pending, removed later; accent/case-insensitive). An empty result must say clearly that nothing is pending in that range | UI/BE | P1 | M | N04, D02 |
| R3b | Gmail-style query parser: free text + `από:` `προς:` `φάκελος:` `έτος:` `αρ:` `θέμα:` … (unit tests) | BE | P2 | M | R3a |
| R3c | Global search box in top bar + **separate results page**, live suggestions, access-controlled | UI | P2 | M | R3b |

### R4 Attachments & trash
| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| R4a | Secure file handling: policy-checked download, safe `Content-Disposition`, upload type/size validation (🔎 S06) | SEC | P1 | S | S02 |
| R4b | **Trash with retention**: setting "keep N days", daily auto-purge (scheduler), trash page with original protocol (number/year/subject, link), deleted by/when, restore / delete now; covers attachments of deleted protocols. 🔎 (D03) the trash button moves the file to `trash/` but keeps the `arxeio/` path in `savedPath` | DATA/BE | P1 | M | R4a, D03 |

### R5 Email
📧 Remind owner to provide emails before starting.
| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| R5a | Email service: IMAP via new library, **EML cache** (mailbox + UIDVALIDITY + UID, headers-only on revisit, locking for concurrent users, expiry), correct charset decoding (**`??` bug**: ISO-8859-7, Windows-1253, RFC 2047). 🔎 Owner's example (2026-11-16): Horde webmail splits a sender name *inside* a 2-byte Greek letter across encoded words; both decoders (mail-mime-parser and `HeaderDecoder`) then show `??`; join adjacent encoded words before decoding (`test_known_bug_sender_name_split_inside_a_greek_letter_*`). Registration must be all-or-nothing: an attachment that fails must not leave a protocol behind with the e-mail still in the inbox (the emoji example is fixed by D05; R5a needs its own test with an attachment that fails to save) | EMAIL | P1 | L | N01 |
| R5b | **winmail.dat** (TNEF): extract body + inner attachments, show/register those instead | EMAIL | P2 | L | R5a |
| R5c | Inbox screen: **left sidebar** message list (click scrolls to message), **collapsible plain/HTML sections** remembered per browser, empty page → back to page 1, file icons + sizes from MIME structure, pill actions | UI | P1 | M | R5a, N04 |
| R5d | Register-from-email flow: AJAX save with **specific inline error** (no reload, email stays visible as not registered), no green attachment-count popup, inline notices, subject placeholder, **keyword suggestions** from significant words | EMAIL/UI | P1 | M | R5c, R1a |

### R6 Administration
| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| R6a | Users & roles screens; registration only for first admin (S00 kept) | UI/SEC | P2 | M | N04 |
| R6b | Folders (Φ.) screen + **auto-assignment rules** (folder → d-users / e-users) | UI/BE | P2 | M | N04, D02 |
| R6c | Settings (incl. gap-check and trash-retention settings), backups (🔎 path traversal fix), archive/expired screens | UI/SEC | P2 | M | N04, R4b |

### R7 Printing & exports
| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| R7 | Protocol book print, receipts (+ email receipt), XLS export, attachment-clearance print: rebuilt on new layout, output checked against legacy output | UI | P2 | M | R2 |

## Phase 5 — Finish

| ID | Task | Cat | Pri | Size | Depends |
|----|------|-----|-----|------|---------|
| A01 | Integrity check engine + "Έλεγχοι" page + list banner (absorbs A02 gap rule, adds last-30-days report) | AUTO | P2 | M | R2 |
| A03 | Rule: dates must not go backwards as numbers increase | AUTO | P2 | S | A01 |
| A04 | Rule: contains "εκκρεμότητα" (accent/case-insensitive) | AUTO | P3 | S | A01 |
| A05 | Rule: identical / partially identical protocols (subject, in_num, sender, attachment hash) without `sxetiko` | AUTO | P2 | M | A01, D03 |
| A06 | Rule: no attachment and none of `sxetiko` / "εκκρεμότητα" / "ΕΛΕΥΘΕΡΟ" / no folder. 👤 Owner (2026-11-18): a protocol without a folder is on hold or a placeholder until the actual content reaches the department: not an error here; A04 may list them as pending | AUTO | P2 | S | A01 |
| X01 | **Audit log — recording**: append-only `audit_log` table (user, action, entity, old→new values, IP, time); automatic capture of create/update/delete on protocols, attachments, assignments, users, folders, settings; logins / logouts / failed logins; attachment downloads; email registrations; retention setting | AUDIT | P3 | M | N02 |
| X02 | **Audit log — viewing**: admin page with filters (user, action, date, protocol), per-protocol "History" tab, CSV export; not editable from UI | AUDIT | P3 | M | X01 |
| Z01 | Remove legacy code: old layout, jQuery 1.9, Bootstrap 3, old plugins/controllers/views; Vue 2; D04 transitional cast `LegacyYmdDate` → `date` | MOD | P2 | M | R1–R7 |
| Z02 | Auth hardening: rate limiting login/reset, secure cookies, security headers/CSP (🔎 S07) | SEC | P2 | S | Z01 |
| Z03 | Final security review of whole app + dependency audit; final evidence report | SEC | P1 | M | Z02 |

---

## Execution order

1. **Batch 1:** F01 → F03 → F02 → S00 → B03 → B08 → S02 → A02
2. **Batch 2:** B09 → F04 → L01 👤 → L02 👤 → F05 → S04 → B04 → B07 → F06 (added 2026-11-15)
3. **Batch 3:** N01 → N02 → N03 → N04
4. **Batch 4** (moved earlier by the owner, 2026-11-15): D05 👤 → D01 👤 (moved from Batch 5: long subjects fail, 2026-11-16) → R5a 📧 → F08 (added 2026-11-16) — emoji e-mails can't be registered; the
   inbox downloads every e-mail in full on each visit (main remaining timeout cause, see B07). R5a plugs its cache
   into the **current** inbox page; the new inbox screen stays R5c.
5. **Batch 5:** D02 👤 → D03 👤 → D04 👤
6. **Batch 6:** R1a → R1b → R1c → R2 → R4a → R4b
7. **Batch 7:** R3a → R3b → R3c
   - **Batch 7.5** (owner's notes, 2026-11-18): R1h (folder codes) → R3d (top bar) → R1i (sections: collapsible, movable, one/two columns, per user)
8. **Batch 8:** R5c → R5d → R5b 📧
9. **Batch 9:** R6a → R6b → R1d → R6c → R7
   - **Batch 9.5** (owner's notes, 2026-11-19): R2e (list pager above the list too; previous / next; any page from a list) → R6d (settings explained, numbering settings together, settings without effect hidden) → R2f (dates out of order or in the future flagged in the list and the form) → R2g (the list pager on every paged screen) → R2h (row actions under ⋮) → R5e (inbox: date field; suggestions from file names) → R1j (form: floating save, copy/receipt at the far right, Λίστα in the top bar a link) → R3e (each user's home page; the top bar stays open from 768px) → R2i (list: alternating colours, fixed columns, short rows with ▾) → R3f (search: whole words / numbers) → R1k (unsaved changes: red dot, question before leaving) → R2j (assignment pills ✎ / 👁) → R5f (inbox badge with the number of e-mails) → R5g (inbox pagers) → R3g (Διαχείριση in the user menu) → R2k (list rows two lines, Από / Προς tooltip) → R5h (inbox: scrolling to an e-mail stays) → R5i (long recipient lists folded)
10. **Batch 10:** A01 → A03 → A04 → A05 → A06 → X01 → X02 → Z01 → Z02 → Z03

X01 can be moved earlier at any time (it is generic and doesn't depend on module rebuilds).

## Traceability (owner request → task)

| Owner request | Task |
|---|---|
| Action buttons as pills, single row | N04 (+ every R-screen) |
| Navigation at top of every screen | N03 |
| Gmail-style search on every screen | R3b, R3c |
| Empty email page → page 1 | R5c |
| Whole-screen drag-and-drop attachments | R1c |
| Inbox left sidebar with scroll-to | R5c |
| Collapsible plain/HTML with memory | R5c |
| Search: optional year prefilled, Enter, Σχετικοί αριθμοί | R3a |
| Date prev/next buttons + calendar for protocol date | N04, R1b |
| "Number already used" indicator | R1b |
| Character sanitation (`--`) | R1a |
| `??` characters in email fields | R5a |
| TAB after number → day | R1b |
| Remove green attachments popup | R5d |
| YES/NO dialog → inline notice | R1b, R5d |
| Copy also copies date | R1a |
| Manual number/date not overwritten | B03 |
| Subject from attachment filename | R1c, R5d |
| Single number in date → full date | N04 |
| Attachments open on new/copy | R1c |
| Subject/summary VARCHAR → TEXT | D01 |
| `9` → `09` | N04 |
| EML cache, multi-user | R5a |
| File-type icons | N04, R1c, R5c |
| File size (no slowdown) | D03, R1c, R5c |
| Partial incoming-number search | R3a |
| Folder autocomplete ignoring accents/case/symbols | R3a |
| Keywords from email content | R5d |
| Simple user finds unassigned protocols | S02 |
| Auto-assignment by folder | R6b, R1d |
| Replace ΕΛΕΥΘΕΡΟ protocol on manual number | R1a |
| Gap list on list refresh | A02, R2 |
| Writer may edit protocol date | R1a |
| Descriptive errors instead of 500 | B04 |
| Email registration failure message without reload | R5d |
| Checks: gaps 30 days, εκκρεμότητα, date continuity, duplicates, missing attachment | A01, A03–A06 |
| Modernize all libraries, IMAP security, PHP | N01, N03, Z01 |
| winmail.dat | R5b |
| [New] Trash for deleted attachments with retention | R4b |
| [New] Gap banner with start-number setting | A02 |
| [New] Audit log | X01, X02 |

## Q&A log
- **Q-1** (B03): number/date at top can be edited, but save resets date to today and number to next free; user re-enters both.
- **Q-2**: Protocols are deleted only to replace/re-create them; saving onto an existing number correctly fails; no auto-fill of gaps.
- **Q-3**: Gap check over a recent window (3–6 months, configurable).
- Rebuild approach and Livewire approved (2026-11-12).

## Context
- App runs on the local network only (not internet-exposed), MySQL, multiple concurrent users. Symptoms: random timeouts, 500 errors, functional and UI bugs.
- Owner can supply some real-world, public-safe emails; the rest synthetic.

## Open questions
- **B03d (pinned):** date reset on save — not reproducible yet (production: *Έλεγχοι καταχώρισης* = NO). Owner will report the steps when it happens again.
