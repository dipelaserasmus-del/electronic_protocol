# Ιστορικό ενεργειών (audit log)

Last update: 2026-11-28 12:15 (X01: recording; X02 adds the page)

The application writes down **who did what and when**: every protocol, attachment, assignment, folder, user and
setting created, changed or deleted, every sign-in, download and e-mail registration. Nothing in the log can be changed
or deleted from the application; old entries leave only by the retention you choose.

- [For administrators](#for-administrators): what is recorded, how long it is kept.
- [For developers and AI assistants](#for-developers-and-ai-assistants): where the code is, how a change is recorded,
  what is not, the rules the code keeps.

## For administrators

**Where to see it:** *Διαχείριση → Ιστορικό* and the *Ιστορικό* of each protocol come with X02. Until then the log is
written but not shown.

### What is recorded

Each entry: **when**, **who** (the user name as it was then, kept even if the user is renamed or deleted), **from
where** (the computer's IP address), **what** (the action and what it is about) and, for a change, **each field's old
and new value**.

| What | Actions |
|---|---|
| Protocols | created (with every field written), changed (only the fields that changed, old → new), to the trash, back from the trash (with the old number if it got a new one), deleted for good |
| Attachments | added, changed (e.g. renamed, the stamp's place on the file), to the trash, back, deleted for good, **downloaded** (and whether with the stamp) |
| Assignments of a protocol (Διεκπεραίωση / Ενημέρωση) | added, changed, removed |
| Folders and their automatic assignments | created, changed, deleted; a **merge** of two folders as one entry (how many protocols and files moved) |
| Users | created, changed, deleted. The **password is never written**: only that it changed (`***`) |
| Settings (*Ρυθμίσεις*, the stamp's design) | the setting and its old → new value |
| Sign-in | signed in, signed out, **failed sign-in** (the user name typed, never the password) |
| E-mail | registered from the Inbox (with how many files), besides the protocol's own «created» entry |

Not recorded: what users only **look at** (the list, a search, a preview), their own view preferences (list order,
inbox look) and the application's own bookkeeping (e.g. the date of the trash's last clean-up). What the application
does on its own, like the trash's daily clean-up, is recorded **without a user**, marked automatic.

### How long it is kept

`AUDIT_KEEP_DAYS` in the server's `.env`: entries older than this many days are removed once a day. **Empty or 0
(the default) keeps everything.** Example: `AUDIT_KEEP_DAYS=1825` keeps five years. It is in `.env`, not in
*Ρυθμίσεις*, because it is a decision about the records, made once.

## For developers and AI assistants

### Where the code is

| Part | File |
|---|---|
| Table `audit_log` (no foreign keys: an entry outlives its user and protocol) | `database/migrations/2026_11_28_000001_create_audit_log_table.php` |
| An entry (append-only: updating or deleting one through the model throws) | `app/Models/AuditEntry.php` |
| Writing entries, the system mode, the retention | `app/Services/Audit/AuditLog.php` (`record()`, `protocol()`, `asSystem()`, `purgeExpired()`) |
| Changes of a model, by themselves | `app/Models/Concerns/Audited.php`, used by `Protocol`, `Attachment`, `ProtocolAssignment`, `FolderAssignment`, `Keepvalue`, `User`, `Config` |
| Sign-ins | `app/Listeners/AuditAuthEvents.php`, registered in `AppServiceProvider` |
| Written by hand | `Attachments\Trash::trash()` (saved quietly), `Protocols\ProtocolTrash` (trash, restore, purge: queries), `ProtocolController::download()`, `Mail\EmailRegistration::register()`, `Folders\FolderMerge::apply()` (a query for all protocols), `StampPlaceController::update()` (a file's stamp place, saved quietly: no re-hashing) |
| Retention | `config('app.audit_keep_days')` ← `AUDIT_KEEP_DAYS`; daily in `PurgeTrashDaily` (with the trash) and `php artisan audit:purge` (scheduled in `routes/console.php`) |
| Tests | `tests/Feature/AuditLogTest.php`; also `EmailRegistrationTest` (e-mail), `FolderGlossaryTest` (merge) |

### An entry

`action` (`AuditLog::ACTIONS`), `entity_type` + `entity_id` (what it is about), `protocol_id` (the protocol it belongs
to: X02's per-protocol history), `changes` (`{field: [old, new]}`; a created row: `[null, value]` for each non-empty
field; a deleted one: `[value, null]`), `context` (what helps to read it: `ref` «8/2026», a file `name`, a setting
`key`, `automatic`), `user_id` + `username`, `ip`, `created_at`.

### The rules the code keeps (tests check them)
- **Append-only:** the application never updates or deletes an entry; only `purgeExpired()` removes old ones, with a
  query, and only when `AUDIT_KEEP_DAYS` > 0.
- **Never a secret:** `User::auditMasked()` = password (`***`); a failed sign-in keeps only the typed user name.
- **Only real changes:** an update records only changed fields; `id`, `created_at`, `updated_at` never; a save that
  changes nothing writes nothing.
- **Who did it:** the signed-in user, except inside `AuditLog::asSystem()` (the daily clean-ups: no user,
  `automatic: true`) and the sign-in events (the user signing in or out; a failed one: nobody).
- **A failed write never stops the user's work:** `record()` catches and reports (laravel.log).

### Before changing it
- **Model events only.** `DB::table(...)`, `Model::where(...)->update()/delete()` and `saveQuietly()` write nothing by
  themselves. A new place that changes what a user did that way must call `AuditLog::record()` (as `ProtocolTrash`
  and `FolderMerge` do). Data fixes run by the administrator from the command line (`attachments:fill-metadata`,
  `stamp:old`, `folders:glossary`, migrations) are recorded only where they go through the models.
- Replace related rows **row by row** (delete what was removed, update what changed, create what is new), as
  `FolderCatalog::setAssignments()` does (review of X01): deleting all and creating them again records nothing for a
  removal and a «created» for every unchanged row.
- A new model whose changes matter: `use Audited;` and, if it belongs to a protocol, `auditProtocolId()`.
- A setting the application writes for itself goes in `Config::NOT_AUDITED`.
- The log grows with every save and download: X02 reads it by `created_at`, `user_id`, `action`, `protocol_id`
  (indexed).
