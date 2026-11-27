# Batch 9.7 — your requests of 2026-11-25 to 2026-11-27 (2026-11-27)

48 tasks, all merged to `master`, each only after every CI job was green (unit tests on PHP 8.4 and 8.5, the full
browser suite on the Docker stack, image builds, security audit) and the automated review had been checked.

The biggest change: **Ρυθμίσεις** were rebuilt as GitHub's settings (R6f). Then, from your plan in the interactive
*Σχεδιαστής ρυθμίσεων*, every setting was regrouped and reworded, checked against the code, and documented in
[docs/SETTINGS.md](../SETTINGS.md) (R6m). Around it: the inbox got its looks, order and «registered» marks per user;
the list and the form were tidied for daily work; the stamp can be placed, made opaque and put on pictures; the
printed book and the receipt are laid out for paper.

### Ρυθμίσεις and administration

| Task | Result | PR | Report |
|---|---|---|---|
| R6f | Ρυθμίσεις as GitHub's settings: a menu of sections under headings, one section at a time, saved on its own | [#128](https://github.com/dipelaserasmus-del/electronic_protocol/pull/128) | [R6f](R6f.md) |
| R6m | Ρυθμίσεις as the owner planned them (settings planner): new grouping and texts; mysqldump path and print limit to .env | [#162](https://github.com/dipelaserasmus-del/electronic_protocol/pull/162) | [R6m](R6m.md) |
| R6l | «Σχετικοί αριθμοί και στα δύο πρωτόκολλα» applied to existing protocols; docs/RELATED-NUMBERS.md | [#142](https://github.com/dipelaserasmus-del/electronic_protocol/pull/142) | [R6l](R6l.md) |
| R6g | Φάκελοι και διατήρηση: sort by Κωδικός / Πρωτ.; a folder's code shows its protocols | [#129](https://github.com/dipelaserasmus-del/electronic_protocol/pull/129) | [R6g](R6g.md) |
| R6h | Fix: a folder stored without the dot (Φ12) and in use could not be saved («the code does not change») | [#130](https://github.com/dipelaserasmus-del/electronic_protocol/pull/130) | [R6h](R6h.md) |
| R6i | A folder's automatic assignment applied to its existing protocols (chosen each time: open / all / this year; no e-mails) | [#131](https://github.com/dipelaserasmus-del/electronic_protocol/pull/131) | [R6i](R6i.md) |
| R6j | Log viewer: a «Σήμερα» button next to the day | [#138](https://github.com/dipelaserasmus-del/electronic_protocol/pull/138) | [R6j](R6j.md) |
| R6k | Log viewer: «Εξαγωγή» of the day's entries as shown; personal data masked by default | [#139](https://github.com/dipelaserasmus-del/electronic_protocol/pull/139) | [R6k](R6k.md) |

### Inbox

| Task | Result | PR | Report |
|---|---|---|---|
| R5q | Inbox look per user (user menu), with «Καμία» (the thin line); the setting only the default | [#119](https://github.com/dipelaserasmus-del/electronic_protocol/pull/119) | [R5q](R5q.md) |
| R5r | Inbox: a refused Καταχώριση is brought into view (field focused, reason above the buttons); no «Καταχωρίζεται…» for a quick refusal | [#122](https://github.com/dipelaserasmus-del/electronic_protocol/pull/122) | [R5r](R5r.md) |
| R5s | Inbox: a folder for every e-mail (with or without attachments); setting «Ο Φάκελος χρειάζεται για κάθε e-mail» removed | [#123](https://github.com/dipelaserasmus-del/electronic_protocol/pull/123) | [R5s](R5s.md) |
| R5t | Inbox: no «Με σφραγίδα» before registering; the registered protocol's files listed with 👁 and the stamp switch | [#125](https://github.com/dipelaserasmus-del/electronic_protocol/pull/125) | [R5t](R5t.md) |
| R5u | User menu: «Εμφάνιση των e-mail» only on the inbox page | [#127](https://github.com/dipelaserasmus-del/electronic_protocol/pull/127) | [R5u](R5u.md) |
| R5v | Inbox: the range only at the pager; «παλαιότερα πρώτα ↑ / νεότερα πρώτα ↓» swapped by each user | [#145](https://github.com/dipelaserasmus-del/electronic_protocol/pull/145) | [R5v](R5v.md) |
| R5w | Inbox: the round ↑ «back to the top» of the list | [#150](https://github.com/dipelaserasmus-del/electronic_protocol/pull/150) | [R5w](R5w.md) |
| R5x | Inbox: a registered e-mail's envelope gets a green stripe on the left | [#155](https://github.com/dipelaserasmus-del/electronic_protocol/pull/155) | [R5x](R5x.md) |
| R5y | Inbox: one spinner while registering; a registered e-mail tinted and bordered green, a read one grey; the page follows it | [#157](https://github.com/dipelaserasmus-del/electronic_protocol/pull/157) | [R5y](R5y.md) |

### Protocol list

| Task | Result | PR | Report |
|---|---|---|---|
| R2m | List: «Λίστα Πρωτοκόλλων», no Νέο / Ανανέωση on the page (↻ next to Λίστα), ↑ back to the top; top bar always in view (test) | [#120](https://github.com/dipelaserasmus-del/electronic_protocol/pull/120) | [R2m](R2m.md) |
| R2n | List: a protocol without a folder flagged (⚠ Χωρίς, tinted row), as a wrong date | [#126](https://github.com/dipelaserasmus-del/electronic_protocol/pull/126) | [R2n](R2n.md) |
| R2o | List: the no-folder flag says «Χωρίς φάκελο» in full | [#136](https://github.com/dipelaserasmus-del/electronic_protocol/pull/136) | [R2o](R2o.md) |
| R2p | List: an attachment that is only an ΑΔΑ shows a pill «ΑΔΑ» and its code as the link | [#137](https://github.com/dipelaserasmus-del/electronic_protocol/pull/137) | [R2p](R2p.md) |
| R2q | List: the Από / Προς tooltip one line per part (sender, number, date) | [#140](https://github.com/dipelaserasmus-del/electronic_protocol/pull/140) | [R2q](R2q.md) |
| R2r | List: the e-mail's own file after the attachments, shown by its date and time (setting to put it first) | [#141](https://github.com/dipelaserasmus-del/electronic_protocol/pull/141) | [R2r](R2r.md) |
| R2t | The list's head (title, gaps notice, filters, pager) stays under the top bar | [#159](https://github.com/dipelaserasmus-del/electronic_protocol/pull/159) | [R2t](R2t.md) |
| R2u | List: a file's 👁 before its name, its size after it (dimmed); filter tooltips; the fixed head blends with the page | [#161](https://github.com/dipelaserasmus-del/electronic_protocol/pull/161) | [R2u](R2u.md) |

### Protocol form

| Task | Result | PR | Report |
|---|---|---|---|
| R1l | Protocol form: a refused Αποθήκευση brings the field with the error into view (focused) | [#132](https://github.com/dipelaserasmus-del/electronic_protocol/pull/132) | [R1l](R1l.md) |
| R1m | Form: an attachment that is only an ΑΔΑ shows an ΑΔΑ pill and its code on the left (no «Μόνο ΑΔΑ») | [#133](https://github.com/dipelaserasmus-del/electronic_protocol/pull/133) | [R1m](R1m.md) |
| R1n | Fix: saving a new protocol asked «leave page?» (the unsaved-changes guard woke during the save) | [#134](https://github.com/dipelaserasmus-del/electronic_protocol/pull/134) | [R1n](R1n.md) |
| R1o | Protocol form: the time left to change it in days, hours and minutes | [#144](https://github.com/dipelaserasmus-del/electronic_protocol/pull/144) | [R1o](R1o.md) |
| R1p | Protocol form: keywords offered from the subject (on leaving Θέμα) and the files' names | [#154](https://github.com/dipelaserasmus-del/electronic_protocol/pull/154) | [R1p](R1p.md) |
| R1q | Protocol form: ◀ ▶ in the title line; copy / receipt / delete stacked at the right; no «Σύνδεσμος»; TAB from field to field; new files marked «ΝΕΟ» | [#160](https://github.com/dipelaserasmus-del/electronic_protocol/pull/160) | [R1q](R1q.md) |
| R4l | Deleted protocol: the notice only says «… μεταφέρθηκε στον κάδο.», «κάδο» a link to the trash | [#135](https://github.com/dipelaserasmus-del/electronic_protocol/pull/135) | [R4l](R4l.md) |

### Files, preview and stamp

| Task | Result | PR | Report |
|---|---|---|---|
| R4i | Stamp: designer for administrators only; «Με σφραγίδα» for every user (the setting is only the default) | [#118](https://github.com/dipelaserasmus-del/electronic_protocol/pull/118) | [R4i](R4i.md) |
| R4j | A type without preview: grey 👁, not clickable, left out of ‹ › | [#121](https://github.com/dipelaserasmus-del/electronic_protocol/pull/121) | [R4j](R4j.md) |
| R4k | Stamp: the number and date lines on opaque white (nothing under the stamp hides them) | [#124](https://github.com/dipelaserasmus-del/electronic_protocol/pull/124) | [R4k](R4k.md) |
| R4m | Old server never stamped: the migration marks no file when putStamp is off; stamp:old --none-stamped | [#149](https://github.com/dipelaserasmus-del/electronic_protocol/pull/149) | [R4m](R4m.md) |
| R4n | Stamp place per file: top left / centre / right from the preview, for every user | [#151](https://github.com/dipelaserasmus-del/electronic_protocol/pull/151) | [R4n](R4n.md) |
| R4o | The stamp on JPG and PNG pictures (as an A4 page; EXIF orientation; per-file place) | [#153](https://github.com/dipelaserasmus-del/electronic_protocol/pull/153) | [R4o](R4o.md) |
| R4p | The stamp see-through or opaque per file (chosen in the preview, for every user) | [#156](https://github.com/dipelaserasmus-del/electronic_protocol/pull/156) | [R4p](R4p.md) |
| R4q | Preview of e-mail files (.eml); 👁 next to each file in the protocol list | [#158](https://github.com/dipelaserasmus-del/electronic_protocol/pull/158) | [R4q](R4q.md) |

### Printing

| Task | Result | PR | Report |
|---|---|---|---|
| R2s | Printed protocol book laid out for paper (sheet on screen, page numbers, cleaner table) | [#146](https://github.com/dipelaserasmus-del/electronic_protocol/pull/146) | [R2s](R2s.md) |
| R2v | The receipt restyled for paper, as the protocol book | [#163](https://github.com/dipelaserasmus-del/electronic_protocol/pull/163) | [R2v](R2v.md) |

### Navigation and layout

| Task | Result | PR | Report |
|---|---|---|---|
| R3j | Narrow page: − / + / ↺ for its width, saved per user | [#117](https://github.com/dipelaserasmus-del/electronic_protocol/pull/117) | [R3j](R3j.md) |
| R3k | No «Νέο» for readers (top bar, search page, old menu) | [#143](https://github.com/dipelaserasmus-del/electronic_protocol/pull/143) | [R3k](R3k.md) |

### Security, platform, versions

| Task | Result | PR | Report |
|---|---|---|---|
| N06 | Symfony security updates (advisories of 2026-11-25: mailer, routing, http-kernel) | [#147](https://github.com/dipelaserasmus-del/electronic_protocol/pull/147) | [N06](N06.md) |
| F10 | Docker image: security updates from the main archive when security.ubuntu.com fails | [#152](https://github.com/dipelaserasmus-del/electronic_protocol/pull/152) | [F10](F10.md) |
| V01 | Versions report: original / current / newest, flagged (docs/reports/VERSIONS.md) | [#148](https://github.com/dipelaserasmus-del/electronic_protocol/pull/148) | — |
| V01b | Versions report: a description of each component (tooltips + list) and the forecast for after Batch 10 | [#164](https://github.com/dipelaserasmus-del/electronic_protocol/pull/164) | — |

## Changed from the plan
- **Ρυθμίσεις (R6m), your decisions of 2026-11-27:**
  - sections: Υπηρεσία/Οργανισμός · Πρωτόκολλο (Αρίθμηση και έλεγχοι, Λίστες και αναζήτηση, Καταχώριση, Δικαιώματα)
    · Αρχεία (Συνημμένα, Κάδος) · E-mail (Λογαριασμός εισερχομένων, Εισερχόμενα, Ειδοποιήσεις); *Εκτυπώσεις και
    Excel*, *Καταχώριση e-mail* and *Συντήρηση* are gone;
  - three values left the page for the `.env` file: `MYSQLDUMP_PATH`, `PRINT_MAX_ROWS`, `APP_TIMEZONE`; their old
    database rows now say so («Δεν χρησιμοποιείται πια: ορίζεται στο .env ως …»), with the old value kept;
  - the field suggestions' two values, never on the new page before, are now under *Καταχώριση*;
  - administrators always have the Inbox, whatever the user list says.
- **The spreadsheet of settings was replaced by the interactive planner** (it showed the page itself, with drag and
  drop); the spreadsheet was deleted on 2026-11-27. The planner now starts from the merged settings, ready for the
  next round.

## Found on the way
- **«Αποστολή απόδειξης για κάθε e-mail» only hid a button:** a registration without a receipt was still possible.
  The server now enforces it (R6m).
- **About fifteen setting texts said more or less than the code does** (e.g. a non-current «Ενεργό έτος» with the
  checks on blocks every new protocol, also from the Inbox; «Αριθμός για ξεκίνημα» applies only when there are no
  protocols at all). The texts now match the code; the list is in [R6m](R6m.md).
- **The suggestions' «how many» was silently raised to 50:** the stored 10 showed 50. Now the value means what it says
  (empty = 50, so nothing changed for users).
- Each task's own findings and review fixes are in its report (linked above).

## For you
- Try the new steps in [OWNER-TODO](../../OWNER-TODO.md) (up to 116), at your own pace.
- On your real copy, after updating: `./dev.sh real artisan migrate --force` (new columns for the stamp's place and
  look, and the notes in the three retired settings' rows).
- Every setting explained, for the future manual: [docs/SETTINGS.md](../SETTINGS.md).

Tests at the end of the batch: 722 PHPUnit tests and the full browser suite (about 140 tests), all passing in CI on
the last PRs.
