# Block the public copies of inbox e-mails on the production server (until the final deployment)

The code running on the production server saves every e-mail shown in *Εισερχόμενα Email* as
`public/tmp/u<user number>/<message number>.eml` (the whole e-mail, attachments included) and its HTML part as
`….html`. Files under `public/` are served **without logging in**, and both numbers are small and easy to guess, so
anyone who can reach the server can download inbox e-mails. Fixed in R5a (the e-mails are kept in private storage
and old copies are deleted), which reaches the server only with the final deployment. Until then, a web-server
rule refuses these files without changing any app code.

Tested on Apache 2.4 with this app's `public/.htaccess` (both variants: `.eml` and `..`-tricks refused, other files
served); not on the owner's server: run the check below after applying.

## Apache
In the app's `public/.htaccess`, add **right after** `RewriteEngine On` (next to the registration rule, if you
applied it):
```apache
RewriteRule ^tmp/u[0-9]+/.*\.eml$ - [F,NC]
```
This blocks the full e-mails and keeps the old inbox working. To also block the HTML parts (the e-mail preview in
the old inbox then shows an error, and *Προβολή εξωτερικά* stops working), use this line instead:
```apache
RewriteRule ^tmp/u[0-9]+/ - [F,NC]
```
If `.htaccess` files are disabled (`AllowOverride None`), put this in the site's `<VirtualHost>` and reload Apache:
```apache
<LocationMatch "(?i)/tmp/u[0-9]+/.*\.eml$">
    Require all denied
</LocationMatch>
```

## Check
Open the inbox once (so copies exist), then, **without** being logged in (e.g. another browser):
```bash
curl -s -o /dev/null -w '%{http_code}\n' https://YOUR-SERVER/tmp/u1/1.eml    # 403 (was 200 or 404)
```
If the app is in a subfolder, add it to the address (e.g. `https://YOUR-SERVER/protocol/tmp/u1/1.eml`).

## Undo / final deployment
Delete the line or block and reload the web server. At the final deployment remove it (R5a no longer writes these
files and deletes old ones on the first inbox visit). With Apache, remove the line from `public/.htaccess`
**before** `git pull`, or git may refuse to pull.
