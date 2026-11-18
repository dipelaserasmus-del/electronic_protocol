# Batch 7.5 — your notes on Batch 7 (2026-11-18)

You tried Batch 7 on 2026-11-18 and sent notes on the folder list, the top bar and the protocol page. This short batch
handles them. Each task is merged to `master` only after all CI jobs are green and the automated review has been
checked.

| Task | Result | PR | Report |
|---|---|---|---|
| R1h Folder codes | `12` puts Φ12 first and the list shows it at its top; `φ11` means Φ.11 / Φ11 only, never Φ1.1; the same rule in the form, the search screen and the search box | #59 | [R1h](R1h.md) |
| R3d Top bar | *Νέο · Λίστα · Εισερχόμενα · Διαχείριση* (Εκτύπωση and Διατήρηση moved under Διαχείριση), search and *Σύνθετη αναζήτηση* as buttons of the search box, the organisation under the name, icons everywhere; **page width** per user: the whole window or narrow and centred | #60 | [R3d](R3d.md) |
| R1i Protocol page sections | drag a section by ⠿ anywhere (two columns and the row below), ▴/▾ to collapse, one or two columns, *Επαναφορά διάταξης*; saved with your account | #61 | [R1i](R1i.md) |

## Found on the way
- Review (Codex): two new tests of R3d had ended up inside a helper class of the test file and never ran. They were
  moved, they run and pass, and the other test files were checked.
- New dependency: Bootstrap Icons 1.13.2 (MIT, by the Bootstrap team) for the icons, pinned to that version.
- New column `users.preferences` for each user's choices (page width, sections); `php artisan migrate` adds it.

## Your two e-mail notes
Neither problem appears on the current code (checked locally with your e-mails; nothing from them is published):
- **`ΑΔΕΙΑ ΚΩΝΣΤΑΝΤΙΝΙΔΟΥ`:** in *Εισερχόμενα* the sender, subject, text and all three attachment names show
  correctly, with no `??`.
- **Registering e-mails of the `ΑΔΕΙΑ` sender:** both this e-mail and `ΑΔΕΙΑ` register, with their attachments. The
  old inbox page refuses an e-mail with attachments until a **Φάκελος** is chosen; its message is *«Για να
  καταχωρίσετε email με συνημμένα αρχεία είναι απαραίτητο να επιλέξετε Φάκελο»*, and the e-mail then stays in the
  inbox.

The questions for you are in [OWNER-TODO](../../OWNER-TODO.md). The inbox page itself is rebuilt in Batch 8 (R5c,
R5d).
