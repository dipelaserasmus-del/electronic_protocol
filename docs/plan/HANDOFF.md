# Hand-off log

Read this first when (re)starting work. Update it at the end of every task.

## Current state — 2026-11-24 18:40 (after Batches 1–9, 9.5 and 9.6; next: Batch 10 when the owner says so; times are Greek time)
- **Batch 9.6 complete (2026-11-24):** R3h #105, R3i #106, R4d #107, R4c #108, R4e #109, R4f #110, R4g #111, R4h #112,
  R5p #113, N05 #114, R6e #115 (summary [`docs/reports/BATCH-9.6.md`](../reports/BATCH-9.6.md); Batch 9.5: [`BATCH-9.5.md`](../reports/BATCH-9.5.md)). **Open with the owner:**
  R5p envelope look (A/B/C, setting `inboxEnvelope`; then drop the other two), R4c browser libraries (only if wanted).
  The owner's official stamp lives only in `local-data/stamp/` (template.json + emblem.png from the public-domain
  Wikimedia SVG; never commit anything from `local-data/official stamp.*`); loaded by the owner with `stamp:import`.
- **No new dependencies without the owner (2026-11-24):** `npm install` of new packages was refused by the session's
  auto-mode check (R4c). Prefer libraries already in composer.json / package.json; ask the owner before adding one.
- **R4c:** `App\Services\Attachments\Preview` (kind: pdf/image from `/download`, text/word/sheet converted by
  PhpWord / PhpSpreadsheet at `/preview/{id}`, `/previewEmailAttachment/{uid}/{key}`; CSP `sandbox; default-src 'none'`;
  PhpWord output escaping on; sheets 500×50, formulas = saved values). `<x-preview-button>` + `partials/preview-modal`
  (one per page) + `resources/modern/preview.js` (groups, ‹ ›, arrows). `ATTACHMENT_PREVIEW_MAX_KB` (10 MB).
- **R4e:** `App\Services\Stamp\{StampTemplate (setting stampTemplate, JSON; emblem storage/app/stamp/emblem.png),
  StampRenderer (GD, PRINT_DPI 600), Pdf\{PdfReader, PdfParser, PdfStamper, IncrementalUpdate}}`: PDFs stamped by an
  incremental update after `%EP-STAMP` (classic + stream xref, ObjStm, PNG predictors, /Rotate, CropBox, first page
  only); markpdf (1 px = 1 pt, all pages) only as fallback with an A4-wide canvas + `-w`. Word: first-section header
  (FIRST), absolute position in pt. `/stamp` (StampDesigner: admin, assigner, writer). `stamp:import <dir> [--preview]`.
  `configs.value` TEXT. Tests build PDFs with `tests/Support/PdfBuilder`.
- **R4f:** files stored unstamped; `App\Services\Stamp\StampedCopy::pathFor` serves `storage/app/stamped/<id>-<key>.<ext>`
  (key: template hash, number, year, date, path, sha256) unless the user preference `stamped` = no or `?original=1`;
  `attachments.stamp_in_file` (all pre-R4f pdf/docx/odt) never stamped again. `<x-stamp-toggle>` (form + preview
  modal). «Χωρίς σφραγίδα» removed.
- **R4g:** `Pdf\OldStampRemover` (UniDoc producer + `q /GSn gs … cm W 0 0 H 0 0 cm /ImgN Do Q` tail on every page, one
  image W×H px); `stamp:old [--list] [--apply]` (originals in storage/app/stamp-originals/). Word stays.
- **R4h:** `App\Services\Protocols\ProtocolTrash` + `trashed_protocols` (data: protocol row, assignments, attachment
  ids). `ProtocolWriter::delete` → trash. Restore: same id, original number if free else `next(yearInUse)` under the
  numbering lock + note. `mayRestore`: admin all; writer/assigner own (`user_id`). TrashPage tabs (`tab`, `q`);
  daily purge (`PurgeTrashDaily`, `attachments:purge-trash`) purges protocols first. FolderMerge updates snapshots.
- **R5p:** `.inbox-envelope-{airmail|paper|plain}` (setting `inboxEnvelope`), strip `.inbox-envelope-n/-from/-date`;
  `.inbox-sep` gone.
- **N05:** `user_notes` + `NotesController` (GET/POST `/notes`, version, 409 + `force`); `resources/modern/notes.js`
  (escape-first markdown, http/https/mailto only; `toggle` handler changes state only on a real change);
  `partials/notepad` in layouts.modern; `body.has-notepad` keeps a 2.25 rem strip ≥ lg.
- **R6e:** `App\Services\Logs\LogReader` (whitelist `laravel(-date)?.log`, per-day byte-range index of laravel.log
  cached by inode+size, 8 MB/day cap), `App\Livewire\LogViewer` at `/logs` (admin). `LOG_DAILY_DAYS`.
