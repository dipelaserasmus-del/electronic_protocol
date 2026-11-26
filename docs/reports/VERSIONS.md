# V01 — Versions of the platform, frameworks and libraries

Last update: 2026-11-26 09:48 (Greek time). **To be updated again at the end of the development (task V02, after Z03).**

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
| Known PHP-package vulnerabilities (`composer audit`, 2026-11-26) | 🔴 **86 advisories in 19 packages** (5 critical: Laravel, PhpSpreadsheet ×2, webklex laravel-imap, webklex php-imap) | 🔴 **5** in Symfony 8.1.10 / 8.1.11 (published 2026-11-25; fixed in 8.1.12, a patch update of the lock file; not applied yet) |
| Known npm vulnerabilities (`npm audit`, app + e2e) | not measured (Laravel Mix 4 / webpack 4, no lock file) | 🟢 0 |
| PHP | 🔴 8.1 (end of life since 2025-12-31) | 🟡 8.5 in the image, 8.4 + 8.5 in CI (image rebuild needed for the PHP fixes of 2026-11-19) |
| Framework | 🔴 Laravel 8 (security fixes ended 2023-01-24) | 🟡 Laravel 13.42 (supported until 2028-03-17; 13.44.1 out) |
| Database | 🔴 MariaDB 10.4 (end of life 2024-06-18) / MySQL 8.0 in Docker (end of life 2026-04) | 🟢 MySQL 8.4 LTS |
| Old browser code still served | 🔴 jQuery 1.9 / 3.1, Bootstrap 3.3.7, Vue 2 on every page | 🔴 only on the login, registration and password-reset pages (removed by Z01) |

## 1. Servers and runtime

| Component | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| Operating system | Production: Windows 11 + XAMPP 3.3.0. Upstream Docker: `php:8.1-fpm` (Debian) | Docker `ubuntu:26.04` LTS (`Dockerfile`) | Ubuntu 26.04 LTS | 🟢 | Ubuntu security updates until 2031. Production moves to this Docker stack at deployment. |
| PHP | 🔴 Production 8.1.25 (ZTS, Apache module); upstream Docker 8.1 | Docker image: Ubuntu's `php8.5` (8.5.4 + Ubuntu's back-ported fixes); CI: 8.4 and 8.5 (newest patch via setup-php); `composer.json` platform 8.4.1 | **8.6.0** (2026-11-19); security releases **8.5.13 / 8.4.28** (2026-11-19) | 🔴 original / 🟡 current | 8.1 is end of life (2025-12-31) and gets none of the 2026 fixes, among them the critical ones of October ("PHP2Shell") and 2026-11-19 (e.g. CVE-2026-129289, PHP-FPM request smuggling, 9.2). **Current: check that the image carries Ubuntu's fix for the 2026-11-19 advisories and rebuild it** (`./dev.sh` rebuild, then `php -v` / `apt changelog php8.5-fpm`); we could not confirm Ubuntu's package from here. Laravel 13 is tested on PHP 8.3–8.5; **8.6 not yet** (wait for Laravel / packages). |
| PHP IMAP | 🔴 `ext-imap` (c-client) | none: `webklex/php-imap` speaks IMAP over sockets | — | 🟢 | `ext-imap` left PHP core in 8.4; c-client is unmaintained. |
| PHP extensions | pdo_mysql, mbstring, zip, exif, pcntl, gd, bz2, imap | pdo_mysql, sqlite3, mbstring, xml, zip, gd, intl, bcmath, bz2, curl | — | 🟢 | Ubuntu packages, same version as PHP. |
| Database | 🔴 Production **MariaDB 10.4.32**; upstream Docker **MySQL 8.0** | **MySQL 8.4** LTS (`mysql:8.4`, newest 8.4.x at pull; CI the same) | 8.4.13 (LTS); **9.7.4** (newest LTS, 2026-10-20); 26.10 (innovation) | 🔴 original / 🟢 current | MariaDB 10.4: end of life 2024-06-18. MySQL 8.0: end of life 2026-04. MySQL 8.4: premier support until 2029-04, extended until 2032-04. 9.7 LTS is a later option (needs a test run). |
| Web server | 🔴 Production **Apache 2.4.58** (XAMPP 8.1.25 bundle); upstream Docker `nginx:latest` | `nginx:stable` (follows the stable branch at pull: 1.30.x) + PHP-FPM | nginx **1.30.6** stable / 1.31.8 mainline; Apache 2.4.70 | 🔴 original / 🟢 current | Apache 2.4.58 misses the security fixes of 2.4.59–2.4.70 (several high ones, e.g. HTTP/2 and mod_rewrite). `stable` is a moving tag: `docker compose pull` gets the patches. |
| Node.js (build only) | Laravel Mix 4 (webpack 4); version not recorded | Image: Ubuntu's **Node 22.22**; CI: Node 22 | **Node 26** (Active LTS since 2026-10-28); 24 (maintenance LTS) | 🟡 | Node 22 is in maintenance, end of life 2027-04-30. Used only to build the CSS/JS; nothing runs on Node in production. |
| npm (build only) | — | Ubuntu's npm 9.2 | npm 10 / 11 (with Node 24 / 26) | 🟠 | Old, but only installs from `package-lock.json` at build time. |
| Mail test servers (dev only) | — | GreenMail `2.1.3`; Mailpit `latest` | GreenMail 2.1.8 | 🟡 | Not part of production. |

