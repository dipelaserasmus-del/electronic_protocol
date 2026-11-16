# Batch 4 — summary (2026-11-16)

All 4 planned tasks done, each merged to `master` only after all CI jobs were green and every automated review
finding had been checked, fixed with a test and answered. This batch fixes what your e-mails showed. It reaches the
server with the final deployment. PHPUnit: **186 → 232 tests**; browser tests: **18 → 25**.

| Task | Result | PR | Report |
|---|---|---|---|
| D05 Emoji | database and all tables → utf8mb4: e-mails, attachment names and form fields with emoji are stored. Searches compare exactly as before | [#34](https://github.com/dipelaserasmus-del/electronic_protocol/pull/34) | [D05](D05.md) |
| D01 Long subjects | subject, sender and both summaries → TEXT: your 255+-character subject registers in full; errors show a reference code `E-XXXXXX`, never the database text. Also fixed: the save button of the last e-mail on an inbox page could silently do nothing | [#35](https://github.com/dipelaserasmus-del/electronic_protocol/pull/35) | [D01](D01.md) |
| R5a Inbox | each e-mail is downloaded **once** and kept in a private cache (a page that took 3.5 s now takes 0.2 s); `??` in sender **and attachment** names fixed; registering an e-mail is all-or-nothing (no half-registered protocols). 🔒 Fixed: the inbox left copies of e-mails in a public folder, downloadable without login | [#36](https://github.com/dipelaserasmus-del/electronic_protocol/pull/36) | [R5a](R5a.md) |
| F08 Attachment names | long Greek (or emoji) names are shortened by bytes for Linux/Docker, keeping the extension; nameless attachments become `συνημμένο.pdf`; a script counts existing files that are too long | [#37](https://github.com/dipelaserasmus-del/electronic_protocol/pull/37) | [F08](F08.md) |

## Your e-mails, before → after
| Problem | Before | Now |
|---|---|---|
| Subject over 255 characters | not registered, inbox just reloads | registered in full |
| Emoji (subject, text, attachment name) | not registered / protocol without its attachment | registered with them |
| `??` in a sender name (Horde) | `Γραφε??ο …` | correct |
| `??` in an attachment name (your sick-leave e-mails, Horde) | `ΑΔΕ??ΑΣ` | correct; found in 2 of your 9 e-mails, checked on this computer only |
| Attachment fails to save | protocol left behind, e-mail stays, retry duplicates | nothing left behind, clear message, retry works |
| Long Greek attachment name on Docker | not saved | saved with a shortened file name, shown with its whole name |
| Every inbox visit | downloads every e-mail of the page again | only new e-mails |

## Found on the way
- 🔒 **E-mails downloadable without login on the current server** (`public/tmp/u<n>/<n>.eml`). Fixed in R5a for the
  new code; for the server you run today: [docs/BLOCK-PUBLIC-EMAIL-COPIES.md](../BLOCK-PUBLIC-EMAIL-COPIES.md) (one
  Apache line, tested).
- The new browser tests found two bugs the other tests could not: the inbox's save button for the last e-mail of a page
  (D01), and the header/body join when downloading from the real mail server (R5a).
- Review findings (Codex) fixed with tests: confirm dialog vs form handlers (N04, #33), cache expiry vs concurrent
  users and rollback leaving rows in the trash (R5a), long names losing their extension and long folders (F08).
