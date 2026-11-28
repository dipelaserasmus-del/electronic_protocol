# V01 — Versions of the platform, frameworks and libraries

Last update: 2026-11-27 13:56 (Greek time). **To be updated again at the end of the development (task V02, after Z03).**

What the app ran on before the rebuild, what it runs on now, and what is the newest version available. Checked on
2026-11-26 with `composer audit`, `npm audit`, Packagist, npm and the projects' release / support pages.

## Legend

| Flag | Meaning |
|---|---|
| 🔴 | **End of life, or known serious (critical / high) vulnerabilities not fixed** in this version |
| 🟠 | **Outdated**: a newer major version exists, or the project is unmaintained |
| 🟡 | **Behind on patches / to check**: a newer patch or minor release exists, or the version depends on an image rebuild |
| 🟢 | Current and supported |

**Original** = the upstream repository `g-theodoroy/electronic_protocol`, branch `master` (same `composer.json` /
`composer.lock` as commit `2f06ea6`, 2025-01-09), and the production server as the owner described it on 2026-11-16
(Windows 11, XAMPP 3.3.0). The rebuild itself started on 2026-11-12 from `92eb910`, after the upstream author's
Laravel 13 upgrade of 2026-11-10; that upgrade never reached the production server.
**Current** = `master` on 2026-11-26 (`composer.lock`, `package-lock.json`, `Dockerfile`,
`docker-compose*.yml`, `.github/workflows/ci.yml`).
**Newest supported** = the newest version this app's stack accepts, where it differs from the newest release.

## Summary

| | Original | Current |
|---|---|---|
| Known PHP-package vulnerabilities (`composer audit`, 2026-11-26) | 🔴 **86 advisories in 19 packages** (5 critical: Laravel, PhpSpreadsheet ×2, webklex laravel-imap, webklex php-imap) | 🟢 **0** (the 5 Symfony advisories of 2026-11-25 were closed by N06, Symfony 8.1.12) |
| Known npm vulnerabilities (`npm audit`, app + e2e) | not measured (Laravel Mix 4 / webpack 4, no lock file) | 🟢 0 |
| PHP | 🔴 8.1 (end of life since 2025-12-31) | 🟡 8.5 in the image, 8.4 + 8.5 in CI (image rebuild needed for the PHP fixes of 2026-11-19) |
| Framework | 🔴 Laravel 8 (security fixes ended 2023-01-24) | 🟡 Laravel 13.42 (supported until 2028-03-17; 13.44.1 out) |
| Database | 🔴 MariaDB 10.4 (end of life 2024-06-18) / MySQL 8.0 in Docker (end of life 2026-04) | 🟢 MySQL 8.4 LTS |
| Old browser code still served | 🔴 jQuery 1.9 / 3.1, Bootstrap 3.3.7, Vue 2 on every page | 🔴 only on the login, registration and password-reset pages (removed by Z01) |

## 1. Servers and runtime