## 2. PHP framework and libraries (`composer.json`)

| Package | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| laravel/framework | 🔴 8.83.27 | 13.42.0 | **13.44.1** (2026-11-24) | 🔴 original / 🟡 current | Laravel 8: 7 advisories (1 critical, 2 high), security fixes ended 2023-01-24. Laravel 13: bug fixes until Q3 2027, security until 2028-03-17; Laravel 14 expected Q1 2027. |
| symfony/* (Laravel's base) | 🔴 5.4.23 | 8.1.5–8.1.11 (http-kernel 8.1.11, mailer 8.1.10, routing 8.1.11) | **8.1.12** (2026-11-24/25); 8.2 in RC | 🔴 | Original: 17 advisories across http-foundation, http-kernel, mime, process, routing. **Current: CVE-2026-133743 (http-kernel, forwarded headers in inline fragments), CVE-2026-133751 / -133755 (mailer, STARTTLS man-in-the-middle), CVE-2026-133746 / -133754 (routing, URLs to another host)**, all fixed by 8.1.12. CI's audit job fails on any advisory, so the next PR needs this update (`composer update symfony/http-kernel symfony/mailer symfony/routing --with-dependencies`, checked: lock-only change, audit then clean). |
| livewire/livewire (+ Alpine) | — | 4.4.8 | 4.4.10 (2026-11-24) | 🟡 | New pages (N03). No open advisory for 4.4.8. |
| laravel/ui | 3.4.6 | 4.6.3 | 4.6.3 | 🟢 | Only the old login / password screens; goes with Z01. |
| laravel/tinker | 2.8.1 | 3.1.0 | 3.2.0 | 🟡 | Console only. |
| maatwebsite/excel | 🔴 3.1.48 | 4.0.6 | 4.0.7 | 🔴 original / 🟡 current | Original: 1 high advisory. |
| phpoffice/phpspreadsheet | 🔴 1.28.0 | 5.11.0 | 5.12.0 (2026-11-22) | 🔴 original / 🟡 current | Original: **31 advisories (2 critical, 17 high)**. |
| phpoffice/phpword | 0.18.3 | 1.4.0 | 1.4.0 | 🟢 | Word stamp and preview (R4c, R4e). |
| webklex/laravel-imap + php-imap | 🔴 2.4.0 / 2.7.2 | 6.2.0 | 6.2.0 (2025-04-25) | 🔴 original / 🟢 current | Original: 1 critical advisory each. Upstream releases are slow (last 2025-04); watch it. |
| zbateson/mail-mime-parser | 🔴 2.4.0 | 4.0.9 | 4.0.9 | 🔴 original / 🟢 current | Original: 2 high advisories. |
| yasapurnama/document-watermark | 1.0.1 | `dev-main` of the fork `g-theodoroy/document-watermark` (da116bc, 2026-11-10) | upstream 1.0.1 (2022) | 🟠 | Unmaintained upstream; since R4e it is only the fallback (markpdf) for PDFs the app's own stamper cannot read. Candidate for removal. |
| guzzlehttp/guzzle (via Laravel) | — (only `guzzlehttp/psr7` 2.5.0 🔴) | 7.16.1 | **8.6.0** | 🟠 | Laravel 13 accepts 7.8+ or 8; no advisory for 7.16.1. Original `guzzlehttp/psr7` 2.5.0: 4 medium advisories. |
| league/commonmark (via Laravel) | 🔴 2.4.0 | 2.10.7 | 2.10.9 (2026-11-25) | 🔴 original / 🟡 current | Original: 15 advisories (9 high). |
| league/flysystem (via Laravel) | 🔴 1.1.10 | 3.36.0 | 3.36.0 | 🔴 original / 🟢 current | Flysystem 1 is unmaintained; 1 low advisory. |
| monolog/monolog | 2.9.1 | 3.12.1 | 3.13.0 | 🟡 | |
| nesbot/carbon | 🔴 2.66.0 | 3.15.1 | 3.15.1 | 🔴 original / 🟢 current | Original: 1 medium advisory. |
| psy/psysh | 🔴 0.11.18 | 0.12.24 | 0.12.24 | 🟢 | Original: 1 medium advisory. |

**Removed since the original** (not needed or abandoned): `fideloper/proxy` (built into Laravel), `fruitcake/laravel-cors`
(abandoned, built into Laravel), `joshrainwater/active-users` (replaced by the app's own `TouchUserActivity`),
`swiftmailer/swiftmailer` (abandoned → `symfony/mailer`), `facade/ignition` (abandoned), `barryvdh/laravel-debugbar` →
`fruitcake/laravel-debugbar`, PHP `ext-imap`.

## 3. Browser libraries

### New pages (`package.json`, built by Vite)

| Package | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| Bootstrap | 🔴 3.3.7 (served) / ^4 in package.json | 5.3.9 | 5.3.9 | 🟢 | Bootstrap 3 and 4 are end of life. |
| Bootstrap Icons | — | 1.13.2 | 1.13.2 | 🟢 | |
| @popperjs/core | popper.js 1.x | 2.11.8 | 2.11.8 | 🟢 | The version Bootstrap 5 needs. |
| Vite | Laravel Mix 4 (webpack 4) | 7.3.8 | **8.3.8** | 🟠 | Build tool only; no advisory for 7.3.8. |
| laravel-vite-plugin | — | 2.1.0 | **3.2.1** | 🟠 | Goes with Vite 8. |
| sass | 1.x | 1.108.2 | 1.108.2 | 🟢 | |

### Old pages still served (`public/js`, `public/css`, `layouts/app.blade.php`)

Only the **login, registration and password-reset pages** still use the old layout; Z01 removes it and these files.

| Library | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| jQuery (inside `public/js/app.js`) | 3.1.0 | 3.1.0 | 4.1.0 | 🔴 | CVE-2019-11358, CVE-2020-11022, CVE-2020-11023 (XSS). `public/js/jquery.min.js` 1.9.1 is still in the folder but no page loads it. |
| Bootstrap 3 JS + CSS (`app.js`, `bootstrap.min.css`) | 3.3.7 | 3.3.7 | 5.3.9 | 🔴 | End of life; CVE-2018-14040, -14041, -14042, CVE-2019-8331 (XSS). |
| Vue (inside `app.js`) | 2.x | 2.x | 3.x | 🔴 | Vue 2 end of life 2023-12-31. |
| bootstrap-select | 1.12.2 | 1.12.2 | 1.13.18 | 🔴 | CVE-2019-20921 (XSS, fixed in 1.13.6). |
| bootstrap-datepicker | 1.7.0-dev | 1.7.0-dev | 1.10.1 | 🟠 | |
| jasny-bootstrap (fileinput) | 3.1.3 | 3.1.3 | 4.0.0 | 🟠 | Unmaintained. |
| toastr | 2.1.x | 2.1.x | 2.1.4 (2018) | 🟠 | Unmaintained. |
| Bootstrap 3.3.7 CSS from maxcdn in `viewEmail.blade.php` | yes | yes | — | 🟠 | Only in the HTML copy saved when an e-mail is registered (no script). |

## 4. Development and test tools

| Tool | Original | Current | Newest available | Status | Notes |
|---|---|---|---|---|---|
| PHPUnit | 🔴 9.6.8 | 13.4.7 | 13.4.8 | 🔴 original / 🟡 current | Original: 1 high advisory; 9.x is end of life. |
| nunomaduro/collision | 5.11.0 | 8.9.5 | 8.9.6 | 🟡 | |
| fruitcake/laravel-debugbar (dev) | 🔴 barryvdh 3.7.0 (maximebf/debugbar 1.18.2) | 4.4.5 | 4.4.5 | 🟢 | Original: 2 medium advisories. Development only. |
| beyondcode/laravel-query-detector | 1.7.0 | 2.3.0 | 2.3.0 | 🟢 | |
| fakerphp/faker | 1.22.0 | 1.24.1 | 1.24.1 | 🟢 | |
| mockery/mockery | 1.5.1 | 1.6.15 | 1.6.15 | 🟢 | |
| Playwright (`e2e/`) | — | 1.63.0 | 1.65.1 | 🟡 | |
| GitHub Actions (checkout, cache, setup-node, setup-python, upload-artifact) | — | `@v4` / `@v5` | newer major versions (Node 24 runtime) | 🟡 | Work today; update when GitHub stops the Node 20 runtime. |

## What to do

1. **Symfony 8.1.12** (patch update, section 2): closes the 5 open advisories; also needed for CI's audit job to pass.
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

## How it was checked

- `composer audit --locked` on the current lock file and on the upstream lock file (2026-11-26).
- `npm audit` in the root and in `e2e/` (0 vulnerabilities).
- Newest versions: Packagist (`repo.packagist.org/p2/…`), `npm view`, php.net, Laravel's release policy, MySQL /
  nginx / Node.js release pages, Ubuntu package lists.
- Old browser libraries: version strings in the files under `public/` and the scripts `layouts/app.blade.php` loads.
- No real data was used.