- **D08 (owner, 2026-11-20): folder codes — the dot after Φ is optional and never counts** (the old app wrote both
  `Φ.2` and `Φ12`). Always compare codes with `App\Support\FolderCode` (key/same/startsWith; browser twin `folderKey`
  in tags.js), never as plain strings, and look at the codes the **protocols** carry, not only the list's
  (`ProtocolSearch::protocolCodes`/`allCodes`; `FolderMerge::codes`/`listed`/`intoCode`). Dots inside the number count.- **D07 (owner, 2026-11-19): folder glossary.** The owner's official folder list = `database/data/folders.json`
  (+ generated `docs/FOLDERS.md`, test-checked) — **treat it as the folders glossary/dictionary**. `php artisan
  folders:glossary` (preview) / `--apply` (`App\Services\Folders\FolderGlossary`, via `FolderCatalog::parseRecords /
  importRecords`: descriptions only). `merges`: **Φ.43 → Φ.46** (`App\Services\Folders\FolderMerge`: protocols,
  attachment files arxeio/ + trash/, rules; Φ.43 removed). **Owner: apply this merge in every later data migration /
  the deployment** (in OWNER-TODO's deployment steps). Sub-folders Φ.43.x are only named (ask the owner). Import fixes:
  cells trimmed by characters (trim() cut Π = CE A0), codes matched normalised on both sides (`matchKey`), existing
  folders keep their code. `FileNames::available` (was Trash::free). Owner's try-it **38** = apply on the real copy.
- **D10 (owner, 2026-11-24): `real load` = the whole move.** The owner puts the server's `storage/app` (as it is) in
  `local-data/server-files/` (only read); load: dump → adopt/migrate → `rm storage/app/arxeio,trash` → `attachments:import
  local-data/server-files --apply` (`App\Services\Attachments\ServerFiles`: by the rows incl. trashed,
  `Attachment::fileCandidates`, case/NFC-insensitive lookup on the server copy, never overwrites, orphans only counted)
  → fill-metadata → `folders:glossary --apply --brief`. No `server-files/` → the old behaviour (files used as they are).
  Deployment: the same `attachments:import` from the old XAMPP `storage\app`. **D11:** unmatched files that are not
  attachments (`ServerFiles::notAttachment`: outside arxeio/ + trash/, hidden segments, `dummy`, Thumbs.db/desktop.ini,
  arxeio/backups/, arxeio/protocol.sqlite*) are counted apart; files in arxeio/emails/ and directly in arxeio/ can be
  attachments (protocols without a folder). `--list` explains each other orphan (`explain`: same basename in a row,
  protocol `<num>-<YYYYMMDD>_` not in the DB = newer than the export, or the protocol has other files). Owner's first
  real load with files (2026-11-24): complete; 38 and 61 done.
- **D09 (owner, 2026-11-23): glossary `aliases`.** A glossary folder may list `aliases`: the same folder written
  with another code in the database (`Φ32.β` = `Φ.32β`, `Φ29Α` = `Φ.29`, `Φ33.β` = `Φ.33.1`). `FolderGlossary::records`
  updates that folder (it keeps its code); the list's own code wins when both exist. New owner decisions of this kind go
  in `folders.json` (`aliases` + `aliasNote`), then `--docs`.
- Cloud sandbox note (2026-11-19): a cached app image without npm/mysqldump → build the frontend on the host
  (`npm ci && npm run build`, write `public/build/.source-hash` as `docker/app/build-assets.sh` computes it) and run
  `composer install --ignore-platform-req=php` on the host; BackupTest / backup, printing-Excel and native-drag e2e then
  fail here only (also on master).
- **Batch 9 complete** (R6a #65, R6b #66, R1d #67, R6c #68, R7 #69; stacked; summary [`docs/reports/BATCH-9.md`](../reports/BATCH-9.md); 508 PHPUnit). **Next: Batch 10** (A01 → A03 → A04 → A05 → A06 → X01 → X02 → Z01 → Z02 → Z03), then the deployment guide.
- **R2e (Batch 9.5, owner's note 2026-11-19):** `x-list-pager` component (resources/views/components/list-pager.blade.php)
  above and below the protocol list (‹ ›, numbers window, `Σελίδα [select] από N` via `$wire.gotoPage`, rows shown);
  `#pager-top` / `#pager-bottom`, `data-pager=previous|next|select`. R2g: used on every paged screen (search
  results and /find, trash, users, clean-up; one paged list per page); the lower pager scrolls to `#pager-top`; trait
  `App\Livewire\Concerns\PagesWithinRange::pageOf($query, $perPage)` turns a page past the last into the last.
- **R6d (Batch 9.5):** `SettingsCatalog` entries have `help` (line) and `more` (ⓘ popover, Bootstrap Popover via Alpine
  `x-init` in `livewire/partials/setting-field.blade.php`); `box` + group `boxes` frame settings that work together
  (`#box-numbering`). Hidden (values kept): updatesAutoCheck, protocolArrowStep, safeNewProtocolNum. `ProtocolWriter`:
  the number is given on save unless an administrator with protocolValidate off chooses it; retention is the folder's
  (`Keepvalue::retentionOf`) unless allowUserChangeKeepSelect. Not in the catalog but still read: timeZone,
  rowsToSearchGiaSimplirosi, rowsToShowGiaSimplirosi, wideListProtocol (old layout).
- **R2f (Batch 9.5):** `App\Services\DateOrder`: `flags(years)` = protocols out of order (complement of the longest
  non-decreasing run of dates by number, per year) or after today; `check(date, year, number|null, exceptId)` = the
  form's warning against the nearest in-order neighbours; `message()`. List: row `.date-flagged` + `.date-flag` ⚠
  (Bootstrap tooltip, above the row link). Form: `#date-warning` (only when the user may set the date; never blocks;
  the date field is `wire:model.live.change`). Owner: a future date only warns. **A03 (Batch 10, "dates going
  backwards")** should reuse `DateOrder`.
- **R2h (Batch 9.5):** the list row's actions are a Bootstrap dropdown (`.row-actions` ⋮, `data-action=copy|receipt`,
  Popper `strategy: fixed` so the card does not clip it, `z-2` above the row link).
- **R5e (Batch 9.5):** the inbox's in_date uses `<x-date-input :help="false">` (new: `help=false` drops the hint);
  `KeywordSuggestions::for(…, $fileNames, $spellings)` (MAX 8): file names (no extension) searched like the subject,
  their words after the subject's; `Suggestions::spellings($user)` (cached 10 min) restores accents of capitals from
  the last 3000 subjects the user may see. Mapping words to categories (γνωμάτευση → Δικαιολογητικά) is not done.
- **R1j (Batch 9.5, owner's note 2026-11-20):** the protocol form has one submit, `#form-save` in `.form-save-float`
  (position fixed, bottom right; the form gets bottom padding). No Νέο / Λίστα pills in the form. Copy / receipt are
  the last pills (`.pill-far-right`, `data-action=copy|receipt`, icons). Top bar: an item with both `url` and
  `children` is split (`.nav-split`: link `#nav-N-link` + ▾ `#nav-N` «Περισσότερες: …»); Λίστα → the role's landing.
  Readers get no copy as new (`ProtocolPolicy::create`: everyone but readers).
- **R3e (Batch 9.5, owner's note 2026-11-20):** `UserPreferences` key `home` (all / d / e; `HOMES`), chosen in the user
  menu (*Αρχική σελίδα*). `Navigation::landing($user)` = the choice, else config/landing-page.php; used by the login
  (`/chkForUpdates`, RedirectAfterLogin), `redirectUsersTo`, the brand and Λίστα (`data-home-link`, updated at once by
  preferences.js). *Όλα τα πρωτόκολλα* is always /home/list. Top bar: `navbar-expand-md`; 768–1399px compact rules
  in app.scss (name/role only in the user button's tooltip, brand …, search 13rem, may wrap to 2 rows under 950px).
- **R2i (Batch 9.5, owner's note 2026-11-20):** list `.protocol-table`: `table-layout: fixed`, widths on the header
  cells (`col-*`), stripes on `.protocol-row` only (`:nth-child(even of .protocol-row)`, so ΚΕΝΟ rows don't shift
  them). Each row `x-data="{ open }"`, `.row-open` when open or *Αναλυτικά*: `.row-clamp` (2 lines) and files after
  the 2nd hidden otherwise (`+N ακόμη`); ▾ `.row-toggle` next to ⋮.
- **R3f (Batch 9.5, owner's note 2026-11-20):** a text value or free word starting with `=` is matched whole
  (`ProtocolSearch::wholeOf` / `contains`: the LIKE as before AND `REGEXP_LIKE(col, wordPattern, 'i')`; the pattern
  folds Greek accents and σ/ς itself, since ICU regexps don't; boundaries = no letter/digit before/after). The /find
  switch `whole` (URL `whole=1`) puts `=` before every value. Highlight ignores the `=`. Help: search/help.blade.php.
- **R1k (Batch 9.5, owner's note 2026-11-20):** `resources/modern/unsaved.js` (Alpine `unsavedChanges`, the form's
  x-data): snapshot of `$wire` form/handle/inform/uploads/ΑΔΑ/noStamp at load (a save reloads the page), `changes()`
  → [label, before, now] (labels `ProtocolForm::LABELS`), red dot `#form-save .unsaved-dot`. Same-site link clicks
  with changes → `#unsaved-dialog` (Ακύρωση / Χωρίς αποθήκευση / Αποθήκευση = `$wire.save(path)`; `save(?string $then)`
  redirects there if `ProtocolForm::sameSite` accepts the path). Closing / reloading the tab: the browser's question.
- **R2j (Batch 9.5, owner's choice 2026-11-20):** `<x-assignee-pills :assignments :names :inform>`: one pill per user,
  `bi-pencil-square` Διεκπεραίωση (`.assignee-handle`), `bi-eye` Ενημέρωση (`.assignee-inform`); list and search
  results. The Λίστα menu and the home-page choices use the same icons (were hourglass / bell).
- **R5f (Batch 9.5, owner's choice 2026-11-20):** `App\Services\Mail\InboxCount`: count of the inbox (uidsSince
  `since()` = daysToCheckEmailBack, every e-mail, read or not), one cache key for all users, 60 s; the inbox page puts
  its total, register / markRead forget it. `GET /inbox/count` (writer + email + throttle). Top bar: Navigation item
  `count` → `[data-inbox-count]` badge, drawn from the cache only (no IMAP on page render); `resources/modern/inbox-count.js`
  checks every minute while shown and on visibilitychange; the inbox page's Livewire event `inbox-count` sets it at once.
- **R5g (Batch 9.5, owner's note 2026-11-20):** the inbox uses `x-list-pager` (top / bottom) through a
  LengthAwarePaginator built in render(); EmailInbox has `previousPage` / `nextPage` / `gotoPage(n, name)` (renamed from
  goToPage: Livewire matches method names exactly; past the last page → the last). The sidebar has
  `.inbox-sidebar-pager` (‹ Σελίδα N από M ›).
- **R3g (Batch 9.5, owner's note 2026-11-20):** the top bar is Νέο · Λίστα · Εισερχόμενα; `Navigation::manage($user)` =
  group title => links (Εκτύπωση, Φάκελοι, Διαχείριση), drawn in the user menu (`.user-menu`, two columns from
  768px: `.user-menu-manage` | `.user-menu-own` Προτιμήσεις; Περί… / Έξοδος below).
- **R2k (Batch 9.5, owner's choice 2026-11-20):** list rows at most two lines until opened: Από / Προς one line each
  (`.from-to-line`, `.from-to-more` Bootstrap tooltip = full name · number · date; the details inline when open),
  `.attachment-list` first file + `+N` (`.more-files`), `<x-assignee-pills>` first pill + `.assignee-more`.
- **R5h (Batch 9.5, owner's report 2026-11-20):** folderPicker.scroll() scrolls only its list (scrollIntoView moved
  the page); inbox `go(uid)` focuses the article (tabindex -1), not its first field, and `hold()` keeps it aligned for
  2.5 s (ResizeObserver on body, after scrollend) unless the user scrolls / clicks / types.
- **R5i (Batch 9.5, owner's note 2026-11-20):** `livewire/partials/inbox-addresses` folds Προς / Κοιν. after 3
  (`[data-addresses]`, «και άλλοι N ▾»). Synthetic e-mail 12 has 41 recipients + Cc. **`./dev.sh e2e` loads the mock
  inbox with `--no-private`** (make-emails.php): the dev inbox otherwise holds the owner's `local-data/emails`, and an
  R5g screenshot had shown one (replaced; old image still in the branch history of #85+, owner decides).
- **R5j (Batch 9.5, owner's note 2026-11-20):** `livewire/partials/inbox-attachments` (with the winmail notice) is
  drawn in the form above the buttons while the e-mail waits, in `.inbox-mail` once done; `.inbox-mail` tinted (info
  7 % + left bar), Θέμα after Προς / Κοιν. The R5g browser test sets emailNumFetch 2 (the mock inbox is small now).
- **R5k (Batch 9.5, owner's report 2026-11-20):** per e-mail `.inbox-saving` notice with `wire:target` = its four
  register(uid, …) calls (Livewire matches the exact arguments); spinners on the pressed button; every register /
  register-anyway / markRead button `wire:loading.attr=disabled wire:target="register,markRead"`. Livewire runs one
  request at a time per page; numbers are given under the DB lock (ProtocolNumbering), also across users.
- **R5l (Batch 9.5, owner's note 2026-11-20):** every inbox article has `x-data="{ shown }"`; a done one
  (`.is-done`, server) hides `.inbox-mail` by CSS until «Εμφάνιση email» (`.mail-shown`); «Επόμενο email ↓» =
  `go(next pending uid on the page)`.
- **L03 (owner, 2026-11-23):** optional test mailbox for the real-data copy: `config/imap.php` account `test`
  exists only when `TEST_IMAP_HOST` is set (`TEST_IMAP_*`, label «Δοκιμαστικός λογαριασμός (user)»; accounts may
  carry a `label`, `SettingsCatalog::imapAccounts` shows it). The owner's login lives in git-ignored
  `local-data/test-mailbox.env` (created from `docker/dev/test-mailbox.env.example` by `./dev.sh real up`, passed to
  ep-real-app by `env_file … required: false`). Never read that file. `real load` still resets to `default`.
  Optional `TEST_SMTP_*` (owner's choice: real SMTP, redirected): `config/mail.php` `$smtp` for both mailers +
  `mail.test` (`enabled`, `only_to`); `App\Support\Mail\TestMailRedirect` (MessageSending listener in
  AppServiceProvider) keeps only `TEST_MAIL_ONLY_TO` recipients (default the test mailbox), else first allowed +
  `[ΔΟΚΙΜΗ] προς: …` subject, empty list = not sent. IMAP: the library's `tls` = TLS from the start, but production's `.env` (old ext-imap) has 143 + `tls` = STARTTLS:
  `config/imap.php` `$imapEncryption` maps `tls` on a port ≠ 993 to `starttls` (default and test accounts).
- **R5m (owner, 2026-11-23: morning timeouts; cache safe for several users):** `inbox.blade.php` renders
  `<livewire:email-inbox :defer=…>` (config `imap.deferred_inbox`, tests of the loaded page set it false);
  `EmailInbox::placeholder` = `livewire/partials/inbox-connecting` (Alpine `inboxWait`; also as wire:loading for
  refresh/pages). Owner's notes: «Παρακαλώ περιμένετε…» (no long text), «Τελευταία ανανέωση: ΩΡΑ» (`checkedAt`),
  502/503/504/no answer on the inbox page → `inbox.js` `interceptRequest` stops Livewire's popup and fires
  `inbox-unreachable` (banner / `#inbox-unreachable` say it, «Δοκιμή ξανά»); nginx `fastcgi_read_timeout 180s`.
  Review: `markRead` may still move a registered-but-not-moved e-mail (`moveOnly`). Root `data-inbox-state` connecting/ready/failed (e2e `openInbox` /
  `inboxReady` in helpers.js wait for it). `App\Support\Mail\MailboxFailure` slow/login/other (+ elapsed ≥ 0.8 ×
  timeout); `IMAP_TIMEOUT` (accounts' `timeout`). `App\Services\Mail\InboxClaims`: per-e-mail lock + 7-day done record
  (who, number); `EmailInbox::claimed()` wraps registerNow / markReadNow, `doneByOther()`. Next **R5n**: show the
  last check's e-mails from the cache while the server is slow.
- **R5n:** `Inbox::snapshot/rememberSnapshot` (shared list of UIDs + time per account/folder, 7 days) +
  `cachedPage` (cache only). `EmailInbox::mount` (deferred_inbox on) = `showSnapshot()` (`stale`, `connecting`, claims
  shown as done; never changes `page`), root `wire:init="connect"`; `load()` writes the snapshot, keeps the e-mails
  shown on failure (`stale`), `gotoPage` restores the page number on failure. R5m's Livewire placeholder removed.
- **R5o:** `inbox.js` `keepInPlace(el, top)` (one at a time, instant scroll, stops on wheel/touch/key/pointerdown) on
  every Κείμενο / HTML toggle; form sections `data-section="incoming"` (bg-success-subtle) / `"outgoing"` (bg-info-subtle,
  folded, Alpine `open`; `wire:key` carries open/folded so an error re-opens it; only if `ProtocolAccess::out`); out_*
  in `EmailRegistration::defaults` (empty) and `EmailInbox::FIELDS` / input; preview `.inbox-mail` light purple
  (owner's choice), headers `.inbox-message-head` lavender (done: green); HTML tab icon `.inbox-html-tab`; list
  `.inbox-sidebar-n` («N από M») and `.inbox-sidebar-when` (time + ✓ number / αναγνωσμένο); between e-mails
  `.inbox-sep` «↓ N από M ↓» (owner chose option B; articles no longer `mb-3`).
- **R3h (Batch 9.6):** `GET /protocol/{num}/{year?}` → `ProtocolFormController::link` (year defaults to this year;
  `\d{1,9}` / `\d{4}`) redirects to `/home/{id}`; missing, not allowed (`cannot('view')`) and malformed all render
  `protocols/missing.blade.php` (404, identical apart from the number; search link `αρ:N/YYYY` or `/find`). Form pill
  `data-action="link"` `data-copy-link` (resources/modern/copy-link.js: clipboard API, `execCommand` fallback for
  plain http, prompt last); for readers it carries `pill-far-right`.
- **R3i (Batch 9.6):** `QueryParser`: `πότε:`/`when:` = `ημ:`; `QueryParser::relative(value, today)` → [word, [from, to]]
  (σήμερα, χθες, εβδομάδα = Monday→today, `Nμέρες`/`τελευταίεςN`/`lastN` = N days incl. today, μήνας = 1st→today,
  φέτος / πέρσι = whole year) for every date operator; `SearchCriteria::$relative` (field → word) makes `format()`
  write the word back (and `SearchPage::paramsFor` puts it in `w`, not in the date fields). `χρήστης:`/`καταχώρισε:`/
  `user:` → `registrarName` (LIKE on users.name → `protocols.user_id`), menu `by` → `registrarId` (menu wins).
  `/find`: `quickDate(key)` buttons (`SearchPage::QUICK_DATES`) fill from/to and clear the year; a protocol date typed
  in «Οπουδήποτε» clears the prefilled year unless `έτος:` is typed.
- **R4d (Batch 9.6):** `ProtocolWriter::renameAttachment(user, attachment, typed)` (same rule as `removeAttachment`:
  `ProtocolAccess::canSave` + policy update; only `attachments.name`, path untouched; extension from
  `ProtocolWriter::extensionOf` = the name's or the stored file's, not doubled when typed; `FileNames::clean`;
  `InvalidArgumentException` «Γράψτε το νέο όνομα.» / ΑΔΑ-only). Form: Alpine inline editor in `.attachment-name`
  (`data-action="rename"`, `.rename-input`, fixed `.input-group-text` extension, Enter / Escape), Livewire
  `ProtocolForm::renameAttachment`.
- **R2l (owner, 2026-11-23):** the list page's «Αναζήτηση» pill removed (top-bar search box + its advanced button cover it).
- **F09 (2026-11-23):** `Dockerfile` writes `/etc/apt/apt.conf.d/80-retries` (Retries 5, http/https Timeout 30 s)
  before `apt-get`; every CI job has `timeout-minutes`. Cause: security.ubuntu.com answering only some requests made
  the image builds hang for an hour (cancel + re-run the stuck run if it happens again). A macOS leg cancelled after
  ~15 min with 0 steps is GitHub's runner-capacity limit (annotation «not acquired by Runner»), not ours: re-run it.
- **Squash merges (owner, 2026-11-20):** #85 → #91 and R5k squash-merged and their branches deleted: their history
  held an R5g screenshot with a private e-mail. Merge with `--squash` anything whose history includes those branches.
- **Inbox incoming number (owner, 2026-11-20, keep):** `EmailRegistration::defaults` fills *Αριθ. εισερχ.* with the
  e-mail's time (H:i:s) on purpose: it stands in when the attachment has no sender's protocol number; otherwise the
  user types that number over it.
- **Owner's try-it list (2026-11-19):** `OWNER-TODO.md` → *Try it, batch by batch* holds numbered checks 1–30 (Batches
  3–9). The owner reports them by number, at their own pace: tick `[x]` the ones reported fine and fix the ones reported
  wrong (with tests) before or within Batch 10. At the end of each batch, **add** a new subsection for that batch with the
  next numbers; never replace the earlier ones. Batch 10 starts only when the owner says so.
- **R6a:** `/users` = Livewire `UserAdmin`; rules `App\Services\Users\UserAccounts` (≥ 1 active admin, not own account, users with protocols/assignments deactivated not deleted, password ≥ 8, sessions ended on deactivate/password change); `App\Support\Roles` (names, descriptions, `adminId()` memoized with `once`); inactive users refused at login (`LoginController::credentials` closure) and logged out (`EnsureActiveUser`, web group); index `protocols.user_id`.
- **R6b + R1d:** `/keep` = Livewire `FolderAdmin` (everybody sees; admins change, import pasted spreadsheet rows via `FolderCatalog::records` (quote-aware, separator per row)); table `folder_assignments` (FK to keepvalues only; user rows removed by `UserAccounts::delete`); `App\Support\FolderRules` applied in `ProtocolWriter::create` (after validation, any role) and `update` (only when the folder changes); form notice `#notice-auto-assign`.
- **R6c:** `/settings` = `SettingsPage` from `App\Support\SettingsCatalog` (only its keys saved; e2e `setSetting` uses `#s-<key>`); `/backups` = `BackupPage`; `/arxeio` = `ArchivePage` + `App\Services\Attachments\Expired`; `layouts/print` (print pages); unused settings removed; `InboxAccess` whole usernames. All GET routes that changed data are gone.
- **R7:** `/print` = `PrintPage`; `PrintController` (`/printed` GET+POST, `target=xls`, `/printedAttachments`, `/receipt`); `App\Services\Printing\PrintRange`. `POST /receiptToEmail` removed. Only the auth pages still use `layouts/app` (Z01).
- **Docker Desktop on the owner's Fedora PC:** files replaced by `sed -i` stay stale inside `ep-dev-app` (and Blade's compiled views then too): use in-place writes; if needed `mv f f.m; docker exec ep-dev-app ls <dir>; mv f.m f` and `./dev.sh artisan view:clear`.

## Earlier state — 2026-11-18 18:59 (after Batch 8; times are Greek time)
- **Batch 8 complete** (R5c #62, R5d #63, R5b #64; stacked; summary [`docs/reports/BATCH-8.md`](../reports/BATCH-8.md); 459 PHPUnit, 68 browser tests). **Next: Batch 9** (R6a, R6b, R1d, R6c, R7; owner's folder list helps R6b).
- **R5c done** (PR #62): inbox `/inbox` = Livewire `EmailInbox` (`/viewEmails` redirects). `EmailRegistration` saves through `ProtocolWriter::create(…, allOrNothing: true)` (content rows `content/name/mime/stamp/dir/prefix`; `AttachmentsNotSaved`; `email_date` → e-mail receipt and no Τόπος rule). `MailContent::summary` (cached by `Inbox::summary`), `Mailbox::move`, `InboxController` (page, attachment, HTML). Old `storeFromEmail`/`setEmailRead`/`checkSameEmail`/`getEmailNum` removed; `Legacy/StoreFromEmailTest` → `EmailRegistrationTest`. Review: every e-mail numbered/dated on save (`etos`, `protocoldate` set by `EmailRegistration`); errors without a field go to `forms.<uid>.form`.
- **R5d done** (PR #63, stacked on #62): `SimilarProtocols` (shared with ProtocolForm) → notice + `register(uid, receipt, confirmed)`; `EmailRegistration::checkFolder` first; subject from the attachment (`SubjectFromFile`) only for e-mails without a subject; keywords as tags (`tagBox(field, suggestions, [uid])`) + `KeywordSuggestions`. Review: duplicate check uses the subject from the attachment (`EmailInbox::subject`).
- **R5b done** (PR #64): `App\Support\Mail\Tnef` (attributes, MAPI props, LZFu/MELA RTF → text, receipts → Greek text; code pages via iconv: mbstring has no CP1253); `MailContent::contents` shows TNEF files as keys `<part>-<n>` (Livewire-safe, no dots) instead of winmail.dat; `summary.winmail`; `Inbox::SUMMARY_VERSION` 2. `tests/Support/TnefBuilder.php` (plain PHP, also required by `docker/dev/make-emails.php`; synthetic 11-outlook-winmail-dat.eml).
- The dev mock mailbox also loads `local-data/emails` on the owner's computer: evidence screenshots must show synthetic e-mails only (check before committing).

## Earlier state — 2026-11-18 13:58 (after Batch 7.5; times are Greek time)
- **Batch 7.5 complete** (owner's notes on Batch 7: R1h #59, R3d #60, R1i #61; summary [`docs/reports/BATCH-7.5.md`](../reports/BATCH-7.5.md); 416 PHPUnit, 62 browser tests). **Next: Batch 8** (R5c → R5d → R5b, inbox/e-mail 📧: remind the owner about e-mails first). R1h: `App\Support\FolderCode` (+ tags.js twin, `tests/fixtures/folder-code-cases.json`): dots before the number optional, inside significant, a number alone = number part. R3d: top bar Νέο · Λίστα · Εισερχόμενα · Διαχείριση (+ Εκτύπωση, Διατήρηση), search box buttons, institution second line, Bootstrap Icons (`bootstrap-icons` 1.13.2, `<i class="bi bi-…">`); `users.preferences` JSON + `App\Support\UserPreferences` (`width` full/narrow via `POST /preferences` + `preferences.js`; `form` layout); `body.layout-narrow`, `main` is a CSS container that hides d-lg/d-xl columns. R1i: protocol form sections rendered from `UserPreferences::formLayout` in zones left/right/bottom (`wire:sort` → `ProtocolForm::moveSection`, `toggleSection`, `setColumns`, `resetLayout`); `<x-form.section-head>`; collapsed sections use `hidden` and open on errors. Old-layout pages keep their old menu. Owner's two e-mail notes (`??` in a new e-mail; e-mails of one sender not registered) do not reproduce on current code: questions in OWNER-TODO (likely the old inbox's "choose a Φάκελος" message). Owner's real copy: 3653 protocols, 7630 attachments, 7148 files found.
- In tests, never append methods before the file's last `}`: some test files end with a helper class (review of R3d).

## Earlier state — 2026-11-18 12:22 (after Batch 7)
- **Batch 7 complete** (R3a #55, R3b #56, R3c #57; stacked branches `task/R3a-advanced-search` → `task/R3b-query-parser` → `task/R3c-global-search`; summary [`docs/reports/BATCH-7.md`](../reports/BATCH-7.md); 386 PHPUnit, 57 browser tests; all merged 2026-11-18 13:10 Greek time). **Next: Batch 8** (R5c → R5d → R5b, inbox/e-mail 📧: remind the owner about e-mails first). Search: `App\Services\Search\SearchCriteria` (what) + `ProtocolSearch` (access-limited query; LIKE escaped; general_ci = no accents/case; folder exact by code as written, without dots every matching code; `PENDING_WORD`) + `QueryParser` (`parse`/`format`, `ignored`, `yearGiven`, `tokens`). `/find` = Livewire `SearchPage` (fields in the URL: `s`, `year`, `num`, `from`/`to`, `in_from`…, `folder`, `t[field]`, `empty[]`, `assignee`, `pending`, `w`; `paramsFor(criteria)`); `/search` = `SearchResults`; `/search/suggest` JSON (throttle 120/min). Shared results table `search/results.blade.php`; top-bar box `search/box.blade.php` + `resources/modern/search.js` (Alpine `searchBox`, `/` shortcut); `folderPicker({model, live, choices, submit})`. `Highlight::html` folds accents and takes several terms. Old `find`/`getFindData` removed. Settings `searchField1–3` unused (remove in R6c). Institution name in the top bar only ≥ 1600 px.
- Auto mode refused `git push` once (2026-11-18); the owner made the `autoMode.allow` rule in `~/.claude/settings.json` explicit (`git push -u` of task/* branches, stacked too). Push without pipes, one branch per command.

## Earlier state — 2026-11-18 11:10 (after Batch 6.6)
- **Batch 6.6 complete** (D06 #51, R2d #52, R1f #53, R1g #54; summary [`docs/reports/BATCH-6.6.md`](../reports/BATCH-6.6.md); 356 PHPUnit, 52 browser tests). **Next: Batch 7** (R3a → R3b → R3c, search). D06: `keepvalues.describe`/`remarks` TEXT (`Keepvalue::TEXT_MAX` 16000), folders page escapes and no `<br />` in the edit box; owner will send the full folder list (import to write; ask about a separate title). R2d: green `.gap-row`, "N/YYYY ΚΕΝΟ", centred button; `<x-icon name="plus|check|warning">` (inline SVG). R1f: `App\Support\TagList` (related/keywords: add, replace in place, remove, `fold` = no case/accents, shared cases `tests/fixtures/fold-cases.json`), `ProtocolForm::addTag/editTag/removeTag`, `RelatedNumbers::resolve` → `exists` + viewable `protocol`; `Suggestions::keywords()` / `folders()` as JSON in the page (`#suggest-keywords`, `#suggest-folders`), searched in the browser (`resources/modern/tags.js`: Alpine `tagBox`, `folderPicker`); `#f-fakelos` is now a combobox (`data-combobox`; e2e `fillProtocol` handles it). R1g: number grid `.number-head`, `$numberTouched` (status `quiet` until changed), `#done-card`, `#less-used` (Ανάθεση + receipts). Firefox on Fedora/GNOME Wayland **crashes** when a file from Nautilus is dropped (Wayland protocol error in Firefox's main process: known Firefox bug); works on Windows; no page change.

## Earlier state — 2026-11-17 12:10 (after Batch 6.5)
- **Batch 6.5 complete** (R1e #48, R2b #49, R2c #50; summary [`docs/reports/BATCH-6.5.md`](../reports/BATCH-6.5.md); 346 PHPUnit, 48 browser tests). **Next: Batch 7** (R3a → R3b → R3c, search), unless the owner first sends UI-layout notes (rarely used fields/controls to move). Owner's feedback on Batch 6 (2026-11-17): R1e (form: drop on Firefox/Wayland, related numbers as tags (`addRelated`/`removeRelated`, stored text unchanged), toasts top-centre + click-to-close + optional link, restore notice links to the protocol), R2b (list: user filter also under *Όλα*, user menu grouped by open assignments, richer *Αναλυτικά*, attachment names), R2c (missing numbers as dashed rows from *Αρ.Πρωτοκόλλου για ξεκίνημα*, one setting instead of two: `gapCheckFromNumber` migrated into `firstProtocolNum` and removed; `firstProtocolNum` 0 = 1, the installer's value). Batch numbers may be decimals in tasks.toml (6.5). The owner's real copy (8081) showed attachments as missing: owner to rerun *Real data: load newest backup from local-data/db* and send the counts. Toasts: `appToast(message, type, {url,label})` / `dispatch('toast', message:, type:, url:, label:)`. Native file drag in e2e: CDP `Input.dispatchDragEvent` (Chromium only).

## Earlier state — 2026-11-16 23:54 (after Batch 6)
- **Batch 6 complete** (R1a #42, R1b #43, R1c #44, R2 #45, R4a #46, R4b #47; merged in that order, stacked PRs: each PR's base was the previous task's branch, retargeted to `master` after the one below merged; after rebasing, rebuild the frontend (`./dev.sh assets`, public/build is not in git) before browser tests). Summary: [`docs/reports/BATCH-6.md`](../reports/BATCH-6.md). 339 PHPUnit tests, 42 browser tests. **Next: Batch 7** (R3a → R3b → R3c, search). R1a (#42): `App\Services\Protocols\ProtocolWriter` saves every protocol (legacy form included): `TextInput` cleaning, `ProtocolAccess` (form's field rules, now enforced by the server), validation, numbering + ΕΛΕΥΘΕΡΟ replacement, attachments, e-mails. Text column `diekperaiosi` dropped; `Protocol::$diekperaiosi` is a virtual "d14,e10" over `protocol_assignments` for the legacy prints/receipts/inbox. New setting `allowWriterChangeDate`. R1b (#43): the protocol form is the Livewire component `App\Livewire\ProtocolForm` (page `protocols/form`, controller `ProtocolFormController`) at `/home`, `/home/{id}`, `/home/{id}/1`; old form view and its GET/POST helper routes removed (`POST /home[/{id}]` stay as adapters until Z01). Livewire 4: `wire:model.blur` only syncs in the browser; use `.live.blur` / `.change` when the server must react. nginx `client_max_body_size 64m` (was 1 MB on the Docker stack). R1c (#44): attachments panel (drop anywhere on the window via Alpine + `$wire.uploadMultiple('picked', …)`, ΑΔΑ per file, subject from file name `App\Support\SubjectFromFile`). Bootstrap `d-*` utilities are `!important`: don't put them on elements toggled by `x-show`. R2 (#45): list = `App\Livewire\ProtocolList` (page `protocols/list`) at the old addresses; filters/sort in the query string (`filter`, `user`, `field`, `sort`); route params passed as `route-filter`/`route-user` (Livewire assigns same-named params to public properties); `resources/lang/el.json` for Livewire's pagination text. R4a (#46): `App\Support\SafeFile` (download/content responses: inline only pdf/images/text with the extension's type, else attachment; html/svg/xml → octet-stream; nosniff; sandbox except pdf/images; streamed) + `SafeFile::refuse()` for uploads (`config/attachments.php`: max_kb, blocked extensions, content sniffed with finfo — Laravel's fake files take their type from the name). E-mail attachments are not refused at registration (R5d). R4b (#47): `App\Services\Attachments\Trash` (trash/restore/purge, `trashed_from`, `protocol_label`), page `/trash` (Livewire `TrashPage`, admins), retention setting `trashRetentionDays` (empty 30, 0 manual) counted from `trashRetentionStart` for files trashed before the upgrade; no scheduler in the stack → `PurgeTrashDaily` middleware runs it once a day after the response (+ `attachments:purge-trash`, schedule entry). Owner's Batch 5 real-load numbers received (D03 7183 measured / 646 not found = the L01 backup/file-copy gap; D04 0 invalid dates). Old-form-only tricks for e2e: wait for `networkidle` before submitting (its inbox poll could eat the flash data); in the new form use `fillProtocol`/`saveProtocol` (e2e/tests/helpers.js) and pick numbers above the proposed next one (earlier runs leave numbers behind).
- **Batch 5 complete:** D02 (#38), D03 (#39), D04 (#40). Summary: [`docs/reports/BATCH-5.md`](../reports/BATCH-5.md). 263 PHPUnit tests, 27 browser tests. Dates are DATE columns, read through the transitional `LegacyYmdDate` cast (still Ymd ints to legacy code; replace in Z01); assignments live in `protocol_assignments` (text column `diekperaiosi` kept in sync, drop in R1a). Owner asked to run *Real data: load* and send only the D03/D04 counts. F08 check on the Fedora copy: 7162 files, 0 over 255 bytes (definitive check at migration). **Next: Batch 6** (see BACKLOG).
- **Batch 4 complete:** D05 (#34), D01 (#35), R5a (#36), F08 (#37). Summary: [`docs/reports/BATCH-4.md`](../reports/BATCH-4.md). 232 PHPUnit tests, 25 browser tests. R5a found that inbox e-mails in `public/tmp` are downloadable without login on the current server; the owner does not touch production before the migration (2026-11-16), so the Apache rules (`docs/BLOCK-PUBLIC-EMAIL-COPIES.md`, `docs/BLOCK-REGISTRATION.md`) stay unapplied and the new version closes both gaps. Flaky CI upload in `attachment-names.spec.js`: found and fixed in D03 (seeder-created folders not writable, see D03.md); `storeAs()` failures are not checked in the form upload path (row saved with empty path): make it explicit in R1a. Batch 5 followed.
- **Batch 3 complete** (N01 #27, N02 #28, N03 #31, N04 #33; plus #26, #29, #30, #32 plan/known-bug PRs). Summary: [`docs/reports/BATCH-3.md`](../reports/BATCH-3.md). 186 PHPUnit tests, 18 browser tests.
- **Batch 2 complete** (PRs #14–#21), plus F06 (#23), F07 (#24), B10 (#25). `master` CI green, branch-protected
  (all 9 CI jobs required, admins included, no force push). 127 PHPUnit tests, 9 browser tests.
  Summary: [`docs/reports/BATCH-2.md`](../reports/BATCH-2.md).
- **Owner decision (2026-11-16): no deployment to production until all tasks are completed and verified.**
  So the production server keeps the old code; owner actions "after deploying" are collected for one final
  deployment (see `OWNER-TODO.md`). Consequence: S00 (open admin registration) stays exploitable in production;
  the old code ignores `storage/conf/.denyregister`. Mitigation without deploying code: a web-server rule that refuses
  `POST /register`: `docs/BLOCK-REGISTRATION.md` (owner has it, not applied yet).
- **Production target (owner, 2026-11-16): the Docker stack** after the rebuild (today: Windows 11 + XAMPP, Apache,
  PHP 8.1.25, MariaDB 10.4.32, php-imap). No XAMPP/MariaDB compatibility work. The owner migrates the old DB + files;
  path = `./dev.sh real load` (MariaDB dump → MySQL 8.4 + `migrate:adopt-legacy`). Still to ask before the deployment
  guide: ~~which machine~~ **same Windows 11 PC, Docker Desktop + WSL 2** (owner, 2026-11-16).
- **Batch 4 = D05 → R5a**, moved earlier by the owner on 2026-11-15. Needs: L01 done ✅ (D05 is verified on real
  data locally, Fedora) and e-mails from the owner for R5a. R5a plugs its cache into the current inbox page.
- **L02 done (2026-11-16 19:47):** the Mac loads the real data (owner confirmed). Batch 2 is complete.
- Owner's own list: [`OWNER-TODO.md`](../../OWNER-TODO.md). Update it at the end of every batch.
- Owner says "run batch N" to start a batch. **Claude completes the whole PR procedure on its own** (commit, push,
  answer reviews, merge on green CI).

## Open items / waiting on the owner
| Item | Status |
|---|---|
| `B03d` date reset on save | **Pinned (on hold).** Not reproducible by Claude or the owner. Production has *Έλεγχοι καταχώρισης* (`protocolValidate`) = **NO**. Owner will report steps when it happens again. |
| Real emails for tests | **Received 2026-11-16: 6 private e-mails in `local-data/emails/`** (owner: Claude may read them locally, never publish; see CLAUDE.md). Structural notes in `local-data/emails/NOTES-claude.md` (git-ignored). Covers: ISO-8859-7 Outlook mail with a 255-char subject, emoji (fails today, D05), winmail.dat-only read receipt (R5b), 21 MB mail with 4 attachments, long RFC 2231 file names. Long subject answered (2026-11-16): subject > 255 chars (posted by the inbox form) → MySQL 1406 on `thema`, inbox just reloads; reproduced by `test_known_bug_*subject_over_255*`; **D01 moved into Batch 4**. Owner's .eml #1 is cut at 255 by Outlook, so it doesn't reproduce it. **Also 2026-11-16:** new private e-mail shows `??` in the sender: name split inside a Greek letter across encoded words (R5a); attachment names probed: long/illegal/`../` handled, but emoji → protocol without attachment + e-mail left in inbox (D05/R5a), long Greek name without spaces → recorded but not written on Linux (F08), empty/extension-less names (F08). All pinned as `known_bug_` tests in `StoreFromEmailTest`. |
| `L01` + `L02` owner's computers | **L01 done (2026-11-16):** Fedora real copy loads after F07 (owner confirmed). Some attachment rows have no file locally: the owner's DB backup and file copy were taken on different days (attachments open fine). Expected; the next real-data set will be in sync, so a gap then is a real problem. **L02 done (2026-11-16 19:47):** Mac real copy loads (owner confirmed); its file copy is only a small part, so most attachment rows have no file there (expected). The first try failed only because the Mac checkout was 16 PRs behind (before F07). |
| `S04` production `.env` | Owner (2026-11-14): the production key is randomly generated and can be recreated; Claude may handle `.env` as needed. Back up `.env` before the next `git pull` on the server. |
| Production server facts | **Answered 2026-11-16:** Windows 11, XAMPP 3.3.0 (Apache), PHP 8.1.25 ZTS x64, php-imap on, MariaDB 10.4.32. Will be replaced by the Docker stack on the same Windows 11 PC (Docker Desktop + WSL 2). |
| Knowledge base | Owner declined (2026-11-13): extra complexity for small gain. Claude may re-suggest it later if it becomes clearly useful. |
| Final deployment (all tasks done) | Owner: back up DB + `.env`; `php artisan migrate:adopt-legacy` once (no `migrations` table, F07), then `php artisan migrate`; check `users` for unknown admin accounts; `which mysqldump`, press Backup once (B10). Claude writes a deployment guide before then. |
| Open registration in production (S00) | Not deployed by owner decision. Web-server rules (Apache/nginx) given in `docs/BLOCK-REGISTRATION.md`; owner chose **not to apply them yet** (2026-11-16). Remind at the final deployment (remove the rule before `git pull`). |

## Decisions log (short)
- **The Mac is secondary** (owner, 2026-11-16): its checkout is often stale. On the Mac, `git fetch` and pull `master`, then re-read this file, `PROGRESS.md` and `OWNER-TODO.md` before advising or starting work.
- No changes on the production server until the migration, also not the Apache security rules; date stamps in our md files include the time, Greek time (owner, 2026-11-16).
- Private e-mails: Claude may read `local-data/emails/` locally, never publish anything from them (2026-11-16).
- Module-by-module rebuild, Livewire + Bootstrap 5, MySQL only (BACKLOG "Decisions").
- Dev image: Ubuntu 26.04 + distro PHP 8.5 (sandbox can't reach Debian/Alpine mirrors; CI builds it too).
- Dev stack isolated: Compose project `electronic-protocol-dev`, containers `ep-dev-*`, volume `devdbdata`.
- Number may be chosen only by admin with `protocolValidate` OFF (same rule as the form).
- Deleting protocols: admins only. Assign: admin + Αναθέτων. Mark done: admin, Αναθέτων, d-assignee, creator.

## Resuming in a fresh Claude Code cloud session
The cloud sandbox can't use Docker Hub, Debian mirrors or GitHub downloads inside containers, so it needs
a one-time preparation (`docker/dev/cloud-setup.sh`: images from the Google mirror, app image built with host
networking, Composer on the host). If the session runs in the **"electronic_protocol"** cloud environment, its
setup script already did this before Claude started. Then:
```bash
./dev.sh cloud-up     # starts Docker + the stack, migrates/seeds on first run (~2 min)
./dev.sh test
```
Without that environment: `bash docker/dev/cloud-setup.sh` first (~7 min on a cold container).
- Playwright: `NODE_PATH=$(npm root -g) node script.js` (Chromium preinstalled).
- Git tags can't be pushed through the sandbox proxy; milestones are recorded in `MILESTONES.md`.
- Never run `artisan db:wipe` without `--env=testing` (it would wipe the dev demo DB).
- **Owner's Fedora runs Docker Desktop** (VM file sharing): a file replaced by an atomic rename (Claude's Write/Edit tools) can stay stale *inside the containers* for minutes. Before running tests in the containers, rewrite changed files in place (`cat f > tmp && cat tmp > f`).
- Frontend (N03): `docker/dev/cloud-setup.sh` builds the app image, which now runs `npm ci`; the cloud sandbox must reach the npm registry (not yet tried there).

## Task log
| Date | Task | PR | Notes |
|---|---|---|---|
| 2026-11-24 18:40 | docs | — | Batch 9.6 docs PR: [BATCH-9.5](../reports/BATCH-9.5.md) summary written (it was missing); BACKLOG's Batch 9.5 line lists the tasks added during it; OWNER-TODO links both summaries |
| 2026-11-24 16:00 | R4d | #107 | rename an attachment (✎; shown name only, extension fixed, characters cleaned; same rule as ✕) |
| 2026-11-24 16:00 | R4c–R6e | #108–#115 | Batch 9.6 (stacked): preview, stamp designer + PDF stamper, stamp on view, old stamps, protocol trash, envelopes, notepad, log viewer; batch summary |
| 2026-11-24 15:00 | R3i | #106 | search: `πότε:` σήμερα / χθες / εβδομάδα / Nμέρες / μήνας / φέτος / πέρσι (every date word, written back as typed); `χρήστης:` + «Καταχωρίστηκε από»; quick date buttons |
| 2026-11-24 14:30 | R3h | #105 | direct links `/protocol/N[/YYYY]` → form; one «Δεν υπάρχει» page (missing / forbidden / malformed); «Σύνδεσμος» pill copies the link |
| 2026-11-24 09:30 | D10 | #102 | `real load` copies the server's files by the database from `local-data/server-files` (`attachments:import`) and applies the folder glossary; VS Code task renamed |
| 2026-11-24 13:30 | D11 | #103 | `attachments:import`: server files that are not attachments counted apart; `--list` says why each other file has no row; OWNER-TODO: next is Batch 9.6, deployment steps completed, 38/61 done |
| 2026-11-23 14:28 | R2l | #100 | list: «Αναζήτηση» pill removed; F09 report: the macOS 15-min cancellations are GitHub's runner queue limit |
| 2026-11-23 14:15 | D09 | #98 | glossary `aliases` (Φ32.β = Φ.32β, Φ29Α = Φ.29, Φ33.β = Φ.33.1): the existing folder is updated and keeps its code; clearer preview line |
| 2026-11-23 13:55 | F09 | #99 | Docker build: apt retries / 30 s time-outs; CI job time limits (Ubuntu security server outage hung the image builds) |
| 2026-11-23 12:13 | R5o | #97 | inbox: no jump on Κείμενο / HTML; Εισερχόμενο / Εξερχόμενο (folded) sections; purple preview, lavender headers; HTML tab icon; list «N από M» and ✓ number |
| 2026-11-23 11:01 | R5n | #96 | inbox first view from the shared snapshot (cache only) with «τελευταίας ανανέωσης (ΩΡΑ)»; `connect()` after the page is shown; e-mails stay on failure |
| 2026-11-23 10:15 | R5m | #95 | inbox page deferred with «Σύνδεση με τον διακομιστή…» + seconds; failures slow/login/other + «Δοκιμή ξανά»; `IMAP_TIMEOUT`; one registration per e-mail across users (`InboxClaims`) |
| 2026-11-23 09:10 | L03 | #94 | real copy: optional test IMAP account `test` from `local-data/test-mailbox.env` (compose `env_file`, optional), chosen in Ρυθμίσεις; account labels in the select; optional test SMTP whose mail reaches only `TEST_MAIL_ONLY_TO` |
| 2026-11-19 14:10 | D07 | #77 | folder glossary (owner's list) + `folders:glossary`, merge Φ.43 → Φ.46; 2 import bugs fixed |
| 2026-11-18 23:26 | R7 | #69 | `/print` = Livewire `PrintPage` (book / attachments tabs, live count, limit warning); `PrintController` (`/printed` GET+POST, xlsx via `target=xls`, `/printedAttachments`, `/receipt`) on `layouts/print`; `App\\Services\\Printing\\PrintRange` (old param names, ignored list, `limit()`, `describe()`, `params()`); handlers by name incl. inactive; each attachment its own keep/expiry; POST /receiptToEmail removed (form's sendReceipt); only auth pages left on the old layout |
| 2026-11-18 23:17 | R6c | #68 | `/settings` = Livewire `SettingsPage` from `App\\Support\\SettingsCatalog` (groups, label, help, kind, rules; only known keys saved; errors at the field); `/backups` = `BackupPage` (create/delete actions); `/arxeio` = `ArchivePage` + `App\\Services\\Attachments\\Expired`; `/expired` print on new `layouts/print` (R7 reuses); removed POST /settings, GET /backup, /deleteBackup, /delExpired, /delDeleted; unused settings removed (migration + seeder); `InboxAccess` whole usernames; e2e `setSetting` uses `#s-<key>` |
| 2026-11-18 23:10 | R1d | #67 | `App\\Support\\FolderRules` (`for`, `merge`, `describe`) applied by `ProtocolWriter::create` (any role, after validation) and `update` (only when the folder changes); d replaces e for the same user, hand-made assignments kept, inactive skipped; message in SaveResult; form notice `#notice-auto-assign` |
| 2026-11-18 23:03 | R6b | #66 | `/keep` = Livewire `FolderAdmin` (all see, admins change): table with automatic-assignment badges and protocol counts, search (FolderCode + words), form (Διατήρηση one field → keep/keep_alt, users d/e), import of pasted spreadsheet rows (preview, empty cell keeps); `App\\Services\\Folders\\FolderCatalog` (codes in use not renamed/deleted, `normaliseCode`); table `folder_assignments` + `App\\Models\\FolderAssignment`; menu item «Φάκελοι και διατήρηση»; old POST/GET-delete routes removed |
| 2026-11-18 22:58 | R6a | #65 | `/users` = Livewire `UserAdmin` (table, search, role/state filters, form card, role cards); rules `App\\Services\\Users\\UserAccounts` (≥1 active admin, not own account, users with protocols/assignments deactivated not deleted, password ≥ 8); `App\\Support\\Roles` (names + descriptions); inactive users can't log in (`LoginController::credentials`) and are logged out (`EnsureActiveUser`, web group); old POST/GET-delete routes removed |
| 2026-11-18 13:58 | R1i | #61 | protocol page sections: drag by ⠿ between left/right/bottom (`wire:sort`), collapse (opens on errors), one/two columns, reset; saved per user (`users.preferences.form`); drop overlay moved out of the attachments body |
| 2026-11-18 13:47 | R3d | #60 | top bar: Νέο · Λίστα · Εισερχόμενα · Διαχείριση (Εκτύπωση, Διατήρηση for all + admin items), search box with search + advanced buttons, institution on a second line, Bootstrap Icons (`bootstrap-icons` 1.13.2, `<i class="bi bi-…">`); `users.preferences` JSON + `App\\Support\\UserPreferences` + `POST /preferences` + `resources/modern/preferences.js` (`data-preference`); width full/narrow (`body.layout-narrow`, main as CSS container hiding d-lg/d-xl columns); old layout menu unchanged |
| 2026-11-18 13:36 | R1h | #59 | `App\\Support\\FolderCode` (+ `folderKey/folderSame/folderStartsWith` in tags.js, cases `tests/fixtures/folder-code-cases.json`): dots before the number optional, inside it significant (`φ11` = Φ.11 only), number alone = number part (`12` = Φ12); picker ranks code, then starts-with, then words; list scrolls to top while typing; search uses the same rule |
| 2026-11-18 12:22 | R3c | #57 | top-bar box on every page (old layout: plain form), `/` focuses it; suggestions (6, marked, escaped, access-limited, `/search/suggest` throttled), search words offered while typed; `/search` results page (all years, ignored words listed, help list), *Σύνθετη αναζήτηση* → `/find` filled in; folder code with dots = that folder only; institution name ≥ 1600 px |
| 2026-11-18 12:12 | R3b | #56 | `QueryParser::parse` (words, "phrases", αρ: έτος: ημ: ημεισ: ημεξ: φάκελος: θέμα: από: προς: … ανάθεση: κενό: είναι:εκκρεμές; names without accents, short/English forms; unknown op = word; wrong values in `ignored`) and `format` (round trip); free words anywhere (fields, folder, attachments, number); advanced screen: «Οπουδήποτε» + «Ως κείμενο» |
| 2026-11-18 12:05 | R3a | #55 | `/find` = Livewire `SearchPage` (old find/getFindData removed); `SearchCriteria` + `ProtocolSearch` (access-limited, LIKE escaped, general_ci = no accents/case); year prefilled, Enter searches, number ranges, folder picker with «Όλοι»/«Χωρίς φάκελο» (code without dots), pending check (no folder or ΕΚΚΡΕΜΟΤΗΤΑ, clear "none" message), related numbers whole; `Highlight` accent-insensitive; state in the URL |
| 2026-11-18 11:10 | R1g | #54 | number large in the middle (year small, date right), "Ελεύθερος" only after a change by hand or from a gap row; done-date card high; Ανάθεση + receipt cards below |
| 2026-11-18 11:00 | R1f | #53 | related numbers green ✔ / orange ⚠, editable tags (both fields), keyword suggestions (all keywords, most used first, accents ignored, known spelling kept), folder search box; review fix: Enter after Escape |
| 2026-11-18 10:55 | R2d | #52 | gap row green, grey text, "N/YYYY ΚΕΝΟ", centred button with +; `<x-icon>` |
| 2026-11-18 10:45 | D06 | #51 | folder description/remarks → TEXT, folders page escaping and `<br />` fix |
| 2026-11-16 23:53 | R4b | #47 | trash keeps path for restore, never overwrites; deleted protocol's files to the trash with its label; Κάδος page (restore/delete now/empty, purge dates); daily clean-up without scheduler; legacy Εκκαθάριση uses it and no longer wipes trash/ |
| 2026-11-16 23:45 | R4a | #46 | downloads: safe Content-Type/Disposition (Greek names encoded), nosniff/sandbox, streamed; uploads: size + code-running types refused (also double extension and renamed content), at once in the form and in the service |
| 2026-11-16 23:39 | R2 | #45 | Livewire list: filter pills (all/mine/d/e/f) + whose list (admin/assigner), sortable columns, sticky header, deadline colours, assignees, attachments, Αναλυτικά remembered (localStorage), A02 banner, poll refresh; old list removed |
| 2026-11-16 23:18 | R1c | #44 | panel always open; whole-window drop overlay; progress; files added, not replaced; ΑΔΑ per new file; empty subject ← first file's name (placeholder, click to edit, used on save) |
| 2026-11-16 23:11 | R1b | #43 | Livewire form: number free/Δεσμευμένος/ΕΛΕΥΘΕΡΟ live, TAB number → date (date buttons out of tab order), notices for same subject / incoming number, pills, suggestions (datalist), attachments list/upload/trash, Ανάθεση / Διεκπεραιώθηκε / Διαγραφή actions; nginx + Livewire upload limit 64 MB; removed old form + `delprotocol`, `attach/del`, `anathesiSe`, `setDiekpDate`, `checkInNum` |
| 2026-11-16 22:49 | R1a | #42 | `ProtocolWriter` + `ProtocolAccess` + `TextInput`; legacy `store`/`update` delegate to it; fixed: writer's save emptied assignments, read-only fields only read-only in the browser, search results/autocomplete unescaped, 31/02 accepted, failed upload saved as empty row, stamp temp files in web root; `diekperaiosi` column dropped (virtual attribute); `allowWriterChangeDate`; copy keeps the date; ΕΛΕΥΘΕΡΟ replaced after confirmation |
| 2026-11-16 19:47 | L02 done | #41 | owner loaded the real data on the Mac (after updating it past F07); tracker + owner list |
| 2026-11-16 17:00 | D04 | #40 | protocols `protocoldate`, `in_date`, `out_date`, `diekp_eos`, `diekp_date` + attachments `expires` → DATE; invalid old values → `legacy_invalid_dates` + NULL (invalid `protocoldate` stops the migration); `App\Casts\LegacyYmdDate` keeps showing Ymd integers to the legacy code (Z01: replace with `date`), `Protocol::dateOf()` for new code; date comparisons in queries use `Y-m-d` |
| 2026-11-16 15:55 | D03 | #39 | `attachments.size`, `sha256` (indexed), `deleted_by`; measured on save (`Attachment` model), `deleted_by` on soft delete + in *Διαγραφή ληγμένων*; `attachments:fill-metadata` (existing files, counts only; also in `real load`); migrations on legacy tables must be idempotent (`AdoptLegacySchemaTest` re-runs them) |
| 2026-11-16 15:35 | D02 | #38 | `protocol_assignments` (protocol, user, type d/e) + backfill; `App\Support\Assignments` (parse/sync/backfill), Protocol scopes `assignedTo`/`assignedFor`/`ownOrAssigned`; text column still written by the old form, synced on save (model event + after the edit query); fixed: search by assignee missed `d14,e10`, no name in results, *Κενό* missed `''` |
| 2026-11-16 14:26 | F08 | #37 | `App\Support\FileNames` (`fit`: ≤255 bytes/≤200 chars, char boundary, keeps extension; `clean`: `συνημμένο.<ext>`); `tools/check-attachment-names.php` (PHP 8.1, counts only) + `./dev.sh real check-names` |
| 2026-11-16 14:04 | R5a | #36 | `App\Services\Mail\{Inbox,MailCache,Mailbox,ImapMailbox,MailboxConnector}`: UID list + one fetch for missing, cache `storage/app/mail-cache/<mailbox>/<UIDVALIDITY>/<UID>.eml` (lock, expiry = still in inbox); `EncodedWords` joins split encoded words, `MailParser` also joins RFC 2231 continuations in every part header (owner's sick-leave e-mail, 2026-11-16); `MailParser::parse` everywhere; registration all-or-nothing; no `public/tmp` copies (were public!), `/viewEmailHtml` sandboxed; tests: `FakeMailbox` (`tests/Support`) |
| 2026-11-16 13:44 | D01 | #35 | `thema`, `in_arxi_ekdosis`, `in_perilipsi`, `out_perilipsi` → TEXT; `Protocol::LONG_TEXT_MAX` (16000) in form checks; e-mail registration error shows `E-XXXXXX`, never the SQL |
| 2026-11-16 13:30 | D05 | #34 | connection + all tables → utf8mb4 (`utf8mb4_general_ci`: searches/unique keys as before); legacy COMPACT tables → DYNAMIC; migration irreversible (backup); N04 review fix (confirm dialog in capture phase) merged with #33 |
| 2026-11-16 13:15 | N04 | #33 | `<x-date-input>`, `<x-pill-actions>`, `<x-file-badge>`, `<x-notice>`, toasts, `data-confirm` dialog; date rules in PHP + JS checked against one shared case list; `/dev/ui` (dev/test only) |
| 2026-11-16 12:54 | N03 | #31 | Vite 7 + Bootstrap 5.3 + Livewire 4.4; Node 22 inside the app image; `layouts/modern` + `App\Support\Navigation` (menu per role, tested); `/about` first page; `./dev.sh assets` |
| 2026-11-16 11:55 | N02 | #28 | `App\Models\*`, Laravel 11+ `bootstrap/app.php`, class-based routes in an `auth` group, `HasMiddleware`; route-middleware snapshot test (unchanged); fix: error handler crashed outside web requests |
| 2026-11-16 11:43 | N01 | #27 | mail-mime-parser 4, PHPUnit 13, `php ^8.4.1` + platform pin, watermark pinned (next commit swaps `markpdf` binaries); `StoreFromEmailTest` characterizes e-mail registration. Production → Docker (owner) |
| 2026-11-16 10:58 | B10 | #25 | Backup: mysqldump without shell, errors shown, `--no-tablespaces`, safe file names; stray bytes in `config/imapFolders.php` broke every download; `real load` reads `.zip`. Also L01 done, `master` branch protection (all CI jobs required, admins included) |
| 2026-11-16 10:20 | F07 | #24 | production backup has no `migrations` table → `migrate:adopt-legacy` records existing tables; `real load` runs it before `migrate` |
| 2026-11-16 00:37 | F06 | #23 | nginx re-resolves `app` (Docker DNS, 5 s): 502 after Docker Desktop restart on the Mac; `tests/shell/nginx-reconnect.test.sh` in CI e2e job |
| 2026-11-15 12:28 | Plan change + owner list | #22 | D05, R5a → new Batch 4 (later batches +1); `OWNER-TODO.md` |
| 2026-11-12 19:48 | F03, F01, F02, S00, B03, B08, S02, A02 | #2–#9 | see `docs/reports/BATCH-1.md` |
| 2026-11-12 19:45 | Review fixes | #10 | 9 Codex findings verified + fixed |
| 2026-11-13 09:33 | Tracker | #11 | `tasks.toml` + PERT/CPM `PROGRESS.md`, `CLAUDE.md`, this file |
| 2026-11-14 15:15 | L02 + Batch 2 report | #21 | Mac guide, RPM VS Code, switching guide, `./dev.sh doctor`, e2e in container (Mac: host.docker.internal), CI: dev.sh on macOS + arm64 image |
| 2026-11-14 15:08 | B07 | #20 | `MessageMover` (IMAP move/EXPUNGE bugs), folder lookup by path, invalid search/print filters ignored + reported, update check removed |
| 2026-11-13 15:19 | B04 | #19 | error reference codes `E-XXXXXX`, Greek error pages, details for admins only |
| 2026-11-13 15:15 | S04 | #18 | `.env` untracked, published `APP_KEY` removed |
| 2026-11-13 15:23 | F05 | #17 | `e2e/` Playwright suite, `./dev.sh e2e`, `EVIDENCE=<ID>` screenshots, CI job builds full stack |
| 2026-11-13 14:57 | L01 | #16 | `.vscode/` tasks via `flatpak-spawn --host`, `:z` mounts, shared perms, `docs/VSCODE-FEDORA.md`; waiting owner |
| 2026-11-13 14:53 | F04 | #15 | `./dev.sh real load` (port 8081, mail/IMAP sandboxed); storage/app now git-ignored + CI hygiene check (464 demo PDFs untracked) |
| 2026-11-13 14:43 | B09 | #14 | mail via `OutgoingMail`; found: emoji breaks e-mail registration (D05), IMAP move EXPUNGE error (→B07) |
| 2026-11-13 13:04 | Cloud setup | #12 | `docker/dev/cloud-setup.sh` + `./dev.sh cloud-up`; dedicated cloud environment proposed |
