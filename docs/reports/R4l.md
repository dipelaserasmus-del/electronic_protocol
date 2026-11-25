# R4l: the notice after deleting a protocol

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R4l-trash-message` |

## What was asked (owner, 2026-11-25)
After deleting a protocol the green notice said *Το πρωτόκολλο 11339/2026 μεταφέρθηκε στον κάδο. Ο αριθμός του είναι πάλι
ελεύθερος· επαναφέρεται από τον Κάδο.* Keep only the first sentence, and make «κάδο» a link to the trash.

## What changed
- The notice says **Το πρωτόκολλο N/YYYY μεταφέρθηκε στον κάδο.** and **κάδο** opens *Κάδος → Πρωτόκολλα*.
- The page's notices can now carry one link, and only to a page of this application. The text itself is still shown
  as text, so a notice can never carry other HTML.

![The notice](img/R4l/r4l-trash-notice.png)

## How verified
- PHPUnit `ProtocolTrashTest` (the form's delete): the notice on the list page is exactly the sentence with «κάδο»
  linking to `/trash?tab=protocols`, without «πάλι ελεύθερος» or the marker; a notice whose link is not a page of
  this site shows no link. Full suite: 689 tests, green.
- Browser (`protocol-trash.spec.js`): after *Διαγραφή* the notice's text is exactly the sentence, and its «κάδο»
  links to the trash's protocols.

## Owner actions
None. To try it: step 91 in [OWNER-TODO.md](../../OWNER-TODO.md).
