# Batch 9.6 — your requests of 2026-11-23 and 2026-11-24 (2026-11-24)

Direct links, search by «when» and «who», renaming and previewing files, an editable stamp put when files are shown,
a trash for deleted protocols, envelopes in the inbox, a notepad and the log viewer. Each task was merged to `master`
only after all CI jobs were green and the automated review had been checked.

| Task | Result | PR | Report |
|---|---|---|---|
| R3h Direct links | `/protocol/125` (this year) and `/protocol/125/2025` open the protocol; a missing, forbidden or malformed link gets the same *Δεν υπάρχει* page (it never tells whether a protocol exists); **Σύνδεσμος** in the form copies the link | #105 | [R3h](R3h.md) |
| R3i Search: when, who | `πότε:σήμερα / χθες / εβδομάδα / 7μέρες / μήνας / φέτος / πέρσι` (every date word), kept relative in saved searches; `χρήστης:` = who registered it; quick date buttons and *Καταχωρίστηκε από* in the advanced search | #106 | [R3i](R3i.md) |
| R4d Rename attachments | ✎ next to a file's name: only the name shown changes, the extension stays, the file keeps its place; found by `συνημμένο:` at once | #107 | [R4d](R4d.md) |
| R4c Preview | 👁 opens a file in a large window, ‹ › through the protocol's (or e-mail's) files: PDF, pictures, text, Word, spreadsheets; other types: download | #108 | [R4c](R4c.md) |
| R4e Stamp designer | the stamp is a template (emblem, lines, dotted field lines, colour, size, corner, first page) edited by administrators, assigners and writers with a live preview; sharp at 600 dpi and placed at its real size; your official stamp built locally (`stamp:import`) | #109 | [R4e](R4e.md) |
| R4f Stamp on view | files are stored as they came; opening, downloading and printing give the stamped copy; **Με σφραγίδα** per user for the originals; files stamped before are never stamped twice | #110 | [R4f](R4f.md) |
| R4g Old stamps | the old tool's stamp is recognised exactly and can be taken out of PDFs (`stamp:old`, originals kept); Word files stay | #111 | [R4g](R4g.md) |
| R4h Protocols to the trash | *Διαγραφή* moves a protocol to the trash and frees its number; *Επαναφορά* with its number, or a new one with a note; administrators all, writers / assigners their own | #112 | [R4h](R4h.md) |
| R5p Envelopes | each e-mail in an envelope with sender and date; **three looks to choose from** | #113 | [R5p](R5p.md) |
| N05 Notepad | a private notepad on every page: sections, to-dos, formatting, saved by itself, two tabs never overwrite each other | #114 | [N05](N05.md) |
| R6e Log viewer | *Αρχεία καταγραφής*: a day at a time, levels, search, details with the stack trace; big logs read in parts | #115 | [R6e](R6e.md) |

## Changed from the plan
- **R4c:** Word and spreadsheet files are converted **on the server** with the libraries the application already has
  (PhpWord, PhpSpreadsheet), not in the browser: installing new JavaScript packages was not allowed in this session,
  and the npm copy of SheetJS has known security advisories. The window and the buttons are as planned; switching to
  the browser libraries later is a small task if you want it.
- **R4e:** a new way to put the stamp on PDFs (an update appended to the file) replaced the old tool, which drew one
  pixel per point, could only stamp every page and ignored rotated pages. The old tool stays only as a fallback.

## Found on the way
- The old stamp tool put the stamp in the wrong place on rotated pages, and resaved Word files through PhpWord (their
  first version cannot be restored: R4g leaves them).
- `configs.value` was 255 characters: too short for the stamp's template (now TEXT).
- The notepad's tab covered the list's ⋮ menu at the right edge: the page now keeps a strip free for it on wide
  screens; on narrow ones it is a round button at the bottom left (found by the full browser suite before the PRs).
- Review (Codex):
  - R3i: a typed relative date cleared a year that had been set on purpose (`πότε:πέρσι έτος:2026` through
    *Σύνθετη αναζήτηση*);
  - R4d: typing the name with its extension in the field gave `.pdf.pdf`;
  - R4c: a long UTF-8 text cut inside a character was read as Windows-1253;
  - R4e: PDFs with a hybrid cross-reference, resource names that are numbers, and a page stamped twice (the first
    stamp would have shown the second image);
  - R4f: the office's name was not part of the stamped copy's key; a file uploaded again over an old one kept the
    old one's «stamp inside» mark;
  - R4g: a stamp added by R4e was taken for «no old stamp»; failed writes were not checked;
  - R4h (security): a writer could force the trash's files tab open from the browser; files that could not be moved
    back were not reported;
  - N05: edits made just before leaving the page, a save while another was on its way, editing before the note had
    loaded;
  - R6e: an empty log file, entries of the same second out of order, the end of a long message not reachable.

  All fixed, with tests.

Tests: PHPUnit 668, browser 118 (locally, all passing), browser tests: see CI (new: direct links, search when/who, rename, preview, stamp
designer, stamp on view, protocol trash, envelopes, notepad, logs).
