# Shaishad & Co. CRM

A **self-hosted, single-tenant CRM** built for service businesses that manage
sales, delivery, billing and people in one place — no multi-tenancy, no SaaS
dependency, no shared database.

> Version 1.0.0 — standalone Laravel application with an in-app installer,
> licensing flow, signed update packages and white-label branding.

## Highlights

- **Sales pipeline** — leads, proposals, projects and tasks in one operational view, with follow-ups and conversion flow.
- **Billing & money** — invoices (GST-ready with CGST/SGST/IGST split), public client invoice & payment receipt pages, payments register (cash/bank/UPI/gateway), expenses, products & HSN catalogue, purchase orders, cheques (PDC), credit notes, delivery challans, delivery routes, AMC & warranty contracts.
- **Reporting** — revenue, profit & loss, receivables ageing, GSTR-1, client profitability, exports and scheduled reports.
- **People & operations** — HR hub, payroll runs, attendance & leaves, documents, knowledge base, automations, quality loop, global cross-module search.
- **Client portal** — self-service portal where clients view invoices, proposals and work, and pay online.
- **Notifications** — Web Push (VAPID) with per-device subscriptions.
- **White-labeling** — brand colours, logo, favicon, invoice branding, mail sender, login copy per customer.
- **Licensing & updates** — installer wizard, licence activation (Licensify), signed OTA updates with automatic backup.

## Screenshots

The [`landing-screenshots/`](landing-screenshots/) folder contains **41
screenshots** of the demo workspace covering every module, grouped as:

- Overview & sales — dashboard, money dashboard, clients, profiles, ledgers, leads, proposals, projects
- Work & billing — tasks, invoices, public invoice/receipt, payments, expenses, products, POs, cheques, credit notes, challans, routes, AMC
- Reports — overview, P&L, receivables, GSTR-1, client profitability
- People & operations — HR, payroll, attendance, documents, knowledge, automations, quality, search
- Settings & access — settings hub, branding, client portal, sign in

Full-resolution PNGs are **3200×2000**, web-ready WebP copies are **1600×1000** in
`landing-screenshots/web/`, and the browsable gallery is `landing-screenshots/index.html`.

## Tech stack

- PHP ≥ 8.2, Laravel (framework)
- MySQL / MariaDB (production), SQLite for local demo
- Vanilla Blade views + custom CSS/JS frontend assets
- Chrome/Chromium automation for screenshots (Playwright); PHP GD/Imagick for image processing

## Getting started

```bash
composer install --no-dev -o
cp .env.example .env        # then configure DB + APP_KEY
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

See [CRM/README-INSTALL.md](CRM/README-INSTALL.md) for the complete
single-tenant install, reseller and update guide, and the release packages:

- `crm-v1.0.0.zip` — full release
- `update-v1.0.0.zip` — update package
- `update-replace-v1.0.0.zip` — replace-style update package

## Demo

A local demo workspace runs at `http://127.0.0.1:8000` with sample data and test
accounts (e.g. staff admin + client portal users).

## License

Self-hosted single-tenant licensing with optional signed updates and white-label
delivery per customer.
