# Changelog

## v1.0.8 — 2026-10-13

On top of v1.0.7. 0 migrations, `?v=89` CSS / `?v=70` JS.

- Licensify server licensing (license.shaishad.com): HMAC-signed activate /
  validate / deactivate / status, Ed25519 certificate cache with
  server-controlled offline grace, fail-closed app + token API + client
  portal, mandatory installer activation step, release-slot + check-now flows
  in Settings, `license:check/activate/deactivate` CLI. Old offline
  file/key/Envato drivers and `license:make` removed.
- Dashboards split in three: Analytics (slimmed), Money (cashflow, target &
  forecast, money position, invoices), Work (focus, calendar, tasks, dues).
- UI fixes: Inter/Jakarta numerals (single-line KPIs), zero-week sparkline
  baseline, footer wrapping, mobile table bleed, icon-rail collapse with
  hover-peek, print empty-states hidden, target row spread fix.
- Packaging: `*.bat` / `*.exe` / `*.sh` excluded (server package validator),
  license identity files documented as never-ship.
- Repack: `.env.example` also excluded (validator flags it as sensitive) —
  fresh uploads now seed a baseline `.env` in code; default product code is
  the real `SSCCRM1000V1` so installs activate without extra configuration.
- Repack 2: all `.gitignore` files excluded (validator flags dotfiles) —
  required empty storage dirs ship as explicit directory entries instead.

## v1.0.7 — 2026-10-10

On top of v1.0.6. 0 migrations, `?v=89` CSS / `?v=70` JS.

- Licensify server licensing (license.shaishad.com): HMAC-signed activate /
  validate / deactivate, Ed25519 certificate cache with server-controlled
  offline grace, fail-closed everywhere (app + token API + client portal),
  mandatory activation step in the installer, release-slot flow in Settings.
  Old offline file/key/Envato drivers and `license:make` removed.
- Dashboards split in three: Analytics (slimmed), Money (cashflow, target &
  forecast, money position, invoices), Work (focus, calendar, tasks, dues).
- UI fixes: KPI numerals single-line (Inter/Jakarta rollback), zero-week
  sparkline baseline, footer wrapping, mobile table bleed, icon-rail
  hover-peek, print empty-states hidden.
- Packaging: `*.bat` / `*.exe` / `*.sh` excluded from zips (license-server
  package validator refuses executables); identity files documented.

## v1.0.6 — 2026-10-06

On top of v1.0.5. 0 migrations, `?v=85` CSS / `?v=70` JS.

- Ledger UI: warm paper surfaces with espresso sidebar slab, self-hosted
  Manrope throughout, editorial serif for headings and KPI numerals,
  uppercase stamp badges, double-rule ledger tables, brand spine on hero.
- Navigation: Views group (Leads table/kanban, Tasks calendar), sidebar
  collapses to an icon rail with brand mark (persisted), ⌘K spotlight,
  notifications bell, theme toggle.
- Dashboard analytics: 7-day collections sparkline, won-vs-lost area chart
  (6 months, server-computed), ⋯ menus on every card, open-deals table with
  gap badges and Showing-counts footer.
- Tables everywhere: shared footer (Showing X to Y of Z + numbered pages),
  rows-per-page picker (10–50, remembered per session), N-of-M selected
  footer on bulk tables, icon Table/Kanban and Table/Calendar switchers.
- Print: empty states hidden on paper; invoice/quote output verified clean.
- Fixes: dashboard 500 from a glued Blade directive, per-page override,
  dark-mode sidebar separator, print empty-table states.

## v1.0.5 — 2026-10-05

On top of v1.0.4. 3 migrations (2026_10_02_000009–000011, all add-only), `?v=80` CSS / `?v=67` JS.

- Team: utilization board with tick-and-move rebalancing, skills matrix
  with certifications + training log + digest alerts, GPS field visits that
  log time to tasks, anonymous suggestions + eNPS polls.
- Delivery: dependency-tracked scheduling with Gantt bars, baselines and
  critical path; recurring tasks; task + checklist template libraries;
  no-login client sign-off links; portal work tracking with CSAT;
  incident log with root-cause flow and kaizen board.
