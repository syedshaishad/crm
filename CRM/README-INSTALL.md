# Standalone Self-Hosted CRM — Install & Resale Guide (v1.0.0)

This is a **single-tenant, self-hosted Laravel CRM**. Each buyer gets an independent
install: own files, own MySQL database, own users/data. There is **no SaaS, no
multi-tenancy, no shared database, and no dependency on any vendor server**.

## 1. Sell / deliver
- Zip this folder (exclude `vendor/`, `node_modules/`, `.env`) as `crm-v1.0.0.zip`.
- Buyer uploads to their hosting (cPanel shared, VPS, any PHP 8.2+ + MySQL/MariaDB host).

## 2. Install on cPanel shared hosting
1. Create MySQL database + user in cPanel → MySQL Databases. Note host/db/user/pass.
2. Upload zip to `/home/USER/crm-app`, extract. `composer install --no-dev -o` via
   cPanel Terminal/SSH (or upload a pre-built `vendor/` from your machine).
3. Copy `crm-app/public/*` → `public_html/` (or point addon domain at `crm-app/public/`).
4. If copied, edit `public_html/index.php` so it loads `/home/USER/crm-app`.
5. Visit `https://domain/install` and follow the 7-step wizard:
   requirements → database → admin → company → mail/SMTP → Razorpay/GST/storage → finish.
6. Installer writes `.env`, runs migrations, creates admin, links storage,
    then writes `storage/framework/installed.lock` and locks itself.

## 3. Cron (overdue invoices + scheduler)
Set one cron entry so `invoices:mark-overdue` runs daily (cPanel → Cron Jobs):
`php /home/USER/crm-app/artisan schedule:run >> /dev/null 2>&1`
(Run every minute; Laravel schedules the daily job itself.)

## 3. White-label per customer
Settings → Branding: company name, logo, favicon, brand colors, address, GSTIN,
invoice prefix/footer/logo, mail sender, login heading. Nothing is hard-coded.

## 4. Licensing (offline-first, signed)
- Default `LICENSE_DRIVER=null` = no enforcement; sell and deploy freely.
- To enforce: buyer sends you their install secret (Settings → License shows
  the fingerprint); you mint offline with
  `php artisan license:make --to="Acme" --type=annual --users=10 --modules=Invoices,Payments --secret=THEIR-SECRET`
  and hand them the file (or `--key` for a `LICENSE_KEY` string).
- Buyer sets `LICENSE_DRIVER=file` (+ `LICENSE_SECRET`) or `LICENSE_DRIVER=key`
  (+ `LICENSE_KEY`), uploads the file in Settings → License. Tampered files are
  refused; expiry/user/module limits enforced; expiry never locks the admin out
  of dashboard, settings, or updates. `LICENSE_ENFORCE_USERS=true` /
  `LICENSE_ENFORCE_MODULES=true` turn on the two limiters.
- No phone-home. See `storage/app/license.json.example` and Settings → License.
  Note the honest limit: whoever roots the server can re-sign — deterrent, not DRM.

## 5. Updates
- Settings → Updates. Drop `update-*.zip` (with `update.php` + `files/` + `migrations/`)
  into `storage/app/updates/`, click Apply: auto DB+file backup → extract →
  migrate → bump `app.version`. See `storage/app/updates/update.php.example`.

## 6. Module toggles
`config/modules.php` lists the bundled modules (Clients/Projects/Tasks/Leads/
Proposals/Invoices/Payments/Expenses/Reports/Documents/Settings/Branding).
It is informational in v1.0.0 — hiding a module is done by removing its nav
entry, not by config alone.

## 7. Requirements
PHP ≥ 8.2, extensions: openssl, pdo(+mysql), mbstring, tokenizer, xml, ctype,
json, bcmath, fileinfo. Writable: `storage/`, `bootstrap/cache`.

