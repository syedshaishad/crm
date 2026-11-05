# Landing page screenshots — Shaishad & Co. CRM

41 screenshots of the demo workspace, captured for marketing and landing-page use.

## What is here

| Path | Contents |
| --- | --- |
| `*.png` | Full-resolution captures, **3200 × 2000** (1600 × 1000 CSS at 2×); the client portal was captured at 1440 × 900 CSS and is **2880 × 1800** |
| `web/*.webp` | Web-optimised copies, all **1600 × 1000**, quality 88 |
| `index.html` | Browsable gallery grouped by product area, with descriptions |
| `contact-sheet.png` | Single-image overview of all 41 screens |
| `manifest.json` | File list with source dimensions and byte sizes |

Open `index.html` in a browser to browse the set. Click any card to open the full PNG.

## Coverage

- **Overview & sales** — dashboard, money dashboard, clients, client profile, client ledger, leads, proposals, proposal detail, projects, project overview.
- **Work & billing** — tasks, invoices, invoice detail, public invoice, public receipt, payments, expenses, products, purchase orders, cheques, credit notes, delivery challans, delivery routes, AMC & warranty.
- **Reports** — overview, profit & loss, receivables ageing, GST returns, client profitability.
- **People & operations** — HR hub, payroll run, attendance, documents, knowledge base, automations, quality loop, global search.
- **Settings & access** — settings hub, branding, client portal, sign in.

## Capture settings

- App: local demo at `http://127.0.0.1:8000`
- Theme: light (forced through `localStorage.theme`)
- Viewport: 1600 × 1000, device scale factor 2
- Format: PNG, 3200 × 2000
- Browser: Google Chrome, headless, via Playwright
- Date: 5 November 2026

## Using them on a landing page

- Use the `web/*.webp` files in `<img>` or `<picture>` tags; they are already 1600 px wide and small (≈60–100 KB each).
- The PNGs are for retina/hero use or for generating additional crops.
- Screens use the demo brand **Shaishad & Co.** and demo data (`.test.local` users, sample clients and invoices). Replace or re-capture with your own branding before publishing if needed.

## Notes

- The demo dataset is dated May–October 2026, so a few “this month” counters are zero on pages that default to November 2026. The reports captures use an explicit May–October range so the charts and totals are populated.
- No application source files were changed for this set. Temporary auth changes made for capture were reverted; the demo database is back to its original state.