- Process: project finance (costs by category, billed ledger, forecast
  profit), automation rules (overdue/stale/expiring → notify, bump,
  reassign with daily throttle), meetings with minutes, searchable
  knowledge base, today's meetings in the digest.
- Navigation: single mega menu (Dashboard + Menu + HR section), proper
  accounting names throughout, responsive icon tier, no-wrap top bar.
- Fixes: user/client mass-assignment gaps (HR fields, currency),
  attendance re-mark crash, proposal/meeting Blade 500s, dark file inputs.

## v1.0.4 — 2026-10-02

On top of v1.0.3. 8 migrations (2026_10_02_000001–000008, all add-only), `?v=79` CSS / `?v=66` JS.

- Sales: win-loss tracking (structured lost reasons + competitors on decline,
  staff mark-declined, analytics tables), lead nurture scores with stale
  flags + one-click +7d bumps + digest section, client portal account
  statement with running balance, scheduled quote follow-up sequences
  (configurable days) plus SMS chase cards.
- Money: split receipts across invoices, manager write-offs, escalating
  dunning ladder, per-line discounts with GST on net, loyalty earn/redeem,
  subscriptions (trials, proration, billing-day snap, pause/cancel),
  foreign-currency invoicing with INR books + snapshot rates.
- Work: project costing (estimates, rates, labor, true margin, bill time as
  invoice), leave + shifts + holidays + overtime + reimbursements with
  payroll integration, half-yearly appraisals, task checklists with
  photo-gated client handover. New HR menu group declutters navigation.
- Insights: dashboard target + 3-month forecast, scheduled sales/
  receivables/P&L reports by email or WhatsApp, token-gated live CSV feeds
  for Sheets/Zoho, signed webhooks (payment, invoice, proposal, lead).
- HR suite: salary structures, monthly payroll runs (attendance-aware, TDS
  192, advance/EMI recovery, books posting, printable payslips), staff
  loans, bonuses, employee profiles + documents + asset custody, exits
  blocked on uncleared dues.
- Fixes: attendance re-mark crash (safe mark), missing task-time relation,
  proposal print 500s from glued Blade directives, dark-mode file inputs,
  responsive icon-only nav tier, every balance display honors write-offs.

## v1.0.3 — 2026-09-28

On top of v1.0.2. 7 migrations (000028–000034, all add-only), `?v=75` CSS / `?v=65` JS.

- Reports: client profitability (cash received vs project costs), P&L
  (revenue vs approved costs by category), receivables ageing buckets,
  collections split by method on Breakdowns.
- Money flows: advance payments adjust against invoices (credit-limit aware),
  recurring invoices auto-send as emailed sent invoices, GSTIN offline
  validation (change-aware, legacy-safe).
- Products: photos with picker thumbnails, invoice send/cancel auto-posts
  and reverses HSN-matched stock, one-click physical audits.
- People: task time logs, attendance calendar, salary advances with owed
  balances, client birthdays (form + 7-day digest flags), custom fields.
- Operations: delivery routes from issued challans (reorder, progress),
  day-close checklist with cash variance, fixed-asset register with
  straight-line book values, invoice cloning, scheduled digest now opens
  with yesterday's collections and today's dues.

## v1.0.2 — 2026-09-27

On top of v1.0.1. 18 migrations (000010–000027, all add-only), `?v=74` CSS / `?v=64` JS, new `bacon/bacon-qr-code` dependency.

- Proposals powerhouse: pitch mode (slides, QR sign CTA, cover art, PDF),
  scope of work + expected results, per-line billing cycles with monthly/
  yearly pricing, auto recurring invoices on convert, scope → project tasks,
  client reply thread, offline acceptance, decline reasons, auto-expire +
  expiry nudges, compare, clone, templates, content library, analytics
  (win rate, funnel, ageing), discount guard, classic/compact themes.
- Money in: PDC cheque tracker (deposit → clear books payment, bounce flow,
  digest alerts), UPI QR + copyable SMS text on invoices, late fees (flat/%,
  once-only, zero-GST line), per-client credit limits with over-limit
  warnings, staff leaderboard (collections, targets, commission) + Tally XML
  export (masters + balanced vouchers).
- Trackers: AMC & warranty, compliance/expiry documents, expense approvals
  (pending stays out of the books), petty cash book (never negative).
