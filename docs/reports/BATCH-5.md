# Batch 5 — summary (2026-11-16 18:08)

All 3 planned tasks done, each merged to `master` only after all CI jobs were green and every automated review
finding had been checked, fixed with a test and answered. This batch changes how assignments, attachment details and
dates are stored in the database; the screens look the same. It reaches the server with the final deployment.
PHPUnit: **232 → 263 tests**; browser tests: **25 → 27**.

| Task | Result | PR | Report |
|---|---|---|---|
| D02 Assignments | who handles / is informed about a protocol is now stored in its own table instead of a text field like `d1,e2,`: searches and "my protocols" no longer mix up user 1 with user 12, and are faster. Existing assignments are copied over automatically | [#38](https://github.com/dipelaserasmus-del/electronic_protocol/pull/38) | [D02](D02.md) |
| D03 Attachment details | each attachment records its size, a fingerprint (SHA-256, finds duplicates and damaged files) and **who deleted it**. Existing files are measured by one command (part of *Real data: load*) | [#39](https://github.com/dipelaserasmus-del/electronic_protocol/pull/39) | [D03](D03.md) |
| D04 Dates | dates are stored as real dates instead of numbers like `20261116`. The conversion first checks every value: a registration date that is not a real date stops it with nothing changed; other impossible dates (e.g. 31 February) are kept aside in a separate table and left empty | [#40](https://github.com/dipelaserasmus-del/electronic_protocol/pull/40) | [D04](D04.md) |

## Found on the way
- **Flaky browser test in CI** (an upload sometimes failed): the demo data created folders the web server could not
  write to. Fixed in D03 (`dev.sh up` opens up storage again after loading the demo data), with a check.
- Review findings (Codex) fixed with tests: two people re-assigning the same protocol at the same moment could leave
  the wrong assignments (D02); re-saving an attachment under the same path kept the old size/fingerprint, and a
  deleted attachment's file was looked for in the wrong folder first (D03). D04: no findings.
- D02 has one done-date per protocol (the app has never had one per person); a per-person date would be a new feature.
