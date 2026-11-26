# R1o: the time left to change a protocol, readable

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R1o-time-left-readable` |

## What was asked (owner, 2026-11-26)
«*Μπορείτε να αλλάξετε το πρωτόκολλο για ακόμη 5936 λεπτά* is not always helpful. Let's make the units human
readable, e.g. 2 μέρες, 4 ώρες, 3 λεπτά.»

## What changed
- The notice now says, for example, *Μπορείτε να αλλάξετε το πρωτόκολλο για ακόμη 4 μέρες, 2 ώρες, 56 λεπτά.*
  (that is 5936 minutes).
  - Parts that are 0 are left out (*4 μέρες, 4 ώρες.*).
  - The singular is used for one (*1 μέρα, 1 ώρα, 1 λεπτό*).
  - It is rounded up to the minute, so it never shows less time than is left.
- `App\Support\Duration::text()`, usable elsewhere.
- On the way: [docs/RELATED-NUMBERS.md](../RELATED-NUMBERS.md) also names `App\Support\RelatedNumbers` (the form's
  pills), so it is not confused with the R6l service of the same name.

![The notice after saving, with 6000 minutes allowed](img/R1o/r1o-time-left.png)

## How verified
- PHPUnit:
  - `DurationTest`: 5936 minutes, singulars, zero parts left out, rounding up;
  - `ProtocolFormTest::test_the_time_left_to_change_it_is_readable`: a writer's own protocol, changed 64 minutes ago
    with 6000 allowed, shows *4 μέρες, 2 ώρες, 56 λεπτά*.
- Browser (`protocol-form.spec.js`, R1o): with 6000 minutes allowed, a writer saves a protocol and sees *για ακόμη 4
  μέρες, 4 ώρες.*

## Owner actions
None. To try it: step 100 in [OWNER-TODO.md](../../OWNER-TODO.md).
