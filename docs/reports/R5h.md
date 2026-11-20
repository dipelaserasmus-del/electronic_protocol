# R5h: inbox: going to an e-mail from the list stays on it

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R5h-inbox-scroll` (on top of R2k) |

## What was reported (owner, 2026-11-20)
Clicking an e-mail in the list on the left scrolls the page to it. Sometimes the page reaches the right e-mail, then
suddenly jumps back up a few pixels. It seemed random; maybe the focus on the folder field?

## What was found
Two causes, both acting just after the scroll:
1. **The folder field.** On arrival, the page put the focus in the e-mail's first field: the folder picker. Focusing
   it opens its list, and the list kept its highlighted folder in view with a browser call that scrolls **every**
   scrollable thing around it, the page included. So the page moved while it was still scrolling to the e-mail.
   - This happened only when the list had a highlighted folder, which made it look random.
2. **E-mails shown as HTML.** Their frames load late and are then resized to their content. One above the e-mail
   changes the page's height after the scroll and moves the e-mail up or down.

## What changed
- The folder list scrolls only itself, never the page. This holds on the protocol form too.
- Going to an e-mail focuses the e-mail itself, not its first field. The folder list no longer opens on its own.
  Keyboard users start at the e-mail, which is outlined.
- For 2.5 seconds after arriving, the page stays on the e-mail if something above it changes height. Any scrolling,
  click or key of the user ends this at once, so the user's own scrolling is never undone.

## How verified
- Browser (`inbox.spec.js`, R5h): go to the last e-mail of the page from the left-hand list. While and after the
  scroll, the first e-mail grows by 300px and then shrinks, as a late HTML frame does. Checked:
  - the e-mail stays at the top;
  - the folder list is closed;
  - the e-mail has the focus, and the address names it;
  - scrolling with the wheel afterwards is not undone.

  It failed before: the e-mail ended 120px away.
- Inbox and protocol form browser tests (they use the same folder picker): 25 of 25.
- Full PHPUnit: 555 tests pass.

## Owner actions
None. To try it: step 49 in [OWNER-TODO.md](../../OWNER-TODO.md).
