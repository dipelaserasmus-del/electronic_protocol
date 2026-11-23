# R5n: inbox: the last check's e-mails at once, from the cache

| | |
|---|---|
| Status | ✅ Done |
| Branch | `task/R5n-inbox-from-cache` |

## What was asked (owner, 2026-11-23)
The inbox times out on busy mornings, the main reason for caching e-mails. The cache must be safe for several users.
After R5m the page appeared at once, but it was empty until the mail server answered.

## What changed
- **A shared snapshot** (`Inbox::snapshot` / `rememberSnapshot`). After every successful check, by any user, the app
  keeps the inbox's list of e-mails: mailbox, UIDVALIDITY, the UIDs, and the time. It is one read-only record for all
  users; the e-mails themselves are already in the shared cache (R5a).
- **The page is drawn from the snapshot, without the mail server.** `EmailInbox::mount()` shows the e-mails of the last
  check from the cache, with a banner:

  *«Σύνδεση με τον διακομιστή email (…)… Παρακαλώ περιμένετε… N δευτ. — Μέχρι τότε βλέπετε τα email της τελευταίας
  ανανέωσης (10:55).»*

  Users can read them and start filling in the forms. Once the page is in the browser, it asks the server
  (`connect()`, `wire:init`), and the fresh list replaces the old one. What was typed into a form stays.

  ![The last check's e-mails while connecting](img/R5n/r5n-from-cache.png)
- **Without a snapshot** (first visit ever, or another account), only the banner shows, as in R5m. It never says
  *«Δεν υπάρχουν εισερχόμενα»* while waiting.
- **If the server fails, the e-mails stay**, with *«Τα email που βλέπετε είναι της τελευταίας ανανέωσης (10:55): μπορεί
  να μην είναι τα τρέχοντα.»* This holds for the first connection, *Ανανέωση* and page changes. A failed page change
  keeps the page number of the e-mails shown. A *504* from the web server: the banner says it, and *Δοκιμή ξανά*
  connects again.
- **Multi-user safety:**
  - The snapshot is only a list. Each user reads it, and whoever checks next replaces it in one write.
  - E-mails registered or read since the snapshot (`InboxClaims`, R5m) show as done in the first view, with who did
    it, so nobody starts on them.
  - Registering still needs the mail server, and stays once per e-mail.
  - Review fix: if the server renumbers the mailbox (a new UIDVALIDITY), forms typed into the cached view are
    dropped, because a reused UID may now be another e-mail. A form keeps its text only for the very same e-mail.
  - An e-mail no longer in the cache is left out of the first view. One registered meanwhile says *«δεν είναι πια
    διαθέσιμο»*.
- **The cached view never changes the page asked for** (`?page=3`). Only the server's answer may, as before. The first
  browser run caught this: the cached view jumped to page 1.
- **R5m's deferred placeholder is gone.** The component itself draws the first view. `config imap.deferred_inbox` now
  means "draw from the snapshot, then connect".

## How verified
- **PHPUnit, `InboxLoadingTest`:**
  - no snapshot: the banner only, nothing from the server, never "empty"; `connect()` shows the e-mails, once only;
  - a snapshot taken by one user is shown at once to another, from the cache, with the time of the last check
    (Europe/Athens);
  - the server failing on `connect()` keeps the e-mails with the "may not be current" line; *Ανανέωση* then shows the
    fresh list and time;
  - the first view keeps `?page=3`, also after `connect()`;
  - an e-mail registered since the snapshot shows as done, is not registered again, and is gone after `connect()`;
  - a renumbered mailbox (new UIDVALIDITY) does not keep the old e-mail's form for a reused UID.
- **Full suite:** PHPUnit 586 tests pass.
- **Browser:**
  - new R5n test: after a check, reload with the server's answer held back. The same e-mails appear at once with the
    stale note and the state *connecting*, then the fresh list without the note;
  - R5m's 504 test now has *Δοκιμή ξανά* connect again (no page reload);
  - 33 inbox, registration, smoke and secure-file browser tests pass.

## Owner actions
None. To try it: step 57 in [OWNER-TODO.md](../../OWNER-TODO.md).
