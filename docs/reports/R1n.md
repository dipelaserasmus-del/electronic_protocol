# R1n: fix: saving a new protocol asked «leave page?»

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R1n-save-leave-warning` |

## What was reported (owner, 2026-11-25)
Creating a new protocol, filling *Θέμα* and pressing *Αποθήκευση* stopped at the browser's question *This page is
asking you to confirm that you want to leave — information you've entered may not be saved*. It should not.

## What was wrong
The page asks before leaving with unsaved changes (R1k). A save turns that off while it runs, but two other things
turned it back on during the save:
- **any other request** coming back without a redirect, e.g. *Θέμα* itself, sent to the server when the field is left,
  i.e. with the click on *Αποθήκευση*; on a slower server (your copy) its answer arrives after the save has started;
- **any `input` event** in the form, also the page's own (a field it updates), not only what the user types.

When the save then went on to the saved protocol, the question was awake and the form still differed from how it
opened (a new protocol), so the browser asked.

## What changed
- Only **the save's own answer** decides: refused (errors), the question is back; «go to the saved protocol», it
  stays quiet whatever else happens meanwhile.
- Only **what the user types** turns the question back on after a refused save, not the page's own events.
- Everything else of R1k is unchanged: the red dot, our own question with the list of changes for links, the browser's
  question when closing or reloading with changes.

## How verified
- Browser (`protocol-form.spec.js`, R1n): a new protocol without a folder, *Θέμα* typed and *Αποθήκευση* pressed
  without leaving the field. The save is held back as on a slow server, and meanwhile the page fires `input` events of
  its own. It must reach the saved protocol with no question. On the old code the browser asked (the test failed);
  now it passes, twice.
- The rest of the form's browser tests (R1k's question and dot among them) and the attachments': 23, green. Full
  PHPUnit: green.

## Owner actions
None. To try it: step 90 in [OWNER-TODO.md](../../OWNER-TODO.md).
