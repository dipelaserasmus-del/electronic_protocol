# Block new registrations on the production server (until the final deployment)

The code running on the production server lets anyone who can reach it create an **administrator** account
(`POST /register`; fixed in S00, which reaches the server only with the final deployment). The old code ignores
`storage/conf/.denyregister`, so that file does not help. These web-server rules refuse registration without
changing any app code. They cover `/register` and `/index.php/register` (Laravel answers on both). Login, password
reset and everything else keep working.

Not tested on the owner's server: run the checks below after applying.

## Apache
In the app's `public/.htaccess`, add **right after** `RewriteEngine On`:
```apache
RewriteRule ^(index\.php/)?register/?$ - [F,NC]
```
If `.htaccess` files are disabled (`AllowOverride None`), put this in the site's `<VirtualHost>` and reload Apache:
```apache
<LocationMatch "(?i)/(index\.php/)?register/?$">
    Require all denied
</LocationMatch>
```

## nginx
In the site's `server { … }` block, **before** the `location ~ \.php$` block (nginx uses the first matching pattern):
```nginx
location ~* /(index\.php/)?register/?$ { return 403; }
```
Then `sudo nginx -t && sudo systemctl reload nginx`.

## Check
```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://YOUR-SERVER/register             # 403
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://YOUR-SERVER/index.php/register   # 403
curl -s -o /dev/null -w '%{http_code}\n' https://YOUR-SERVER/login                        # 200
```
If the app is in a subfolder, add it to the address (e.g. `https://YOUR-SERVER/protocol/register`).

## Undo / final deployment
Delete the line or block and reload the web server. At the final deployment, remove it (S00 closes registration in
the app). With Apache, remove the line from `public/.htaccess` **before** `git pull`, or git may refuse to pull.
While the rule is on, new users are created by an administrator from the user management page.
