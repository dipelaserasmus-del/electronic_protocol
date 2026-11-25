# R6h: fix: a folder written without the dot could not be saved

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R6h-folder-save-code-spelling` |

## What was reported (owner, 2026-11-25)
On *Φάκελοι και διατήρηση*, editing folder **Φ12** (choosing a user for Διεκπεραίωση) and pressing *Αποθήκευση*
showed: *Ο κωδικός χρησιμοποιείται σε 606 πρωτόκολλα και δεν αλλάζει. Για άλλον κωδικό προσθέστε νέο φάκελο.*

## What was wrong
The old application wrote some codes without the dot (Φ12). On save, the form tidies a code to the standard form
(Φ12 → Φ.12) and compared that with the stored «Φ12». They differed, so the save took it for a **rename**, which is
refused for a folder in use. Nothing was renamed: since D08, Φ12 and Φ.12 are the same folder. Two more checks would
have refused it next: the code pattern («Φ.» and a number) and «another folder already has this code» (a copy can have
both spellings, as the demo data does).

## What changed
- When the code in the form is **the stored code in another spelling** (Φ12, Φ.12, φ12, «Φ 12»), the folder keeps its
  code **as stored** and the code is not checked as a new one. The rest of the form (description, retention, remarks,
  automatic assignments) is saved as usual.
- A really different code for a folder in use is still refused, with the same message.
- Since the code is kept as written, the folder's protocols (which carry «Φ12») still find it.

## How verified
- PHPUnit `FolderAdminTest::test_a_folder_written_without_the_dot_is_saved_when_in_use`:
  - a folder «Φ12» (Κατά κρίση, remarks), used by protocols;
  - edit, choose a user for Διεκπεραίωση, save: no error, the code is still «Φ12», the assignment saved;
  - typed as «Φ.12», «φ12», « Φ 12 »: saved, the code unchanged;
  - «Φ.121»: refused as before.

  The test failed before the fix with the message you saw. The other folder tests (rename / delete rules, glossary,
  merges, codes): 54 green. Full suite: 685 tests, green.
- Browser: the demo data has no folder written without the dot, so the browser cannot show this case. The
  PHPUnit test drives the same page component (Livewire) that the browser uses.

## Owner actions
Try it on your copy: step 86 in [OWNER-TODO.md](../../OWNER-TODO.md).
