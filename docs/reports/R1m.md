# R1m: an ΑΔΑ without a file in Συνημμένα

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R1m-ada-attachment-row` |

## What was asked (owner, 2026-11-25)
In the protocol form, an ΑΔΑ added as an attachment showed the pill *ΑΡΧΕΙΟ*, the text *Μόνο ΑΔΑ*, and its link
on the far right. Move the link to the left, remove *Μόνο ΑΔΑ*, and make the pill say *ΑΔΑ*.

## What changed
- An attachment that is only an ΑΔΑ (no file) shows the pill **ΑΔΑ**, then **the code itself** on the left. It links
  to Διαύγεια when *Ρυθμίσεις → Διεύθυνση Διαύγειας* is set, and is plain text otherwise. *Μόνο ΑΔΑ* and the second
  ΑΔΑ on the right are gone; the ✕ stays at the end.
- A file that also has an ΑΔΑ is unchanged: its type and name, and the ΑΔΑ badge on the right.

![An ΑΔΑ without a file](img/R1m/r1m-ada-row.png)

## How verified
- PHPUnit `ProtocolFormTest::test_an_ada_only_attachment_shows_its_code_on_the_left`:
  - the ΑΔΑ row: the ΑΔΑ pill and the code linked to Διαύγεια, no «Μόνο ΑΔΑ», no ΑΡΧΕΙΟ, not twice;
  - a PDF with an ΑΔΑ: as before.

  Full suite: 689 tests, green.
- Browser (`attachments-panel.spec.js`, R1m): an ΑΔΑ added in the form; after saving, its row has the ΑΔΑ pill, the
  code right after it, no «Μόνο ΑΔΑ», and no badge with the code on the right.

## Owner actions
None. To try it: step 89 in [OWNER-TODO.md](../../OWNER-TODO.md).
