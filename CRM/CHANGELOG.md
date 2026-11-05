# Changelog

> Version numbering was reset to **1.0.0** on 2026-10-19. The entries below are
> kept for history; the current codebase is the 1.0.0 baseline. Demo seeders
> and demo records are not part of the release.

## Unreleased

2 migrations, `?v=130` CSS / `?v=79` JS.

- Settings → Branding rebuilt on the shared design system: header with save and
  back actions, primary/accent/override/logo metrics, identity and logo upload
  tiles, grouped colour-token editors, a live component preview with reset,
  company/tax and email-sender cards, and a save bar. Pickr colour picking,
  live CSS-variable theming, the WCAG contrast warning and reset-to-defaults
  all keep working.
- All remaining Settings pages rebuilt on the shared design system (`General`,
  `Tax`, `Billing`, `Email templates`, `Integrations`, `My account`, `Users`,
  the user form, `Modules`, `Content library`, `Updates`, `License`, `API` and
  the webhook log): consistent headers with back/save actions, summary metrics,
  grouped cards, compact filters, switch/check rows, amount-right tables,
  empty states and responsive mobile layouts. The settings hub is now a grouped
  directory with section counts. Every existing field, route and form name is
  preserved, including the user role-permission script and push-notification
  controls.
- Accounting suite rebuilt on the shared directory design: invoices, payments,
  expenses, credit notes, cheques, petty cash, fixed assets, day-end close,
  purchase orders and payment reminders now
  use the same header summary, metric cards, clickable chips, compact filter bar,
  balanced tables, empty states and mobile card rows.
- Invoices: outstanding/overdue summary, All / Invoiced / Outstanding / Collected
  metrics, status chips (including Unpaid and Partial), balance with due-date or
  overdue cues, and the manager-only assigned column.
- Payments: receipt, received, this-month and average metrics, payment-method
  chips, invoice links and method/reference details.
- Expenses: pending/approved/rejected chips, inline approve for managers,
  approved-spend and monthly totals.
- Credit notes: issued, applied and open-credit metrics, open/applied chips and
  remaining credit per note.
- Cheques: held value, ready-to-deposit, cleared and bounced metrics with state
  chips and due-date cues.
- Petty cash: month navigation, in-hand/added/spent/net metrics, labelled quick
  entry and a running-balance book with an opening row.
- Fixed assets: count, original cost, book value and depreciation metrics, a
  register grid and per-row custody assignment.
- Day-end close: expected petty, cash, open invoices and cheque metrics, checklist
  cards, variance display and a past-closes table with checklist progress.
- Purchase orders: status counts, draft and in-transit values, received-value
  metric, status chips, vendor/expected-date cues and a right-hand total.
- Payment reminders: reminder, failure, follow-up and WhatsApp metrics across the
  three audit logs, with delivery status, inline error detail and grouped tables.
- Registers switch to card rows at 1200px, metrics go 2-up at 1100px, and money
  metrics compact below 480px; all pages styled for dark mode.
- Money columns now sit at the right end of every accounting register, right-aligned
  on desktop, with card rows keeping labels on the left and amounts pushed to the
  right edge, so totals line up page to page.

- Clients directory rebuilt: header summary with total/active/new/outstanding
  metrics, clickable status chips, compact search/filter toolbar and quick-add
  form, a balanced client table with project counts and clear status/outstanding
  indicators, plus a two-row mobile card layout and dark-mode styling.
- Delivery challans, delivery routes, products and AMC & warranty indexes rebuilt
  on the same directory design: header summaries, four metric cards, clickable
  status chips, compact search/filter toolbars, balanced desktop tables and
  card layouts down to mobile widths.
- Challans gained number/client search, draft/issued/cancelled counts and
  dispatched value; routes gained today/planned/on-route/completed counts and
  stop progress; products gained archived totals, average rate and low-stock
  cues while keeping quick-add and bulk actions; AMC gained an unscheduled
  state, expiry countdowns and renewal urgency.
- Leads page redesign: compact pipeline metrics, clickable stage chips, a cleaner
  table with balanced desktop column widths, compact row actions and a mobile
  card layout, plus a fit-to-width kanban board with neutral cards and readable
  stage controls.
- Staff profile pictures: upload/remove from Account and user admin forms,
  stored on the public disk, shown in the top bar, account menu and user list,
  with initials fallback.
- Web Push notifications: self-hosted VAPID keys, per-device subscriptions,
  service worker, test/enable/disable UI, notification avatar icon, sound and
  vibration hints, plus an in-app chime while the CRM is open.
- All database notifications now also dispatch through `WebPushChannel`;
  delivery is deferred and silently skips users without subscriptions.
