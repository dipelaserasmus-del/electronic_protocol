# Batch 6.5 — your feedback on Batch 6 (2026-11-17 12:10)

You tried Batch 6 on 2026-11-17 and sent a list of problems and ideas; this short batch handles all of it. Each task is
merged to `master` only after all CI jobs are green and the automated review has been checked.
PHPUnit: **339 → 346 tests**; browser tests: **42 → 48**.

| Task | Result | PR | Report |
|---|---|---|---|
| R1e Form | files dropped in the middle of the window are attached in Firefox too; the green notice is at the top, under the bar, and closes with a click; *Σχετικοί αριθμοί* are tags (Enter or comma), existing protocols open in a new tab; the trash's "restored" notice links to the protocol | [#48](https://github.com/dipelaserasmus-del/electronic_protocol/pull/48) | [R1e](R1e.md) |
| R2b List | choosing a user under *Όλα* now filters the list; users with open assignments first, with their number; *Αναλυτικά* shows the incoming/outgoing details, who registered it, the whole file names; the attachments column shows the file's name | [#49](https://github.com/dipelaserasmus-del/electronic_protocol/pull/49) | [R2b](R2b.md) |
| R2c Missing numbers | each run of missing numbers is a dashed amber row in the list, with *Καταχώριση με αριθμό …*; the check starts at *Αρ.Πρωτοκόλλου για ξεκίνημα* (one setting instead of two); the yellow box is one line | [#50](https://github.com/dipelaserasmus-del/electronic_protocol/pull/50) | [R2c](R2c.md) |

## Found on the way
- The new "start number must be at least 1" rule would have stopped the settings page from saving, because the
  installer stores 0 there. 0 still means 1; a test covers it.
- Review (Codex): sorted upwards, a run of missing numbers starting at the start number had no row; fixed with a test
  (R2c).

## For you
- **Firefox drop:** please try it again (see [OWNER-TODO](../../OWNER-TODO.md)). I could test a real drag only in Chrome.
- **Attachments shown as missing in your real-data copy:** run *Real data: load newest backup from local-data/db* and
  send the counts it prints.
