# R4g: the old stamp in files saved before (investigation)

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R4g-old-stamps` (on top of R4f) |

## The question (owner, 2026-11-23)
Since R4f the stamp is put when a file is shown, and files saved before carry the old stamp inside them (they are
shown as stored). Can that old stamp be taken out safely, so that those files too get the new stamp, or none?

## What was found
The old tool (markpdf, built on the UniDoc library) was run again here on synthetic PDFs (LibreOffice, Ghostscript,
pikepdf, one, three and rotated pages) and its output was read object by object:

- **PDFs: yes, safely.** The tool rewrites the whole file (its *Producer* says `UniDoc …`) and adds **exactly one
  thing** to the end of every page's drawing:
  `q /GS0 gs 1 0 0 1 X Y cm W 0 0 H 0 0 cm /Img1 Do Q`, where `Img1` is **one** image of W × H pixels, the same on
  every page (the tool drew one pixel per point). Nothing else on the page is changed. Taking that last block out
  gives the page as it was: the drawings of the files before the stamp and after taking it out are **identical,
  pixel for pixel** (pdftoppm, every page, also rotated ones). The bytes are not those of the file first uploaded (the
  tool rewrote it, and that file is gone), but what the page shows is.
- **It must be recognised strictly**, so that nothing else is ever touched: the file was written by UniDoc; every page
  ends with that block; the block draws the image at exactly its pixel size; all pages use the same image. Anything
  else is left as it is (and said why).
- **Word files (docx, odt): no.** The old tool opened them with PhpWord and saved them again: the file as uploaded is
  not there to go back to, and taking the picture out of the header would not undo the other changes of that re-save.
  They stay as they are (shown as stored, with the note of R4f).
- Also seen: the old tool ignored page rotation (on a rotated page the stamp went to the wrong place, sideways), and
  it could only stamp every page.

## What changed
- `App\Services\Stamp\Pdf\OldStampRemover`: `analyse` (stamped or not, and why) and `remove` (the drawing of every
  page given back without the block, the image and its graphics state taken out of the page's resources; written as
  an update after the file's bytes, marked `%EP-UNSTAMP`). R4e's update writer is now shared
  (`IncrementalUpdate`).
- **An owner-run command** (nothing runs by itself):
  - `php artisan stamp:old` — counts of the files saved before R4f: PDFs where the old stamp comes out, PDFs without
    an old stamp (they never went through the tool), PDFs left as they are (encrypted, another form), Word files,
    files missing from the disk. `--list`: one line per file (number and reason; no names).
  - `php artisan stamp:old --apply` — takes the old stamp out of the PDFs that have it (the file as it was kept in
    `storage/app/stamp-originals/`, same path) and marks those and the PDFs without an old stamp as unstamped, so
    they follow *Με σφραγίδα* (R4f) like new files. Running it again does nothing more.

## How verified
- PHPUnit `OldStampsTest` (4): an old-stamped PDF (classic table and cross-reference streams, 3 pages) is recognised,
  the block and the image go from every page, the page's own drawing and fonts stay, the original bytes stay first,
  and it is not found again; exactly that stamp only (not UniDoc, not on every page, drawn at another size,
  unreadable); a PDF stamped by the **real old tool** (shipped with the application) recognised and cleaned; the
  command's report, `--apply` (original kept, flags, size and SHA-256 updated, a PDF without old stamp unchanged, Word
  untouched), and a second run. Full suite: green.
- Locally: the tool's output on LibreOffice / pikepdf PDFs (one and three pages, rotations 0°, 90°, 270°) cleaned and
  drawn with pdftoppm: identical to the files before the stamp; opened without errors by pikepdf.

## Owner actions
- Optional, on your real copy (8081), when you want the old files to follow the new stamp:
  `./dev.sh real artisan stamp:old` (counts only), then `./dev.sh real artisan stamp:old --apply`. Tell me the counts
  (numbers only), especially *left as they are*. In the deployment guide: the same on the server after the move.