- Packaging excludes the generated `storage/app/webpush_vapid.json` private
  key; a new key pair is generated per installation.
- Backups are capped at the latest 3 zips; older archives and their working
  directories are pruned automatically after every nightly or manual backup.
- Sidebar groups now render in their declared order; HR sits last, after Data
  Import & Export.
- Settings are loaded once per request and the app version reuses that cache,
  removing roughly 250 repeated database queries from every page; menu
  visibility checks are memoized and the scheduler heartbeat now spawns only
  after the response is sent. Average page render time roughly halved in the
  local benchmark.
- Removed an unused `Payslip` import left over after the payslip action moved
  to a fully-qualified reference.
- Meetings attendees now use a flat checkbox picker matching the form fields
  instead of the native multi-select; selected attendees are listed on upcoming
  and past meetings, choices survive validation errors, and stored IDs are
  normalized.
- Invoice and document printing now always uses the paper/light palette,
  including when the app is in dark mode, so printed invoices and PDFs match
  the light theme.
- Projects now show a plain-language status summary with counts and percentages
  instead of the segmented rainbow bar, and the task/budget meters in each row
  include percentages and clearer colours. Per-project status is now a coloured
  dot and readable label beside the client, instead of a bordered uppercase tag.
- Tasks use the same status summary and compact dot/label tags for status and
  priority; overdue dates show a small caption instead of a red pill, and the
  done strikethrough only applies to the task title.
- Knowledge base rebuilt as a readable article index: page header, compact
  search/filter toolbar, excerpted article rows with kind chips, author and
  updated date, pinned indicators, category counts and a pinned-articles rail.
  Article creation moved into a modal, article pages gained a proper reading
  layout and edit/pin/delete actions, and the Template kind is now editable.
- Quality loop rebuilt: incident rows with severity and status chips, project
  context and inline root-cause/fix editing, plus a kaizen rail for capturing
  ideas and moving them from proposed to doing to done (or dropping them).
  Incident creation now opens in a modal instead of an inline form.
- Meetings rebuilt as an agenda view: upcoming rows with date blocks, time
  range, project, attendee avatars and inline minutes, plus a compact past
  rail. Scheduling moved into a modal with the existing attendee picker, and
  legacy attendee names are resolved alongside stored user IDs.
- Automations and task templates rebuilt on the same directory design:
  header summaries, four metric cards, clickable status chips, compact
  search/filter toolbars and balanced desktop tables with card layouts down to
  mobile widths. Automations gained rule-name search, trigger filtering,
  live/paused tallies, per-rule last-fired ages and a recent-firings table
  while keeping quick-add, pause/resume, delete and run-now. Task templates
  gained a single search across starters and checklist packs, labelled
  quick-add forms, step counts and a two-card grid that stacks on narrow
  screens.
- Quick-add and filter forms now read their `action` attribute instead of the
  form property, so a field named `action` (such as an automation's action
  picker) no longer hijacks the submit URL. Deleting a row on the rebuilt
  directories refreshes the surrounding counters and empty state.

## v1.0.9 — 2026-10-19

On top of v1.0.8. 0 migrations, `?v=107` CSS / `?v=74` JS.

- Design system unified across every staff page against the Dashboard mockup:
  `--dc-*` tokens and dark theme, flat cards (no shadow/hover), full-bleed
  shell, one button/chip/pagination system, uppercase badges, restyled tables,
  forms, filters and empty states.
- Branding now drives the theme: primary colour feeds `--brand-accent`, stored
  surface/text/border/status colours and background/sidebar overrides wire into
  the `--dc-*` tokens (dark mode respects the brand); Pickr colour fields
  unified.
- Dashboards rebuilt on `dc.css`; topbar, account menu, search panel and mobile
  drawer rebuilt (300px rail drawer, mobile title); portal header made
  responsive (no overflow at 390 / 768 px).
- Auth pages share `_head` / `_theme` partials (title, favicon, CSS, theme
  bootstrap); stale `@section('crumb')` blocks removed from all views.
- Cleanup: ~200 dead CSS rules and 79 unused classes removed, dead JS handlers,
  unused imports and duplicate controller helpers removed (new `AssignsRecords`
  trait, `DocumentNumber::isVisibleTo`), unused fonts dropped; `.gitignore`
  hardened (DB, storage, license files).
- Packaging: `.freebuff`, `.gitignore`, generated bootstrap cache and every
  storage/license identity file excluded; required storage dirs ship as
  explicit directory entries.
- Upgrade-path safety: the process-suite migration guards each table, so an
  install left with `workflow_rules` / `automation_firings` by an interrupted
  run completes on retry instead of failing with MySQL error 1050.

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
