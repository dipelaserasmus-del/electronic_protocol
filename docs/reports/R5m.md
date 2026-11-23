# R5m: inbox: loads at once, waits visibly, one registration per e-mail

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R5m-inbox-loading` |

## What was asked (owner, 2026-11-23)
The organisation's mail server is often overloaded, mostly in the early morning, and opening the inbox times out.
Users should see that the page is waiting for the mail server, and that a delay is not the application's fault. The
e-mail cache must be safe when several users work at once.

## What was there
- **The page waited in silence.** It connected to the mail server *before* the browser got any HTML. On a slow
  morning, users saw a blank page and a spinning tab for up to 30 seconds.
- **A failure always said the same.** It read *«Η σύνδεση … απέτυχε. Ελέγξτε τις ρυθμίσεις»*, whether the server was
  slow or the login was wrong, and it had no retry button.
- **Caching (R5a, kept):** each e-mail is downloaded once into a shared cache, and downloads into one mailbox take
  turns (a lock).
- **A gap found while checking multi-user safety:** with the same page open, two users could each register the same
  e-mail. The second user's cached copy stays readable until the next visit, so the second click made a second
  protocol. Only the "similar protocol" notice stood in the way, and *«Ναι, καταχώριση»* went past it.

## What changed
- **The page arrives at once.** `<livewire:email-inbox defer>` sends the page first, then connects in a second request.
  Meanwhile it shows:
  - a blue notice *«Σύνδεση με τον διακομιστή email (…)…»* with a seconds counter;
  - after 6 seconds, a note: *«Ο διακομιστής email αργεί να απαντήσει. Συμβαίνει συχνά, κυρίως τις πρωινές ώρες που
    είναι φορτωμένος: δεν είναι πρόβλημα της εφαρμογής. Η σελίδα περιμένει … έως 30 δευτερόλεπτα.»*

  The same notice (without the counter) shows while *Ανανέωση* or a page change waits for the server.

  ![Waiting for the mail server](img/R5m/r5m-waiting.png)
- **A failure says what happened** (`App\Support\Mail\MailboxFailure`), each with a **«Δοκιμή ξανά»** button and the
  reference code:
  - *slow* (yellow): *«Ο διακομιστής email (…) δεν απάντησε μέσα σε N δευτερόλεπτα. Είναι φορτωμένος … δεν είναι
    πρόβλημα της εφαρμογής. Δοκιμάστε ξανά σε λίγο.»*;
  - *login*: the server did not accept the account, so tell the administrator;
  - *other*: as before.
- **New setting `IMAP_TIMEOUT`** (default 30 seconds): how long the server is given to connect and for each reply. On
  very busy mornings, 60 gives it more time.
- **One registration per e-mail, whoever has it on screen** (`App\Services\Mail\InboxClaims`):
  - registering or marking an e-mail read holds a short lock on that e-mail;
  - a second user's click meanwhile says *«Άλλος χρήστης καταχωρίζει αυτή τη στιγμή αυτό το email…»*;
  - afterwards, a shared record keeps for 7 days what happened and who did it. Anyone who clicks that e-mail later
    sees *«Το email είχε ήδη καταχωριστεί (όνομα): δεν καταχωρίστηκε ξανά.»* with the protocol number, and nothing
    is registered again.

## How verified
- **PHPUnit, new `InboxLoadingTest`:**
  - the page arrives without contacting the server and shows the notice, counter, host and timeout;
  - with the setting off, it loads at once;
  - a timeout shows the *slow* message with a retry, and the retry then shows the e-mails;
  - a refused login and other failures each get their own message;
  - the waiting notice is present for *Ανανέωση* and page changes;
  - the failure kinds are classified correctly, including "empty response" after nearly the whole timeout.
- **PHPUnit, `InboxTest`:**
  - two users and the same e-mail: one protocol; the second user sees who registered it and its number, also with
    *«Ναι, καταχώριση»*;
  - after "read", another user's *Καταχώριση* does nothing;
  - while another user holds the e-mail, a click is told to wait, and it works once released.
- **Test fake corrected:** the fake mailbox in one test reused a UID for a different message. Real servers never do
  that within one UIDVALIDITY, so the fake now changes UIDVALIDITY when it does.
- **Full suite:** PHPUnit 579 tests pass.
- **Browser:**
  - new `inbox.spec.js` (R5m): the first request is held back for 8 seconds; the notice appears at once, the note
    appears after a few seconds with the counter running, then the e-mails;
  - the inbox specs now wait for the server's answer (`openInbox` / `inboxReady`). 30 inbox, registration, smoke and
    secure-file browser tests pass.

## Owner actions
None. To try it: step 55 in [OWNER-TODO.md](../../OWNER-TODO.md). If mornings are still too slow for 30 seconds, add
`IMAP_TIMEOUT=60` to the server's `.env` at deployment.

## Next (R5n)
While the server is slow, show the e-mails of the last check straight from the cache, so users can read them and fill
in the forms. Registering still needs the server, and the claim above keeps it to once per e-mail.
