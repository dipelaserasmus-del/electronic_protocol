# R4m: the old server never stamped: no old file is marked as stamped

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R4m-no-old-stamps` |

## What was asked (owner, 2026-11-26)
On the real copy, a PDF said *η σφραγίδα είναι μέσα στο αρχείο*, but it has no stamp, and *Με σφραγίδα* changed
nothing. «In our production server we did not have the stamp function enabled, so assume that none of our files has
been stamped. This needs to be taken into account for the migration.»

`stamp:old` on the real copy (counts only):

| | |
|---|---|
| PDF with the old stamp | 0 |
| PDF without | 5336 |
| PDF left as they are (encrypted, other form) | 183 |
| Word (docx, odt) | 164 |
| missing from the disk | 2 |

## Why it happened
Since R4f, the stamp is put on a file when it is shown, downloaded or printed. The old application wrote it into the
file when it was saved, and only with its setting *putStamp* on.

The R4f migration could not tell which old files had a stamp. So it marked every existing PDF, docx and odt as «stamp
inside», to be shown as stored and never stamped twice. On a server that never stamped, every one of those marks is
wrong. The counts above confirm it: not one PDF carries the old stamp.

## What changed
- **The migration** marks old files only if the old application stamped at all, i.e. its setting *putStamp* is on
  (no setting: off). On the owner's server it marks none, so after the move every old file follows *Με σφραγίδα*
  like a new one.
- **`php artisan stamp:old --none-stamped`** takes every mark away (PDF, Word, unreadable and missing files alike). It
  is for a database where the migration already ran, like the real copy. It reads and changes no file; running it
  again changes nothing.
- **The deployment list** in [OWNER-TODO.md](../../OWNER-TODO.md) (*Final deployment*): check with `stamp:old`, and
  `--none-stamped` if any file is still counted. It replaces «stamp:old, then --apply».

## How verified
- PHPUnit `OldStampsTest::test_when_the_old_server_never_stamped_no_file_is_marked`:
  - the migration with *putStamp* missing, `0` and `1`: marks only with `1`;
  - `--none-stamped`: both files unmarked, the PDF's bytes unchanged, no copy made, a second run clears 0.

  Full suite green.
- No screen changed; a file without the mark already follows *Με σφραγίδα* (R4f's browser tests).

## Owner actions
- On your real copy, once: `./dev.sh real artisan stamp:old --none-stamped`. Then your PDF gets the stamp with *Με
  σφραγίδα* on, and the note *η σφραγίδα είναι μέσα στο αρχείο* is gone. Step 103.
- At the move: the step in *Final deployment* (OWNER-TODO).
