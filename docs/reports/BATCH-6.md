# Batch 6 — summary (2026-11-16 23:54)

All 6 planned tasks are done. Each one is merged to `master` only after all CI jobs are green and every automated
review finding has been checked, fixed with a test and answered. This batch rebuilds the screens used most: the
protocol form, its attachments and the protocol list. It also adds safe file handling and a trash for attachments.
All of it reaches the server with the final deployment.
PHPUnit: **263 → 339 tests**; browser tests: **27 → 42**.

| Task | Result | PR | Report |
|---|---|---|---|
| R1a Saving | every save goes through one service. It applies on the server the rules the form only showed, fixes several bugs (below), lets writers set the date if a new setting allows it, keeps the date on *copy*, and takes over an *ΕΛΕΥΘΕΡΟ* number after confirmation | [#42](https://github.com/dipelaserasmus-del/electronic_protocol/pull/42) | [R1a](R1a.md) |
| R1b Protocol form | new form: the number says *Ελεύθερος* / *Δεσμευμένος* while you type; TAB goes from the number to the day; notices instead of YES/NO dialogs; actions as pills | [#43](https://github.com/dipelaserasmus-del/electronic_protocol/pull/43) | [R1b](R1b.md) |
| R1c Attachments | panel always open; drag files anywhere onto the window; ΑΔΑ per file; an empty subject takes the file's name | [#44](https://github.com/dipelaserasmus-del/electronic_protocol/pull/44) | [R1c](R1c.md) |
| R2 Protocol list | filter pills (mine, to handle, to inform, done; other users for assigners), sorting by column, header that stays visible, *Αναλυτικά* remembered | [#45](https://github.com/dipelaserasmus-del/electronic_protocol/pull/45) | [R2](R2.md) |
| R4a Safe files | only PDF/images/text open in the browser; uploaded web pages can no longer run inside the application; programs are refused on upload | [#46](https://github.com/dipelaserasmus-del/electronic_protocol/pull/46) | [R4a](R4a.md) |
| R4b Trash | deleted attachments (also those of a deleted protocol) wait in *Διαχείριση → Κάδος* for 30 days (setting), with restore and "delete now" | [#47](https://github.com/dipelaserasmus-del/electronic_protocol/pull/47) | [R4b](R4b.md) |

## Bugs found and fixed on the way
- **A writer who saved a protocol emptied its assignments** (the old form didn't send them) — R1a.
- Fields shown read-only (number, date, locked parts, deadline) were only read-only in the browser — R1a.
- Search results and autocomplete printed stored text unescaped (a crafted subject could run script) — R1a.
- `31/02/2026` was stored as 3 March — R1a.
- **On the Docker stack every upload over 1 MB failed** (nginx limit); this is the production target — R1b.
- **An uploaded `.html`/`.svg` opened as a page of the application** — R4a.
- The trash overwrote files with the same name, and *Οριστική διαγραφή* emptied the whole trash folder — R4b.
- Review findings (Codex), 13 in all, fixed with tests, among them:
  - a reader could register a protocol by calling the form's save directly;
  - the form could be pointed at a protocol the user may not see;
  - a protocol could be saved without a file still uploading;
  - the menu's per-user list showed everybody's protocols;
  - a renamed Windows program passed the upload check;
  - emptying the trash could delete a live file with the same name;
  - a failure after saving asked the user to register again (a second protocol).

  Details in each task's report.

## Decisions taken (tell me if you want otherwise)
- *Δυνατότητα Επεξεργασίας = ΚΑΝΕΙΣ* now also blocks writers when no time limit is set (the old form allowed it, the
  server refused it).
- New setting *Ο Συγγραφέας μπορεί να αλλάξει την ημερομηνία πρωτοκόλλου* (off by default).
- Uploads: everything is accepted except types that run code (a list of allowed types would refuse real documents).
- Trash: 30 days by default. On the server, files already in the old trash get their 30 days from the deployment
  day.
- Removed old pages and links along with the old form and list (delete/assign/done now happen inside the form).
