# CloudPanel deployment (Nginx + PHP-FPM on a VPS)

CloudPanel gives you a Nginx/PHP-FPM stack with a per-site system user, SSH/SFTP,
a MySQL/MariaDB database wizard, Let's Encrypt TLS and a cron UI. The CRM is a
Laravel app whose document root must be the `public/` folder.

## 1. Package (built on your dev machine)
The deployable zip ships WITHOUT `.env`, WITHOUT the install lock, and with a
production-only `vendor/` (`composer install --no-dev -o`), so no composer run is
required on the server. See `packaging notes` below if rebuilding it yourself.

## 2. Create the site + database
1. CloudPanel → Sites → Add Site → "Create a PHP site" (the plain kind, not
   WordPress). Domain: e.g. `crm.example.com`. This makes `/var/www/crm.example.com`
   owned by a site user, with a `html/` web root.
2. CloudPanel → Site → Databases → Add database. Note db name, user, password
   (you will paste these into the installer wizard, step 2 — no manual `.env`
   editing needed).
3. CloudPanel → Site → SSL/SSL Certificates → issue Let's Encrypt (free, auto-
   renews). Do this before opening the installer so the session cookie is secure.

## 3. Upload the files
With the site's SFTP/SSH user (not root — CloudPanel gives you the credentials on
the site page):

    cd /var/www/crm.example.com
    rm -rf html            # remove the placeholder; we publish the app's public/
    unzip /path/to/crm-v1.0.0.zip -d .    # extract so the app sits at the site root
    # you should now have /var/www/crm.example.com/public/index.php etc.

Set the document root to the app's `public/`:
    CloudPanel → Site → Settings → Document Root → /var/www/crm.example.com/public
(Nginx config is regenerated for you; nothing else needs editing.)

Permissions — the site user must own the tree and the runtime dirs must be
writable by PHP-FPM (both are the same user on CloudPanel, so usually a no-op):

    cd /var/www/crm.example.com
    chown -R $(stat -c %U .):$(stat -c %G .) .
    chmod -R 775 storage bootstrap/cache

## 4. Run the installer
Open `https://crm.example.com/install` and follow the wizard:
requirements → database (paste the DB creds from step 2) → admin → company →
mail/SMTP → Razorpay/GST/storage → finish.
The wizard writes `.env`, runs migrations, seeds the admin, links `storage/app/public`
and writes `storage/framework/installed.lock`, then locks itself. Nothing else
needs to run on the command line.

## 5. Cron (overdue invoices + scheduler)
CloudPanel → Site → Cron Jobs → "Add cron job", every minute:

    php /var/www/crm.example.com/artisan schedule:run >> /dev/null 2>&1

Laravel schedules the daily `invoices:mark-overdue` job itself.

## 6. Post-install
- White-label: Settings → Branding (name, logo, colors, invoice prefix/footer).
- Backups: CloudPanel → Site → Backups (file + DB snapshots). Also enable
  Settings → Updates in-app for versioned update packages.
- Optional perf: `php artisan config:route:cache` after install. Re-run
  `php artisan optimize:clear` before/after any update.

## Packaging notes (rebuild from source)
From the project root:

    composer install --no-dev --optimize-autoloader
    # then zip the tree, EXCLUDING:
    #   .env, .env.backup.*, storage/framework/installed.lock,
    #   storage/framework/views/*.php, storage/logs/*.log,
    #   storage/framework/cache/data/*, .freebuff/, .git/,
    #   storage/app/.installation_id, storage/app/.license_certificate.json,
    #   storage/app/.license_keyring.json, storage/app/license.key,
    #   storage/app/license.json (legacy), storage/app/.license_secret (legacy),
    #   **/*.bat, **/*.exe, **/*.sh (the license server's package validator
    #   refuses executables in distributable zips; the app never runs them —
    #   artisan always runs via `php artisan`).
    #   NO dotfiles at all (.gitignore included — the validator refuses them);
    #   required empty storage dirs ship as explicit directory entries.
    # Identity + license state must NEVER ship: each install mints its own
    # installation id and activates its own key on first run.

Ship NO dot-env files at all — the license server's package validator refuses
them (`.env`, `.env.example`, backups). On first request the app seeds a
baseline `.env` itself (drivers, mail, license server, version) and mints the
APP_KEY via the BootstrapInstallerEnv middleware — that's why the app boots
even though no `.env` exists yet. The installer then overwrites DB/mail/brand
values with the buyer's own.

## Requirements
PHP >= 8.2 (CloudPanel lets you pick 8.2/8.3 per site). Extensions: openssl, pdo
(+mysql), mbstring, tokenizer, xml, ctype, json, bcmath, fileinfo, sodium
(sodium verifies license certificates — the app stays locked without it).
Writable:
`storage/`, `bootstrap/cache`. MySQL/MariaDB.

