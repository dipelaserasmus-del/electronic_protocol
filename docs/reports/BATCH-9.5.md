# Batch 9.5 — your notes after Batch 9 (2026-11-19 to 2026-11-24)

The things you noticed while using the new pages after Batch 9: the list and its rows, the protocol form's buttons,
search, the top bar, the inbox, your folder list and loading your real copy. Each task was merged to `master` only
after all CI jobs were green and the automated review had been checked.

*(This summary was written after the batch, on 2026-11-24 18:40; each task's report has the details.)*

## The list and the protocol form
| Task | Result | PR | Report |
|---|---|---|---|
| R2e Pager | the pager above the list too, with previous / next and any page | #71 | [R2e](R2e.md) |
| R2g Pager everywhere | the same pager in search, trash, users and clean-up; past the last page → the last page | #74 | [R2g](R2g.md) |
| R2f Dates out of order | a date out of order with the numbers, or in the future: ⚠ in the list with an explanation, a warning in the form | #73 | [R2f](R2f.md) |
| R2h Row actions | copy and receipt under a ⋮ menu in each row | #75 | [R2h](R2h.md) |
| R2i Rows | alternating colours, fixed column widths, short rows that open with ▾ | #80 | [R2i](R2i.md) |
| R2j Assignments | pills with ✎ Διεκπεραίωση / 👁 Ενημέρωση (list, Λίστα menu, search results) | #83 | [R2j](R2j.md) |
| R2k Two lines | every row two lines; Από / Προς details in a tooltip; one pill and one file with +N | #88 | [R2k](R2k.md) |
| R2l No «Αναζήτηση» button | the search field has its own | #100 | [R2l](R2l.md) |
| R1j Form buttons | one floating *Αποθήκευση*; copy and receipt at the far right; no Νέο / Λίστα inside the form | #78 | [R1j](R1j.md) |
| R1k Unsaved changes | a red dot on *Αποθήκευση*; leaving asks to save and lists what changed | #82 | [R1k](R1k.md) |
| R6d Settings | every setting explained (ⓘ), numbering settings together, settings without effect hidden; a number always given on save | #72 | [R6d](R6d.md) |

## Search and the top bar
| Task | Result | PR | Report |
|---|---|---|---|
| R3f Whole words | `=` before a value (`εισ:=120`) or the *Ολόκληρες λέξεις* switch; accents ignored | #81 | [R3f](R3f.md) |
| R3e Home page | each user chooses their home page (all / προς Διεκπεραίωση / προς Ενημέρωση); the top bar stays open from 768 px | #79 | [R3e](R3e.md) |
| R3g Διαχείριση | moved into the user menu, in groups | #86 | [R3g](R3g.md) |
| R5f Inbox badge | a red badge next to *Εισερχόμενα* with the e-mails waiting; one count for everybody, every minute | #84 | [R5f](R5f.md) |

## The inbox
| Task | Result | PR | Report |
|---|---|---|---|
| R5e Date and keywords | the app's date field; keyword suggestions from the file names too | #76 | [R5e](R5e.md) |
| R5g Every e-mail reachable | the pager above and below; the page in the list on the left | #85 | [R5g](R5g.md) |
| R5h Stays on the e-mail | going to an e-mail from the list no longer jumps away | #89 | [R5h](R5h.md) |
| R5i Long recipient lists | three names + «και άλλοι N» | #90 | [R5i](R5i.md) |
| R5j Files and the e-mail | the files above the buttons; the e-mail itself tinted apart | #91 | [R5j](R5j.md) |
| R5k Registering | says so until done (notice, spinners); one registration at a time | #92 | [R5k](R5k.md) |
| R5l Done e-mails fold | a registered / read e-mail folds; *Εμφάνιση email*, *Επόμενο email* | #93 | [R5l](R5l.md) |
| R5m Loading | the page at once, «Σύνδεση με τον διακομιστή…» with seconds; a failure says why, with retry; one registration per e-mail across users | #95 | [R5m](R5m.md) |
| R5n From the cache | while the mail server is slow, the last check's e-mails at once | #96 | [R5n](R5n.md) |
| R5o Form and colours | no jump when *Κείμενο* / *HTML* opens; *Εξερχόμενο* fields; the protocol form's section colours; list positions | #97 | [R5o](R5o.md) |
| L03 Test mailbox | an optional test mailbox for your real copy (`local-data/test-mailbox.env`); mail sent only to `TEST_MAIL_ONLY_TO` | #94 | [L03](L03.md) |

## Folders and your real copy
| Task | Result | PR | Report |
|---|---|---|---|
| D07 Your folder list | your list (full descriptions) applied by command; Φ.43 merged into Φ.46 (protocols, files, rules) | #77 | [D07](D07.md) |
| D08 Codes without the dot | Φ12 = Φ.12 in search and merges | #87 | [D08](D08.md) |
| D09 Same folder, other code | aliases: such a folder is updated and keeps its code | #98 | [D09](D09.md) |
| D10 Real load with the files | *load* copies the server's files by the database and applies the folder list | #102 | [D10](D10.md) |
| D11 Files that are not attachments | counted apart; `--list` says why a file has no row | #103 | [D11](D11.md) |
| F09 CI and Docker build | retries and time-outs for the image build; time limits for the CI jobs | #99 | [F09](F09.md) |

## Found on the way
- **Privacy (R2f):** with *Περιορισμός λίστας* on, the ⚠ explanation named the protocols around it, even ones the
  user may not see. It now names only those the user may see.
- **D07:** a cell ending in «Π» broke the folder import (a byte-wise trim cut the letter in half); a folder written
  «Φ2.1» in the list was duplicated by an import with «Φ.2.1».
- **D10:** the real copy worked directly on the files copied from the server, so every test changed them; they are
  now copied first.
- **F09:** the image builds hung because Ubuntu's security server answered only some requests (not our code). The
  macOS jobs cancelled after 15 minutes are GitHub's runner queue limit.
- Review (Codex), among others:
  - R2e: a page past the last one built a reversed pager and could use up the memory;
  - R1k: an assigner without *Αποθήκευση* was stuck on the page after changing an assignment;
  - R3f: whole words missed accents stored as separate marks;
  - R5f: several users could ask the mail server at the same moment;
  - R2k: the search results hid the very file that matched.

  All fixed, with tests.

Your try-it steps for this batch are 31–60 and 62 in [OWNER-TODO.md](../../OWNER-TODO.md).
