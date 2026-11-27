# R2u: list: a file's 👁 before its name, its size after it; filter tooltips; the fixed head blends with the page

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R2u-list-preview-first` |

## What was asked (owner, 2026-11-27)
- «In the main protocol screen the preview icon should be moved before the filename for consistency reasons.»
- «The part of the screen that stays frozen does not blend completely with the background. Can we fix that?»
- Then: «add your explanation for Δικά μου vs Καταχωρίσεις as a tooltip on the buttons» and «move the file size just
  after each filename and dim it» (less word-wrapping in the names).

## What changed
- **A file's line in the list** is now, as in the form: its type (e.g. PDF, 27 KB), 👁, its name.
  - The name is the link that opens the file, as before.
  - The type is a link too, for the mouse; TAB passes only the 👁 and the name.
  - A long name still shortens; the type and 👁 keep their size.
- **The size** follows the name, dimmed (e.g. *Πρόσκληση.txt* `16 B`); the type before 👁 is the type alone. The name
  gets the room the size took.
- **The filters say what they show** (hover):
  - *Δικά μου*: those you registered **or** that are assigned to you (for handling or for information);
  - *Καταχωρίσεις*: only those you registered yourself.

  With a user chosen next to them, the same for that user. *Όλα*, *προς Διεκπεραίωση*, *προς Ενημέρωση* and
  *Διεκπεραιώθηκε* got theirs too.
- **The fixed head** (R2t) had the white of the cards, so it showed as a lighter band over the page's light grey. It now
  has the page's own grey (theme colour), so only its contents show.

![A file's line](img/R2u/r2u-file-line.png)

![The fixed head, scrolled](img/R2u/r2u-head.png)

## How verified
- Browser (`protocol-list.spec.js`, R2u):
  - a saved protocol's line in the list: type, then 👁, then the name, from left to right;
  - the size after the name, in a dimmer colour, and none in the type;
  - the tooltips of *Δικά μου* and *Καταχωρίσεις*;
  - the head's background equals the page's, and it is fixed when scrolled.
- PHPUnit `ProtocolListTest::test_the_filters_explain_themselves_and_the_size_follows_the_name`: a tooltip for every
  filter, the texts of the two, the size right after the name's link, the type alone.

  The R2b test now picks the name's link. The list and preview specs are green (19).
- PHPUnit list and preview tests green (only the view and CSS changed).

## Owner actions
None. To try it: step 115 in [OWNER-TODO.md](../../OWNER-TODO.md).
