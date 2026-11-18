# Batch 8 — the inbox (2026-11-18)

The *Εισερχόμενα* page is rebuilt on the new layout, registering an e-mail now follows the same rules as every
protocol, and Outlook's `winmail.dat` is opened. Each task was merged to `master` only after all CI jobs were green and
the automated review had been checked.

| Task | Result | PR | Report |
|---|---|---|---|
| R5c Inbox screen | new page `/inbox`: the page's e-mails in a list on the left (a click goes to the e-mail); each e-mail with its form, its attachments with **type and size**, its text and HTML in sections that open and close (remembered by your browser); registering **without reloading**: the result in the e-mail's place, an error **at its field**; *Αναγνωσμένο*; an emptied page goes back to page 1. Saving goes through the same code as the protocol form. | #62 | [R5c](R5c.md) |
| R5d Registering from the inbox | **same subject / incoming number as an existing protocol**: a notice with links, and *«Ναι, καταχώριση»* to register anyway (was a pop-up); an e-mail **without a subject** takes its attachment's name; **Λέξεις κλειδιά** as tags, with **suggestions from the subject** (keywords you already use first) | #63 | [R5d](R5d.md) |
| R5b winmail.dat | the files inside are shown and stored instead of `winmail.dat`; its text (also Outlook's "Rich Text") is shown; a **read receipt** shows what it reports (subject, sent, read) | #64 | [R5b](R5b.md) |

## Found on the way
- Review (Codex), R5c: administrators who may choose the number, and writers allowed to change the date, could not
  register e-mails (the page has no number/date fields). Fixed: an e-mail is always numbered on save and dated today.
  Errors about something the page has no field for now appear above the form.
- Review (Codex), R5d: for an e-mail without a subject, the duplicate check missed protocols with the subject it gets
  from its attachment. Fixed.
- Review (Codex), R1i (Batch 7.5): a file refused when dropped on a collapsed *Συνημμένα* now opens the section, so the
  reason is seen.
- Review (Codex), R5b, twelve rounds, all fixed with tests:
  - a crafted `winmail.dat` could make the inbox page time out (huge counts in a few bytes) or run out of memory
    (deeply nested Rich Text);
  - an HTML body in Windows-1253 showed unreadable Greek;
  - the e-mail kept as a page missed a winmail's text, and very long texts were cut there;
  - Greek RTF written as plain bytes, emoji and punctuation such as dashes in Rich Text were lost, and a picture's
    data in it could hide the rest of the text;
  - Greek marked by its font in a Western Rich Text came out as Latin letters, and Greek given in both a plain and a
    Unicode copy came out as `????`;
  - a winmail.dat cut short (inside a header, in its Rich Text, or in its property list) or damaged (checksum) was
    taken as complete.
- The old inbox's checks ran only in the browser (folder required, dates). They now run on the server: an over-long
  value is refused at its field instead of by the database.
- Removed with the old page: `storeFromEmail`, `setEmailRead`, `checkSameEmail`, `getEmailNum` (about 600 lines).
  `/viewEmails` redirects to `/inbox`.

## Your e-mails
All 12 e-mails in `local-data/emails/`, including the 2 new ones of today, register on the new page, checked on this
computer only (nothing from them is published). This was done with a folder, all attachments and *Έλεγχοι καταχώρισης*
on. Your read receipt (`winmail.dat` only) now reads as text instead of an empty e-mail.

Tests: PHPUnit 457, browser 68.
