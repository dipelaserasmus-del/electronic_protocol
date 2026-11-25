# R5s: a folder for every e-mail registered from the inbox

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R5s-inbox-folder-always` |

## What was asked (owner, 2026-11-25)
«In the inbox I was able to save an e-mail without setting a folder first (11334/2026). I shouldn't be able to do
that.»

## What was wrong
The inbox kept the old application's rule: a folder was needed **only when attachments were ticked** (their place in
the archive depends on it). An e-mail without attachments, or with every ✓ removed, was registered with no folder,
unless *Ρυθμίσεις → E-mail → «Ο Φάκελος χρειάζεται για κάθε e-mail»* was on (off by default). The folder picker even
offered *— Χωρίς φάκελο* first.

(The protocol itself was not looked up: the real database stays off-limits. The rule above is the only way the inbox
registers without a folder.)

## What changed
- **Every e-mail needs a Φάκελος**, with or without attachments, whichever button registers it (*Καταχώριση*,
  *Καταχώριση και απόδειξη*, *Ναι, καταχώριση*). The check is in the registration service, so nothing can skip it.
  The message: *Για να καταχωρίσετε ένα email επιλέξτε Φάκελο.* (with ticked files: *… email με συνημμένα αρχεία …*).
  As with R5r, the page goes to *Φάκελος* and says it above the buttons.
- The inbox's folder list no longer offers *— Χωρίς φάκελο*.
- The setting *Ο Φάκελος χρειάζεται για κάθε e-mail* is removed: it is always so now.
- **Not changed:** the protocol form keeps *— Χωρίς φάκελο*, for protocols on hold or placeholders, as you decided on
  2026-11-18. Protocols already registered without a folder (such as 11334/2026) stay as they are; open them and
  choose a folder.

## How verified
- PHPUnit `InboxTest::test_every_email_needs_a_folder`, with the old setting off:
  - an e-mail with its files unticked is refused, also with the receipt and with «register anyway»;
  - with ticked files: refused;
  - the service refuses a blank folder;
  - with a folder: registered in it;
  - the inbox picker has no «Χωρίς φάκελο»; the setting is not in Ρυθμίσεις.

  This test failed before. The other inbox tests now choose a folder before registering, as a user must: none of them
  asserts less than before. Full suite: 679 tests, green.
- Browser (`email-registration.spec.js`, R5c's test extended):
  - with the files unticked and no folder: refused with the message, nothing registered;
  - no «Χωρίς φάκελο» in the list;
  - with a folder: registered.

  The registration helpers and R5k's test choose a folder. The full browser suite passes.

## Owner actions
None. To try it: step 80 in [OWNER-TODO.md](../../OWNER-TODO.md).
