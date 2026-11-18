# Batch 6.6 — your feedback on Batch 6.5 (2026-11-18 11:10)

You tried Batch 6.5 on 2026-11-18 and sent notes on the form, the tags, the folders and the missing-number rows. This
short batch handles all of them. Each task is merged to `master` only after all CI jobs are green and the automated
review has been checked.
PHPUnit: **346 → 356 tests**; browser tests: **48 → 52**.

| Task | Result | PR | Report |
|---|---|---|---|
| D06 Folders | a folder's description (and remarks) holds long text (TEXT, up to 16,000 characters); the folders page no longer adds `<br />` on each edit | [#51](https://github.com/dipelaserasmus-del/electronic_protocol/pull/51) | [D06](D06.md) |
| R2d Missing numbers | the row is green with grey text: "10939/2026 ΚΕΝΟ"; the button with + is in the middle | [#52](https://github.com/dipelaserasmus-del/electronic_protocol/pull/52) | [R2d](R2d.md) |
| R1f Tags and folders | *Σχετικοί αριθμοί*: green ✔ when the protocol exists (↗ opens it), orange ⚠ when not; *Λέξεις κλειδιά* are tags too; a click on a tag edits it; keyword suggestions from every keyword used (most used first, accents ignored); *Φάκελος* is a search box that narrows by code or description | [#53](https://github.com/dipelaserasmus-del/electronic_protocol/pull/53) | [R1f](R1f.md) |
| R1g Form layout | the protocol number large in the middle, small year, "Ελεύθερος" only after you change the number; *Διεκπεραιώθηκε την* high in the right column; *Ανάθεση* and *Απόδειξη παραλαβής* below everything else | [#54](https://github.com/dipelaserasmus-del/electronic_protocol/pull/54) | [R1g](R1g.md) |

## Found on the way
- The folders page turned line breaks into `<br />` that were saved with each edit, and printed the description
  without escaping (D06).
- Review (Codex): after closing the keyword suggestions with Escape, Enter did nothing instead of adding the typed
  keyword; fixed with a test (R1f).

## Firefox crash on drop (Fedora, GNOME)
The crash report shows Firefox's main process stopped by a Wayland protocol error ("unknown object … wl_data_offer"),
inside GTK/libwayland, while receiving the dropped file. A web page cannot cause such a crash. This is a known Firefox
bug with files dragged from *Files* (Nautilus) on GNOME/Wayland, reported for several Firefox and GNOME versions. Drop
works on Windows 11, as you confirmed, and that is where the server's users are.
R1c could not trigger it because the page did not accept the drop. Since R1e it accepts the drop, so Firefox goes on to
read the file, and that is where it crashes. Nothing in the page changes for this; on Fedora, use *επιλέξτε* in the
attachments box until Firefox fixes it.

## For you
- Try the form (see [OWNER-TODO](../../OWNER-TODO.md)).
- Send the full folder list when ready (a spreadsheet or text file).
