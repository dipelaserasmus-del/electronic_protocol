# Batch 9 — administration and printing (2026-11-18)

The administrator pages and the printouts are rebuilt on the new layout. After this batch, every page you see when
logged in uses the new layout. Each task was merged to `master` only after all CI jobs were green and the automated
review had been checked.

| Task | Result | PR | Report |
|---|---|---|---|
| R6a Users and roles | page **Χρήστες**: search, role and active filters, one form, what each role may do; **an inactive user can no longer log in** (before: they could), and is logged out at once; at least one active administrator always remains; nobody deactivates or deletes their own account; users with protocols are deactivated, not deleted | #65 | [R6a](R6a.md) |
| R6b Folders | page **Φάκελοι και διατήρηση**: all folders with their protocols, search; per folder **who gets its protocols automatically** (Διεκπεραίωση / Ενημέρωση); **import of your folder list** pasted from Excel/LibreOffice, checked row by row first; a code in use is not renamed or deleted | #66 | [R6b](R6b.md) |
| R1d Automatic assignment | a protocol saved in such a folder (also by a writer or from the inbox) is assigned to those users, who get the usual e-mail; the form says it before saving | #67 | [R1d](R1d.md) |
| R6c Settings, backups, clean-up | **Ρυθμίσεις** in six cards, each setting explained, a wrong value refused at its field; **Backup** with date and size; **Εκκαθάριση** lists the expired attachments, prints them and moves them to the trash | #68 | [R6c](R6c.md) |
| R7 Printing | **Εκτύπωση** says how many protocols before printing; the book keeps its 13 columns; Excel; attachments print with each file's own keeping time; receipt | #69 | [R7](R7.md) |

## Found on the way
- **Security:**
  - an inactive user could still log in;
  - the last administrator could be deleted, which reopened self-registration;
  - users, folders and backups could be deleted, and expired files moved to the trash, by **plain links** without a
    CSRF check (a page elsewhere could do it through an administrator's browser). All are now actions of their pages.
- **Ρυθμίσεις** stored any posted field and checked few: a colour, a year or a Διαύγεια link could be anything.
- *Χρήστες που καταχωρίζουν e-mail* matched parts of names (`nik` passed with `nikos` in the list).
- Old printouts:
  - inactive handlers were printed as numbers;
  - the book and Excel were cut at the limit without saying so;
  - the attachments print gave all files the first file's keeping time;
  - the expired list showed dates as `20200101`.
- Review (Codex):
  - R6a: the users page counted each user's protocols one by one. It now loads all counts at once, with a new index
    on `protocols.user_id`;
  - R6b: a user deactivated after being put in a folder's rules blocked saving that folder;
  - R6b: a spreadsheet cell with a line break split the import row.

  All three are fixed, with tests.
- On the owner's Fedora PC, Docker Desktop kept stale copies of files changed with `sed -i` inside the container:
  noted for future sessions, no effect on the app.

Tests: PHPUnit 508, browser tests: see CI (new: users, folders, auto-assign, settings, printing).
