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
  Meanwhile a blue banner shows *«Σύνδεση με τον διακομιστή email (…)… Παρακαλώ περιμένετε…»* with a seconds counter.
  The same banner (without the counter) shows while *Ανανέωση* or a page change waits.

  ![Waiting for the mail server](img/R5m/r5m-waiting.png)
- **«Τελευταία ανανέωση: ΩΡΑ»** next to *Ανανέωση* (owner's note): when the e-mails last came from the mail server. A
  failed refresh keeps the last good time.
- **A web server time-out is readable** (owner's note, after a *504 Gateway Time-out* on a slow morning):
  - nginx gave PHP 60 seconds, but the IMAP library allows up to 30 seconds for *each* step (connect, login, list,
    fetch). On a busy server the steps added up to more than 60 seconds, so nginx cut the request off, and Livewire
    showed nginx's error page in a dark popup.
  - nginx now waits up to 180 seconds (`fastcgi_read_timeout`), so the application's own message gets through.
  - If the web server still gives up (502/503/504) or nothing comes back, `resources/modern/inbox.js` stops Livewire's
    popup. The banner itself turns yellow: *«Ο διακομιστής email δεν απάντησε εγκαίρως. Δοκιμάστε ξανά σε λίγο.»*
    with *Δοκιμή ξανά*. On an already loaded page, the same message appears above the e-mails.

  ![A time-out, said in the banner](img/R5m/r5m-timeout.png)
- **The application's own failures say what happened** (`App\Support\Mail\MailboxFailure`), in the same place,
  each with **«Δοκιμή ξανά»** and the reference code:
  - *slow* (yellow): *«Ο διακομιστής email (…) δεν απάντησε μέσα σε N δευτερόλεπτα. Δοκιμάστε ξανά σε λίγο.»*;
  - *login*: the server did not accept the account, so tell the administrator;
  - *other*: as before.
- **New setting `IMAP_TIMEOUT`** (default 30 seconds): how long the server is given to connect and for each reply.
- **One registration per e-mail, whoever has it on screen** (`App\Services\Mail\InboxClaims`):
  - registering or marking an e-mail read holds a short lock on that e-mail;
  - a second user's click meanwhile says *«Άλλος χρήστης καταχωρίζει αυτή τη στιγμή αυτό το email…»*;
  - afterwards, a shared record keeps for 7 days what happened and who did it. Anyone who clicks that e-mail later
    sees *«Το email είχε ήδη καταχωριστεί (όνομα): δεν καταχωρίστηκε ξανά.»* with the protocol number, and nothing
    is registered again.
  - Review fix: an e-mail registered whose move failed (left in the inbox on purpose) can still be moved out with
    *Αναγνωσμένο*, as before. It is never registered again.

## How verified
- **PHPUnit, new `InboxLoadingTest`:**
  - the page arrives without contacting the server and shows the banner, the counter, the host and the hidden
    time-out message;
  - with the setting off, it loads at once;
  - a timeout shows the *slow* message with a retry, and the retry then shows the e-mails;
  - a refused login and other failures each get their own message;
  - the waiting notice and the time-out box are present for *Ανανέωση* and page changes;
  - *Τελευταία ανανέωση* shows the time in the configured time zone, and keeps it after a failed refresh;
  - the failure kinds are classified correctly, including "empty response" after nearly the whole timeout.
- **PHPUnit, `InboxTest`:**
  - two users and the same e-mail: one protocol; the second user sees who registered it and its number, also with
    *«Ναι, καταχώριση»*;
  - after "read", another user's *Καταχώριση* does nothing;
  - while another user holds the e-mail, a click is told to wait, and it works once released;
  - registered but not moved: *Αναγνωσμένο* moves it later, and it is not registered again.
- **Test fake corrected:** the fake mailbox in one test reused a UID for a different message. Real servers never do
  that within one UIDVALIDITY, so the fake now changes UIDVALIDITY when it does.
- **Full suite:** PHPUnit 580 tests pass.
- **Browser:**
  - new `inbox.spec.js` (R5m) tests:
    - the first request is held back; the banner appears at once with the counter running, then the e-mails and
      *Τελευταία ανανέωση*;
    - an nginx *504* page for the first request: a yellow banner with the message, no popup, and *Δοκιμή ξανά*
      loads the e-mails;
    - a *504* on *Ανανέωση*: the message appears above the e-mails, which stay, and *Δοκιμή ξανά* works;
  - the inbox specs now wait for the server's answer (`openInbox` / `inboxReady`). 32 inbox, registration, smoke and
    secure-file browser tests pass.

## Owner actions
None. To try it: steps 55–56 in [OWNER-TODO.md](../../OWNER-TODO.md). If mornings are still too slow for 30
seconds, add `IMAP_TIMEOUT=60` to the server's `.env` at deployment. The nginx change comes with the new version's
Docker stack; the old server is not touched.

## Next (R5n)
While the server is slow, show the e-mails of the last check straight from the cache, so users can read them and fill
in the forms. Registering still needs the server, and the claim above keeps it to once per e-mail.