| Component | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| [Operating system](#c-operating-system "The system the application server runs on. Before the rebuild, production ran directly on Windows 11 through XAMPP (a bundle of Apache, PHP and MariaDB for Windows). The rebuilt app runs in a Docker container based on Ubuntu Linux, so production, development and CI all use the same system and get Ubuntu's security updates.") | Production: Windows 11 + XAMPP 3.3.0. Upstream Docker: `php:8.1-fpm` (Debian) | Docker `ubuntu:26.04` LTS (`Dockerfile`) | Ubuntu 26.04 LTS | 🟢 | Ubuntu security updates until 2031. Production moves to this Docker stack at deployment. |
| [PHP](#c-php "The programming language the whole application is written in. Every page, every save and every e-mail goes through the PHP interpreter. Each PHP version gets two years of bug fixes and two more of security fixes; after that, holes found in it stay open.") | 🔴 Production 8.1.25 (ZTS, Apache module); upstream Docker 8.1 | Docker image: Ubuntu's `php8.5` (8.5.4 + Ubuntu's back-ported fixes); CI: 8.4 and 8.5 (newest patch via setup-php); `composer.json` platform 8.4.1 | **8.6.0** (2026-11-19); security releases **8.5.13 / 8.4.28** (2026-11-19) | 🔴 original / 🟡 current | 8.1 is end of life (2025-12-31) and gets none of the 2026 fixes, among them the critical ones of October ("PHP2Shell") and 2026-11-19 (e.g. CVE-2026-129289, PHP-FPM request smuggling, 9.2). **Current: check that the image carries Ubuntu's fix for the 2026-11-19 advisories and rebuild it** (`./dev.sh` rebuild, then `php -v` / `apt changelog php8.5-fpm`); we could not confirm Ubuntu's package from here. Laravel 13 is tested on PHP 8.3–8.5; **8.6 not yet** (wait for Laravel / packages). |
| [PHP IMAP](#c-php-imap "How the app reads the mailbox for the Εισερχόμενα (inbox) page. The original used PHP's ext-imap extension, built on the old c-client library. The rebuilt app uses a PHP library that talks to the mail server directly, so no extension is needed.") | 🔴 `ext-imap` (c-client) | none: `webklex/php-imap` speaks IMAP over sockets | — | 🟢 | `ext-imap` left PHP core in 8.4; c-client is unmaintained. |
| [PHP extensions](#c-php-extensions "Optional parts of PHP the app needs: the MySQL driver (pdo_mysql), text in Greek and other alphabets (mbstring, intl), ZIP and Office files (zip, xml), images and the stamp (gd), compressed files (bz2), and web requests (curl). They come with PHP and have the same version.") | pdo_mysql, mbstring, zip, exif, pcntl, gd, bz2, imap | pdo_mysql, sqlite3, mbstring, xml, zip, gd, intl, bcmath, bz2, curl | — | 🟢 | Ubuntu packages, same version as PHP. |
| [Database](#c-database "Where every protocol, user, folder, setting and assignment is stored. The old production server uses MariaDB (a MySQL offshoot shipped with XAMPP). The new stack uses MySQL 8.4, a long-term-support release; the owner's data is moved into it at deployment.") | 🔴 Production **MariaDB 10.4.32**; upstream Docker **MySQL 8.0** | **MySQL 8.4** LTS (`mysql:8.4`, newest 8.4.x at pull; CI the same) | 8.4.13 (LTS); **9.7.4** (newest LTS, 2026-10-20); 26.10 (innovation) | 🔴 original / 🟢 current | MariaDB 10.4: end of life 2024-06-18. MySQL 8.0: end of life 2026-04. MySQL 8.4: premier support until 2029-04, extended until 2032-04. 9.7 LTS is a later option (needs a test run). |
| [Web server](#c-web-server "The program that receives the browser's requests and hands them to PHP. Production runs Apache with PHP built in (XAMPP). The new stack uses nginx in front of PHP-FPM, PHP's own process manager.") | 🔴 Production **Apache 2.4.58** (XAMPP 8.1.25 bundle); upstream Docker `nginx:latest` | `nginx:stable` (follows the stable branch at pull: 1.30.x) + PHP-FPM | nginx **1.30.6** stable / 1.31.8 mainline; Apache 2.4.70 | 🔴 original / 🟢 current | Apache 2.4.58 misses the security fixes of 2.4.59–2.4.70 (several high ones, e.g. HTTP/2 and mod_rewrite). `stable` is a moving tag: `docker compose pull` gets the patches. |
| [Node.js (build only)](#c-node-js-build-only "A JavaScript runtime used only while building the image: it compiles the styles and scripts of the new pages into the files the browser downloads. Nothing runs on Node.js when the app is in use.") | Laravel Mix 4 (webpack 4); version not recorded | Image: Ubuntu's **Node 22.22**; CI: Node 22 | **Node 26** (Active LTS since 2026-10-28); 24 (maintenance LTS) | 🟡 | Node 22 is in maintenance, end of life 2027-04-30. Used only to build the CSS/JS; nothing runs on Node in production. |
| [npm (build only)](#c-npm-build-only "Node's package installer. During the image build it installs the browser libraries listed in package-lock.json (Bootstrap, Vite and so on) at exactly the recorded versions.") | — | Ubuntu's npm 9.2 | npm 10 / 11 (with Node 24 / 26) | 🟠 | Old, but only installs from `package-lock.json` at build time. |
| [Mail test servers (dev only)](#c-mail-test-servers-dev-only "Pretend mail servers for development and the browser tests. GreenMail plays the inbox the app reads; Mailpit catches the e-mails the app sends, so no real message leaves the test setup. They are not part of production.") | — | GreenMail `2.1.3`; Mailpit `latest` | GreenMail 2.1.8 | 🟡 | Not part of production. |

## 2. PHP framework and libraries (`composer.json`)

| Package | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| [laravel/framework](#c-laravel-framework "The framework the application is built on. It handles web addresses (routes), log-in and sessions, the database layer, e-mail, scheduled jobs, validation and security protections such as CSRF tokens. Most of the app's code is written on top of it.") | 🔴 8.83.27 | 13.42.0 | **13.44.1** (2026-11-24) | 🔴 original / 🟡 current | Laravel 8: 7 advisories (1 critical, 2 high), security fixes ended 2023-01-24. Laravel 13: bug fixes until Q3 2027, security until 2028-03-17; Laravel 14 expected Q1 2027. |
| [symfony/* (Laravel's base)](#c-symfony-laravel-s-base "A set of components Laravel is built from: reading HTTP requests and writing responses, the router, sending e-mail (mailer), MIME types, console commands, translations. Their security fixes reach the app through Laravel.") | 🔴 5.4.23 | **8.1.12** (console, finder, http-foundation, http-kernel, mailer, mime, process, routing, string, translation, var-dumper; N06), others 8.1.5–8.1.10 | 8.1.12; 8.2 in RC | 🔴 original / 🟢 current | Original: 17 advisories across http-foundation, http-kernel, mime, process, routing. Current: CVE-2026-133743 (http-kernel), CVE-2026-133751 / -133755 (mailer, STARTTLS man-in-the-middle), CVE-2026-133746 / -133754 (routing, URLs to another host), published 2026-11-25, all closed by 8.1.12 (N06, PR #147). |
| [livewire/livewire (+ Alpine)](#c-livewire-livewire-alpine-js "Makes the rebuilt pages interactive without a separate JavaScript application: forms, the list, the inbox and the settings update in place when you click or type, while the logic stays in PHP. It brings Alpine.js, a small library for behaviour inside the page.") | — | 4.4.8 | 4.4.10 (2026-11-24) | 🟡 | New pages (N03). No open advisory for 4.4.8. |
| [laravel/ui](#c-laravel-ui "Laravel's older ready-made log-in, registration and password-reset screens. In this app only those screens still use it; they move to the new layout when the old code is removed (Z01).") | 3.4.6 | 4.6.3 | 4.6.3 | 🟢 | Only the old login / password screens; goes with Z01. |
| [laravel/tinker](#c-laravel-tinker "An interactive console for running PHP commands against the app (php artisan tinker). Used for maintenance and investigation, never by the web pages.") | 2.8.1 | 3.1.0 | 3.2.0 | 🟡 | Console only. |
| [maatwebsite/excel](#c-maatwebsite-excel "Exports to Excel: the protocol book and the lists saved as .xlsx files. It sits on top of PhpSpreadsheet.") | 🔴 3.1.48 | 4.0.6 | 4.0.7 | 🔴 original / 🟡 current | Original: 1 high advisory. |
| [phpoffice/phpspreadsheet](#c-phpoffice-phpspreadsheet "Reads and writes spreadsheets (Excel and LibreOffice). The app uses it for the Excel exports and to show the in-app preview of attached spreadsheets.") | 🔴 1.28.0 | 5.11.0 | 5.12.0 (2026-11-22) | 🔴 original / 🟡 current | Original: **31 advisories (2 critical, 17 high)**. |
| [phpoffice/phpword](#c-phpoffice-phpword "Reads and writes Word documents. The app uses it to put the protocol stamp into .docx attachments and to show the in-app preview of Word files.") | 0.18.3 | 1.4.0 | 1.4.0 | 🟢 | Word stamp and preview (R4c, R4e). |
| [webklex/laravel-imap + php-imap](#c-webklex-laravel-imap-php-imap "Connects to the mailbox over IMAP for the Εισερχόμενα page: lists the e-mails, reads their text and attachments, and marks them as read, so they can be registered as protocols.") | 🔴 2.4.0 / 2.7.2 | 6.2.0 | 6.2.0 (2025-04-25) | 🔴 original / 🟢 current | Original: 1 critical advisory each. Upstream releases are slow (last 2025-04); watch it. |
| [zbateson/mail-mime-parser](#c-zbateson-mail-mime-parser "Takes a raw e-mail apart: sender, subject, text in any encoding (Greek included), and each attachment. Used when inbox e-mails are shown and registered.") | 🔴 2.4.0 | 4.0.9 | 4.0.9 | 🔴 original / 🟢 current | Original: 2 high advisories. |
| [yasapurnama/document-watermark](#c-yasapurnama-document-watermark "The original app's tool for putting the stamp on PDF files (through the markpdf program). Since R4e the app has its own PDF stamper; this library is kept only as a fallback for PDFs the new stamper cannot read.") | 1.0.1 | `dev-main` of the fork `g-theodoroy/document-watermark` (da116bc, 2026-11-10) | upstream 1.0.1 (2022) | 🟠 | Unmaintained upstream; since R4e it is only the fallback (markpdf) for PDFs the app's own stamper cannot read. Candidate for removal. |
| [guzzlehttp/guzzle (via Laravel)](#c-guzzlehttp-guzzle-via-laravel "An HTTP client: lets PHP code call other web services. Laravel uses it for outgoing web requests; the app itself makes few or none.") | — (only `guzzlehttp/psr7` 2.5.0 🔴) | 7.16.1 | **8.6.0** | 🟠 | Laravel 13 accepts 7.8+ or 8; no advisory for 7.16.1. Original `guzzlehttp/psr7` 2.5.0: 4 medium advisories. |
| [league/commonmark (via Laravel)](#c-league-commonmark-via-laravel "Turns Markdown text into HTML. Laravel uses it for its own formatted messages, for example in e-mails written in Markdown.") | 🔴 2.4.0 | 2.10.7 | 2.10.9 (2026-11-25) | 🔴 original / 🟡 current | Original: 15 advisories (9 high). |
| [league/flysystem (via Laravel)](#c-league-flysystem-via-laravel "The file-storage layer behind Laravel's Storage: it saves, moves, reads and deletes the attachment files in the archive and the trash.") | 🔴 1.1.10 | 3.36.0 | 3.36.0 | 🔴 original / 🟢 current | Flysystem 1 is unmaintained; 1 low advisory. |
| [monolog/monolog](#c-monolog-monolog "Writes the application log (storage/logs): errors, warnings and notices, which the Αρχεία καταγραφής page shows.") | 2.9.1 | 3.12.1 | 3.13.0 | 🟡 | |
| [nesbot/carbon](#c-nesbot-carbon "Date and time handling: protocol dates, «σήμερα / χθες / εβδομάδα» searches, retention days for the trash, and dates shown in Greek.") | 🔴 2.66.0 | 3.15.1 | 3.15.1 | 🔴 original / 🟢 current | Original: 1 medium advisory. |
| [psy/psysh](#c-psy-psysh "The interactive shell inside laravel/tinker. Console only.") | 🔴 0.11.18 | 0.12.24 | 0.12.24 | 🟢 | Original: 1 medium advisory. |

**Removed since the original** (not needed or abandoned): `fideloper/proxy` (built into Laravel), `fruitcake/laravel-cors`
(abandoned, built into Laravel), `joshrainwater/active-users` (replaced by the app's own `TouchUserActivity`),
`swiftmailer/swiftmailer` (abandoned → `symfony/mailer`), `facade/ignition` (abandoned), `barryvdh/laravel-debugbar` →
`fruitcake/laravel-debugbar`, PHP `ext-imap`.

## 3. Browser libraries

### New pages (`package.json`, built by Vite)

| Package | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| [Bootstrap](#c-bootstrap "The look of the pages: layout grid, buttons, forms, menus, dialogs and tooltips. The new pages use Bootstrap 5; the old ones used Bootstrap 3.") | 🔴 3.3.7 (served) / ^4 in package.json | 5.3.9 | 5.3.9 | 🟢 | Bootstrap 3 and 4 are end of life. |
| [Bootstrap Icons](#c-bootstrap-icons "The small icons on buttons and menus (trash can, paper clip, eye for the preview and so on).") | — | 1.13.2 | 1.13.2 | 🟢 | |
| [@popperjs/core](#c-popperjs-core "Positions pop-ups next to the element they belong to: Bootstrap's drop-down menus, tooltips and popovers use it.") | popper.js 1.x | 2.11.8 | 2.11.8 | 🟢 | The version Bootstrap 5 needs. |
| [Vite](#c-vite "The build tool: it bundles and minifies the new pages' scripts and styles at image build time. It is not shipped to the browser and not used while the app runs.") | Laravel Mix 4 (webpack 4) | 7.3.8 | **8.3.8** | 🟠 | Build tool only; no advisory for 7.3.8. |
| [laravel-vite-plugin](#c-laravel-vite-plugin "Connects Vite to Laravel, so pages load the right built files (with their version hashes) and development reloads them automatically.") | — | 2.1.0 | **3.2.1** | 🟠 | Goes with Vite 8. |
| [sass](#c-sass "Compiles the stylesheets written in SCSS (variables, nesting) into ordinary CSS during the build.") | 1.x | 1.108.2 | 1.108.2 | 🟢 | |

### Old pages still served (`public/js`, `public/css`, `layouts/app.blade.php`)

Only the **login, registration and password-reset pages** still use the old layout; Z01 removes it and these files.

| Library | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| [jQuery (inside public/js/app.js)](#c-jquery-inside-public-js-app-js "An older JavaScript library for changing the page and sending requests; the original app's scripts were built on it. Today it only runs on the old log-in screens.") | 3.1.0 | 3.1.0 | 4.1.0 | 🔴 | CVE-2019-11358, CVE-2020-11022, CVE-2020-11023 (XSS). `public/js/jquery.min.js` 1.9.1, which no page loaded, was removed (Z01a, 2026-11-28). |
| [Bootstrap 3 JS + CSS (app.js, bootstrap.min.css)](#c-bootstrap-3-js-css "The original app's look and its menus, dialogs and tooltips. Bootstrap 3 has had no updates since 2019.") | 3.3.7 | 3.3.7 | 5.3.9 | 🔴 | End of life; CVE-2018-14040, -14041, -14042, CVE-2019-8331 (XSS). |
| [Vue (inside app.js)](#c-vue-inside-app-js "A JavaScript framework bundled into the original app's script file. The rebuilt pages do not use it.") | 2.x | 2.x | 3.x | 🔴 | Vue 2 end of life 2023-12-31. |
| [bootstrap-select](#c-bootstrap-select "The original app's searchable drop-down lists (for example choosing a folder or a user).") | 1.12.2 | 1.12.2 | 1.13.18 | 🔴 | CVE-2019-20921 (XSS, fixed in 1.13.6). |
| [bootstrap-datepicker](#c-bootstrap-datepicker "The original app's calendar pop-up for choosing dates.") | 1.7.0-dev | 1.7.0-dev | 1.10.1 | 🟠 | |
| [jasny-bootstrap (fileinput)](#c-jasny-bootstrap-fileinput "The original app's file-picker fields for attachments.") | 3.1.3 | 3.1.3 | 4.0.0 | 🟠 | Unmaintained. |
| [toastr](#c-toastr "The original app's small pop-up notices («Αποθηκεύτηκε», errors) in the corner of the screen.") | 2.1.x | 2.1.x | 2.1.4 (2018) | 🟠 | Unmaintained. |
| [Bootstrap 3.3.7 CSS from maxcdn in viewEmail.blade.php](#c-bootstrap-3-3-7-css-in-viewemail-blade-php "The stylesheet link inside the HTML copy of an e-mail that the app saves when an inbox e-mail is registered. It only styles that saved copy and runs no script.") | yes | yes | — | 🟠 | Only in the HTML copy saved when an e-mail is registered (no script). |

## 4. Development and test tools

| Tool | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| [PHPUnit](#c-phpunit "Runs the automated tests of the PHP code (more than 500 of them) on every pull request, against a real MySQL database.") | 🔴 9.6.8 | 13.4.7 | 13.4.8 | 🔴 original / 🟡 current | Original: 1 high advisory; 9.x is end of life. |
| [nunomaduro/collision](#c-nunomaduro-collision "Shows clear, readable error reports in the console when a command or test fails.") | 5.11.0 | 8.9.5 | 8.9.6 | 🟡 | |
| [fruitcake/laravel-debugbar (dev)](#c-fruitcake-laravel-debugbar "A toolbar shown only in development: the database queries, timings and memory of each page. Disabled in production.") | 🔴 barryvdh 3.7.0 (maximebf/debugbar 1.18.2) | 4.4.5 | 4.4.5 | 🟢 | Original: 2 medium advisories. Development only. |
| [beyondcode/laravel-query-detector](#c-beyondcode-laravel-query-detector "Warns during development when a page runs the same database query again and again (the 'N+1' problem), which makes long lists slow.") | 1.7.0 | 2.3.0 | 2.3.0 | 🟢 | |
| [fakerphp/faker](#c-fakerphp-faker "Generates invented test data (names, subjects, dates), so the tests and the demo never need real protocols.") | 1.22.0 | 1.24.1 | 1.24.1 | 🟢 | |
| [mockery/mockery](#c-mockery-mockery "Lets tests replace a real service (for example the mail server) with a stand-in whose answers the test controls.") | 1.5.1 | 1.6.15 | 1.6.15 | 🟢 | |
| [Playwright (e2e/)](#c-playwright-e2e "Drives a real browser through the running app (log in, register a protocol, use the inbox) and saves screenshots as evidence for each task.") | — | 1.63.0 | 1.65.1 | 🟡 | |
| [GitHub Actions (checkout, cache, setup-node, setup-python, upload-artifact)](#c-github-actions "The steps GitHub runs on every pull request: check out the code, cache packages, set up PHP / Node / Python, upload test results.") | — | `@v4` / `@v5` | newer major versions (Node 24 runtime) | 🟡 | Work today; update when GitHub stops the Node 20 runtime. |

## What to do

1. **Production (at deployment, not before — owner's rule of 2026-11-16):** the old server (PHP 8.1, Apache 2.4.58,
   MariaDB 10.4, Laravel 8 code with 86 known advisories) is past end of life with critical fixes missing. The move to
   the Docker stack replaces all of it; until then keep it reachable only from the internal network.
2. **Docker image:** rebuild it once Ubuntu's `php8.5` package carries the 2026-11-19 PHP security fixes; check with
   `php -v` / `apt changelog php8.5-fpm` in the container. This matters most for PHP-FPM (CVE-2026-129289).
3. **Z01** removes the old layout and its browser libraries (the 🔴 rows of section 3).
4. Patch updates within the current versions (Laravel 13.44, Livewire 4.4.10, PhpSpreadsheet 5.12, Excel 4.0.7,
   commonmark 2.10.9, PHPUnit 13.4.8, Playwright 1.65): one small task before the deployment, with the full test run.
5. Optional, needs the owner: Vite 8 + laravel-vite-plugin 3, Node 24/26 in the image and CI, removing
   `document-watermark`, MySQL 9.7 LTS later. PHP 8.6 once Laravel and the packages support it.
6. **V02** (after Z03): update this report with the versions of the release.

## Expected after Batch 10 (forecast, 2026-11-27)

What V02 should find, from the Batch 10 tasks as planned (A01–A06, X01–X02, Z01–Z04). A forecast, not a promise: the
owner's decisions and new releases until then can change it.

| Component | Now | Expected after Batch 10 | Why | Likelihood |
|---|---|---|---|---|
| jQuery 3.1.0, Bootstrap 3.3.7, Vue 2, bootstrap-select, bootstrap-datepicker, jasny-bootstrap, toastr (`public/js`, `public/css`) | 🔴 / 🟠 on the log-in, registration and password-reset pages | **removed** | Z01 removes the old layout, its plugins and `public/js`, `public/css`; the auth pages move to the new layout (Bootstrap 5) | certain (planned) |
| Bootstrap 3.3.7 CSS link in `viewEmail.blade.php` | 🟠 | **removed** or replaced by inline styles | Z01 (old views) and Z02 (CSP: no outside stylesheets) | likely |
| laravel/ui | 🟢 4.6.3 | **removed** | its traits only serve the old log-in / password controllers that Z01 rewrites | likely |
| yasapurnama/document-watermark | 🟠 dev-main | **removed**, or kept as the PDF fallback | the old `ProtocolController` that uses it goes in Z01; `Stamp.php` still calls its markpdf as a fallback, so removing it is the owner's call | possible |
| Laravel, Livewire, PhpSpreadsheet, Laravel Excel, Symfony, commonmark, monolog, tinker, PHPUnit, collision, Playwright | 🟡 one patch / minor behind | 🟢 **newest 13.x / 4.x / 5.x / 8.1.x … patch releases** | Z03's dependency audit and update before the release (same major versions, no new packages) | likely |
| PHP in the Docker image | 🟡 8.5 (Ubuntu 8.5.4 + fixes), rebuild pending | 🟢 **8.5 with Ubuntu's fixes of 2026-11-19 or later** | the image is rebuilt for the release | likely |
| MySQL 8.4, nginx stable, GreenMail | 🟢 / 🟡 | newest **8.4.x**, **1.30.x**; GreenMail 2.1.x | moving image tags pull the patches; GreenMail only if Z03 touches dev images | likely / possible |
| GitHub Actions | 🟡 @v4 / @v5 | newer majors | only if GitHub ends the Node 20 runtime first, or Z03 tidies CI | possible |
| Audit log, integrity checks, security headers (X01–X02, A01–A06, Z02) | — | **no new libraries expected** | built on Laravel itself; new packages need the owner's approval | likely |
| PHP 8.6, Laravel 14, MySQL 9.7, Node 24 / 26, Vite 8, Guzzle 8, Ubuntu | as now | **unchanged** | major upgrades are not in Batch 10; each needs the owner. Laravel 14 is expected in Q1 2027 and would be a task of its own | likely |
| Original column, production server | as now | **unchanged** | history; the production server changes only at the deployment | certain |

**Expected count of the current rows:** red 4 → **0**, orange 9 → about **4–5** (npm, Guzzle 8, Vite 8, laravel-vite-plugin 3,
perhaps document-watermark), most yellow rows → green. The "Newest available" column will move on regardless, as new
versions come out.

## What each component does

Point at a component's name in the tables above to read this as a tooltip; on a phone, tap the name to jump here.

<a id="c-operating-system"></a>**Operating system** — The system the application server runs on. Before the rebuild, production ran directly on Windows 11 through XAMPP (a bundle of Apache, PHP and MariaDB for Windows). The rebuilt app runs in a Docker container based on Ubuntu Linux, so production, development and CI all use the same system and get Ubuntu's security updates.

<a id="c-php"></a>**PHP** — The programming language the whole application is written in. Every page, every save and every e-mail goes through the PHP interpreter. Each PHP version gets two years of bug fixes and two more of security fixes; after that, holes found in it stay open.

<a id="c-php-imap"></a>**PHP IMAP** — How the app reads the mailbox for the Εισερχόμενα (inbox) page. The original used PHP's ext-imap extension, built on the old c-client library. The rebuilt app uses a PHP library that talks to the mail server directly, so no extension is needed.

<a id="c-php-extensions"></a>**PHP extensions** — Optional parts of PHP the app needs: the MySQL driver (pdo_mysql), text in Greek and other alphabets (mbstring, intl), ZIP and Office files (zip, xml), images and the stamp (gd), compressed files (bz2), and web requests (curl). They come with PHP and have the same version.

<a id="c-database"></a>**Database** — Where every protocol, user, folder, setting and assignment is stored. The old production server uses MariaDB (a MySQL offshoot shipped with XAMPP). The new stack uses MySQL 8.4, a long-term-support release; the owner's data is moved into it at deployment.

<a id="c-web-server"></a>**Web server** — The program that receives the browser's requests and hands them to PHP. Production runs Apache with PHP built in (XAMPP). The new stack uses nginx in front of PHP-FPM, PHP's own process manager.

<a id="c-node-js-build-only"></a>**Node.js (build only)** — A JavaScript runtime used only while building the image: it compiles the styles and scripts of the new pages into the files the browser downloads. Nothing runs on Node.js when the app is in use.

<a id="c-npm-build-only"></a>**npm (build only)** — Node's package installer. During the image build it installs the browser libraries listed in package-lock.json (Bootstrap, Vite and so on) at exactly the recorded versions.

<a id="c-mail-test-servers-dev-only"></a>**Mail test servers (dev only)** — Pretend mail servers for development and the browser tests. GreenMail plays the inbox the app reads; Mailpit catches the e-mails the app sends, so no real message leaves the test setup. They are not part of production.

<a id="c-laravel-framework"></a>**laravel/framework** — The framework the application is built on. It handles web addresses (routes), log-in and sessions, the database layer, e-mail, scheduled jobs, validation and security protections such as CSRF tokens. Most of the app's code is written on top of it.

<a id="c-symfony-laravel-s-base"></a>**symfony/* (Laravel's base)** — A set of components Laravel is built from: reading HTTP requests and writing responses, the router, sending e-mail (mailer), MIME types, console commands, translations. Their security fixes reach the app through Laravel.

<a id="c-livewire-livewire-alpine-js"></a>**livewire/livewire (+ Alpine)** — Makes the rebuilt pages interactive without a separate JavaScript application: forms, the list, the inbox and the settings update in place when you click or type, while the logic stays in PHP. It brings Alpine.js, a small library for behaviour inside the page.

<a id="c-laravel-ui"></a>**laravel/ui** — Laravel's older ready-made log-in, registration and password-reset screens. In this app only those screens still use it; they move to the new layout when the old code is removed (Z01).

<a id="c-laravel-tinker"></a>**laravel/tinker** — An interactive console for running PHP commands against the app (php artisan tinker). Used for maintenance and investigation, never by the web pages.

<a id="c-maatwebsite-excel"></a>**maatwebsite/excel** — Exports to Excel: the protocol book and the lists saved as .xlsx files. It sits on top of PhpSpreadsheet.

<a id="c-phpoffice-phpspreadsheet"></a>**phpoffice/phpspreadsheet** — Reads and writes spreadsheets (Excel and LibreOffice). The app uses it for the Excel exports and to show the in-app preview of attached spreadsheets.

<a id="c-phpoffice-phpword"></a>**phpoffice/phpword** — Reads and writes Word documents. The app uses it to put the protocol stamp into .docx attachments and to show the in-app preview of Word files.

<a id="c-webklex-laravel-imap-php-imap"></a>**webklex/laravel-imap + php-imap** — Connects to the mailbox over IMAP for the Εισερχόμενα page: lists the e-mails, reads their text and attachments, and marks them as read, so they can be registered as protocols.

<a id="c-zbateson-mail-mime-parser"></a>**zbateson/mail-mime-parser** — Takes a raw e-mail apart: sender, subject, text in any encoding (Greek included), and each attachment. Used when inbox e-mails are shown and registered.

<a id="c-yasapurnama-document-watermark"></a>**yasapurnama/document-watermark** — The original app's tool for putting the stamp on PDF files (through the markpdf program). Since R4e the app has its own PDF stamper; this library is kept only as a fallback for PDFs the new stamper cannot read.

<a id="c-guzzlehttp-guzzle-via-laravel"></a>**guzzlehttp/guzzle (via Laravel)** — An HTTP client: lets PHP code call other web services. Laravel uses it for outgoing web requests; the app itself makes few or none.

<a id="c-league-commonmark-via-laravel"></a>**league/commonmark (via Laravel)** — Turns Markdown text into HTML. Laravel uses it for its own formatted messages, for example in e-mails written in Markdown.

<a id="c-league-flysystem-via-laravel"></a>**league/flysystem (via Laravel)** — The file-storage layer behind Laravel's Storage: it saves, moves, reads and deletes the attachment files in the archive and the trash.

<a id="c-monolog-monolog"></a>**monolog/monolog** — Writes the application log (storage/logs): errors, warnings and notices, which the Αρχεία καταγραφής page shows.

<a id="c-nesbot-carbon"></a>**nesbot/carbon** — Date and time handling: protocol dates, «σήμερα / χθες / εβδομάδα» searches, retention days for the trash, and dates shown in Greek.

<a id="c-psy-psysh"></a>**psy/psysh** — The interactive shell inside laravel/tinker. Console only.

<a id="c-bootstrap"></a>**Bootstrap** — The look of the pages: layout grid, buttons, forms, menus, dialogs and tooltips. The new pages use Bootstrap 5; the old ones used Bootstrap 3.

<a id="c-bootstrap-icons"></a>**Bootstrap Icons** — The small icons on buttons and menus (trash can, paper clip, eye for the preview and so on).

<a id="c-popperjs-core"></a>**@popperjs/core** — Positions pop-ups next to the element they belong to: Bootstrap's drop-down menus, tooltips and popovers use it.

<a id="c-vite"></a>**Vite** — The build tool: it bundles and minifies the new pages' scripts and styles at image build time. It is not shipped to the browser and not used while the app runs.

<a id="c-laravel-vite-plugin"></a>**laravel-vite-plugin** — Connects Vite to Laravel, so pages load the right built files (with their version hashes) and development reloads them automatically.

<a id="c-sass"></a>**sass** — Compiles the stylesheets written in SCSS (variables, nesting) into ordinary CSS during the build.

<a id="c-jquery-inside-public-js-app-js"></a>**jQuery (inside `public/js/app.js`)** — An older JavaScript library for changing the page and sending requests; the original app's scripts were built on it. Today it only runs on the old log-in screens.

<a id="c-bootstrap-3-js-css"></a>**Bootstrap 3 JS + CSS (`app.js`, `bootstrap.min.css`)** — The original app's look and its menus, dialogs and tooltips. Bootstrap 3 has had no updates since 2019.

<a id="c-vue-inside-app-js"></a>**Vue (inside `app.js`)** — A JavaScript framework bundled into the original app's script file. The rebuilt pages do not use it.

<a id="c-bootstrap-select"></a>**bootstrap-select** — The original app's searchable drop-down lists (for example choosing a folder or a user).

<a id="c-bootstrap-datepicker"></a>**bootstrap-datepicker** — The original app's calendar pop-up for choosing dates.

<a id="c-jasny-bootstrap-fileinput"></a>**jasny-bootstrap (fileinput)** — The original app's file-picker fields for attachments.

<a id="c-toastr"></a>**toastr** — The original app's small pop-up notices («Αποθηκεύτηκε», errors) in the corner of the screen.

<a id="c-bootstrap-3-3-7-css-in-viewemail-blade-php"></a>**Bootstrap 3.3.7 CSS from maxcdn in `viewEmail.blade.php`** — The stylesheet link inside the HTML copy of an e-mail that the app saves when an inbox e-mail is registered. It only styles that saved copy and runs no script.

<a id="c-phpunit"></a>**PHPUnit** — Runs the automated tests of the PHP code (more than 500 of them) on every pull request, against a real MySQL database.

<a id="c-nunomaduro-collision"></a>**nunomaduro/collision** — Shows clear, readable error reports in the console when a command or test fails.

<a id="c-fruitcake-laravel-debugbar"></a>**fruitcake/laravel-debugbar (dev)** — A toolbar shown only in development: the database queries, timings and memory of each page. Disabled in production.

<a id="c-beyondcode-laravel-query-detector"></a>**beyondcode/laravel-query-detector** — Warns during development when a page runs the same database query again and again (the 'N+1' problem), which makes long lists slow.

<a id="c-fakerphp-faker"></a>**fakerphp/faker** — Generates invented test data (names, subjects, dates), so the tests and the demo never need real protocols.

<a id="c-mockery-mockery"></a>**mockery/mockery** — Lets tests replace a real service (for example the mail server) with a stand-in whose answers the test controls.

<a id="c-playwright-e2e"></a>**Playwright (`e2e/`)** — Drives a real browser through the running app (log in, register a protocol, use the inbox) and saves screenshots as evidence for each task.

<a id="c-github-actions"></a>**GitHub Actions (checkout, cache, setup-node, setup-python, upload-artifact)** — The steps GitHub runs on every pull request: check out the code, cache packages, set up PHP / Node / Python, upload test results.
## How it was checked

- `composer audit --locked` on the current lock file and on the upstream lock file (2026-11-26).
- `npm audit` in the root and in `e2e/` (0 vulnerabilities).
- Newest versions: Packagist (`repo.packagist.org/p2/…`), `npm view`, php.net, Laravel's release policy, MySQL /
  nginx / Node.js release pages, Ubuntu package lists.
- Old browser libraries: version strings in the files under `public/` and the scripts `layouts/app.blade.php` loads.
- No real data was used.
