# Standalone Self-Hosted CRM — Install & Resale Guide (v1.0.0)

This is a **single-tenant, self-hosted Laravel CRM**. Each buyer gets an independent
install: own files, own database (MySQL/MariaDB or a single SQLite file), own
users/data. There is **no SaaS, no multi-tenancy, no shared database, and no
dependency on any vendor server**.

## 1. Sell / deliver
- Ship the release zip (`crm-v1.0.0.zip`). It bundles `vendor/` and excludes
  `.env*`, `database/*.sqlite`, logs/backups, generated caches and every
  install/licence identity file (`storage/app/.installation_id`, `.license_*`,
  `license.key`).
- Buyer uploads to their hosting (cPanel shared, VPS, any PHP 8.2+ host with
  `pdo_mysql` or `pdo_sqlite`).

## 2. Install on cPanel shared hosting
1. **Database:** either create a MySQL database + user in cPanel → MySQL
   Databases and note host/db/user/pass, **or** choose SQLite in the wizard and
   leave `database/database.sqlite` as the file path (the installer creates it;
   the containing folder must be writable). SQLite needs no database server and
   suits a single small team; MySQL is better for heavier concurrent use.
2. Upload zip to `/home/USER/crm-app`, extract. `composer install --no-dev -o` via
   cPanel Terminal/SSH (or upload a pre-built `vendor/` from your machine).
3. Copy `crm-app/public/*` → `public_html/` (or point addon domain at `crm-app/public/`).
4. If copied, edit `public_html/index.php` so it loads `/home/USER/crm-app`.
5. Visit `https://domain/install` and follow the 7-step wizard:
   requirements → database → admin → company → mail/SMTP → Razorpay/GST/storage → finish.
6. Installer writes `.env`, runs migrations, creates admin, links storage,
    then writes `storage/framework/installed.lock` and locks itself.
7. If you enable online payments, set **both** Razorpay keys *and*
   `RAZORPAY_WEBHOOK_SECRET`. Without the webhook secret the payment webhook
   endpoint refuses all events, so no gateway payment can be recorded without
   a verified signature.

## 3. Cron (overdue invoices + scheduler)
Set one cron entry so `invoices:mark-overdue` runs daily (cPanel → Cron Jobs):
`php /home/USER/crm-app/artisan schedule:run >> /dev/null 2>&1`
(Run every minute; Laravel schedules the daily job itself.)

## 3. White-label per customer
Settings → Branding: company name, logo, favicon, brand colors, address, GSTIN,
invoice prefix/footer/logo, mail sender, login heading. Nothing is hard-coded.

## 4. Licensing (Licensify server)
- Activation is mandatory in the installer's final step. The app activates
  against `LICENSE_SERVER_URL` (default `https://license.shaishad.com`) with
  the product code and the reseller's API credentials; there is no open mode.
- The server returns an Ed25519-signed certificate with a bounded offline grace
  window, cached in `storage/app/.license_certificate.json`. Every install
  mints its own `storage/app/.installation_id` on first run — never ship it.
- Settings → License shows status, certificate and a Check now action;
  `php artisan license:check|activate|deactivate` are available over SSH.
- The app, token API and client portal fail closed when the licence is invalid.
  Note the honest limit: whoever roots the server can tamper with the code —
  this is a deterrent, not DRM.

## 5. Updates
- **Automatic:** Settings → Updates → *Check for updates*. The license server
  returns the latest release for your license and whether this install may run
  it (PHP/Laravel minimums, major-series cover). *Download & install* fetches a
  short-lived URL, verifies the SHA-256, backs up the database + uploads, then
  applies. Needs `LICENSE_KEY`, `LICENSE_API_KEY` and `LICENSE_API_SECRET`.
- **Manual:** drop `update-*.zip` (with `update.php` + `files/` + `migrations/`)
  into `storage/app/updates/`, or upload it on the same page, and click Apply:
  auto DB+file backup → extract → migrate → bump `app.version`.
  See `storage/app/updates/update.php.example`.

## 6. Module toggles
`config/modules.php` lists the bundled modules (Clients/Projects/Tasks/Leads/
Proposals/Invoices/Payments/Expenses/Reports/Documents/Settings/Branding).
It is informational — hiding a module is done by removing its nav entry, not
by config alone.

## 7. Requirements
PHP ≥ 8.2, extensions: openssl, pdo, mbstring, tokenizer, xml, ctype,
json, bcmath, fileinfo, plus `pdo_mysql` (MySQL install) or `pdo_sqlite`
(SQLite install). Writable: `storage/`, `bootstrap/cache`, and — for SQLite —
the folder holding the database file.