- Billing extras: e-way bill numbers on invoices, client price lists,
  invoice themes (classic/compact shared partial).
- Resale-ready licensing: plan presets (starter/pro/complete) in
  `license:make`, buyer license-key self-activation UI, Envato purchase-code
  driver, 17 toggleable modules, signed update packages (`update:sign` +
  `UPDATE_RELEASE_KEY`), RESELLER-GUIDE.md.
- Reliability: scheduler heartbeat (backups/digests run on traffic, no cron
  needed), backup-by-email (20 MB cap), cheque alert window setting.
- Security hardening: settings locked to admins (+ nav gear hidden), upload
  executable blocklist, image-only brand/invoice/proposal art, zip-slip
  guard, security headers, session cookie config, CSV formula guard,
  password-reset throttle, Trash gated to managers+.
- Polish: theme-aware dual logos (brand + invoice, print-safe), crumb
  `@endsection` leak fixed on 8 pages, proposal action row compacted.

## v1.0.1 — 2026-09-24

On top of v1.0.0. 9 migrations (all add-only), `?v=70` CSS / `?v=61` JS.

- Emailable statements: full ledger by email with pay links, editable
  template. Mail outbox log fixed (was silently dead on Laravel 12).
- Purchase orders: draft → sent → received with convert-to-expense,
  Trash coverage. Delivery challans with from-invoice copy + print.
- Project profitability: billed/profit/margin on projects, index + report
  columns, over-budget alerts.
- WhatsApp Cloud API reminders (manual + nightly auto, audit log).
- FY invoice numbering mode (per-FY series, April reset) with centralized
  numbering. Simple inventory (stock ledger, low-stock alerts).
- Dark mode (OS-follow + toggle), Spotlight command palette, custom icons
  per menu, self-hosted Inter + Jakarta fonts, neutral pastel re-theme.
- Invoice document polish: supplier block dedup, compact totals, A4 print
  rules, borderless-print lock, action overflow menu, adaptive logos.

## v1.0.0 — 2026-09-21

First resale release. Standalone self-hosted white-label CRM (Laravel 12,
PHP 8.2+, MySQL/MariaDB or SQLite). `?v=62` CSS / `?v=58` JS.

**Sell**
- Leads with list + kanban pipeline board, stages, sources, follow-ups,
  convert-to-client, bulk actions, import/export.
- Client management with Trash, portal logins, per-client ledger (running
  balance, CSV + print).
- Proposals with signing links, e-signature capture, decline flow, convert
  to invoice, open tracking + expiry follow-ups with audit log.
- Products catalog with invoice/proposal picker, HSN/SAC, GST slabs.
- Simple inventory: opening stock, in/out/adjustment ledger, low-stock
  alerts on products + dashboard.

**Bill**
- GST invoices (intra-state CGST+SGST / inter-state IGST, RCM, discounts,
  HSN-wise lines), recurring profiles, credit notes, Trash-safe numbering.
- Payments (cash/bank/UPI/card/Razorpay/online links), receipts, Razorpay
  checkout + webhooks, public no-login invoice/receipt views.
- TDS full loop: sections + toggle in Tax settings; claim on payments,
  expenses, invoice create/edit, and a Claim action on the invoice page.
  Claimed TDS settles invoices; 26AS register with FY picker + CSV.
  Client PAN field.
- Expenses with vendors, receipts, projects, recurring schedules.

**Collect**
- Overdue engine (nightly flagging) + reminder control center: manual nudge
  with throttle, `reminder_logs` audit, admin log view, nightly auto-nudges
  sharing one sender. Branded email templates with previews + outbox log.

**Report**
- Overview (stats, trend, donut, category bars), Breakdowns, Exports hub
  (all CSVs + ledger picker), GSTR-1 (B2B/B2C/HSN/CDNR + CSVs), TDS
  register. Client portal (dashboard, invoices, proposals, pay online).

**Platform**
- White-label branding (name, tagline, logo, favicon, colours, login copy,
  company + tax profile, mail identity) with empty-safe fallbacks.
- Granular permissions: per-user per-module Full/View/No-access enforced
  in nav, controllers, search and the REST API. Offline signed licensing
  with module toggles and user limits. Self-update packages with backup,
  preflight, downgrade block and migration runner. Guided installer with
  requirements check. Import/export across modules.
