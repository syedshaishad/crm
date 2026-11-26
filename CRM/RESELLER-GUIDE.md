# Reseller Guide — CRM for Agencies

Single-tenant, self-hosted, white-label CRM. One install per customer, on their
hosting. Licensed through your Licensify server (license.shaishad.com) — the
app stays locked until a valid key is activated, and keeps working offline on
a server-signed grace certificate between check-ins. There is no open mode.

## 1. Hosting requirements (give this to the buyer)

- PHP 8.2+, MySQL/MariaDB, Composer on server (or upload vendor/)
- Extensions: openssl, pdo (+mysql), mbstring, tokenizer, xml, ctype, json,
  bcmath, fileinfo, **sodium** (license certificates cannot verify without it)
- One domain/subdomain, docroot pointed at `/public`
- `storage/` and `bootstrap/cache/` writable
- Outbound HTTPS to `license.shaishad.com` (activation + hourly check-ins)

## 2. Install (buyer or you, ~15 min)

1. Unzip `crm-vX.Y.Z.zip` so the app root is the hosting root
2. Point the domain docroot at `/public`
3. Put your product API credentials in `.env` (`LICENSE_API_KEY`,
   `LICENSE_API_SECRET` — create a product-scoped key on the license server;
   one shared product key for all buyers is fine, or one key per buyer)
4. Visit the site → installer collects company/brand, admin user, DB, then
   **requires license activation** — paste the buyer's key (or API creds too
   if this copy wasn't pre-provisioned). The installer cannot finish and the
   app stays locked until the server accepts the key.
5. Done — brand, colours, invoice prefix all carry the buyer's identity

## 3. License workflow — Licensify server (only workflow)

1. In the license server dashboard: create the product (code `CRM`), the
   customer, and issue their license (set activations/domains, expiry, and a
   development-class domain policy if the buyer needs staging).
2. Give the buyer: their license key (`XXXX-XXXX-…`), plus API credentials in
   their `.env` if not pre-provisioned.
3. Buyer pastes the key during installation (or later in
   Settings → License → Verify & activate). The server registers this
   installation (domain + install id + version) and the app unlocks.
4. Renewal/suspension happens on the server — expiry, suspension and
   revocation take effect at the next check-in (hourly) or immediately on
   Settings → License → Check now. Moving servers: buyer uses
   Release activation (or `php artisan license:deactivate`) to free the slot.

Check any install from the terminal: `php artisan license:check`
(live re-check: `license:check --refresh`). Activate headless:
`php artisan license:activate PASTE-KEY-HERE`.

## 3b. Offline behaviour (bounded, server-controlled)

Each successful check returns a signed certificate covering a grace window
you set on the product. While it verifies, the install runs with no network.
When it lapses and the server is unreachable, the app blocks — it never fails
open, and deleting local license files only forces an immediate re-check.
Plan grace windows against your buyers' hosting reliability.

## 4. Plans

Plans, trials and limits live on the license server (license type, expiry,
activations, domains) — not in this file. Keep one license per buyer per term.

## 5. What enforcement actually does (verified)

- No key, refused/expired/suspended license, unreachable server past grace,
  or missing ext-sodium → every workspace page (app + client portal + API
  with token) 402s with a branded notice quoting the server reference.
- Guests are sent to login; login, password reset and the installer stay
  reachable. Dashboard + Settings stay reachable for admins so a dead install
  can be rescued (enter a key, release a slot, trim nothing — no local
  workarounds exist by design).
- The installer cannot complete without a successful server activation.
- Honest limit: root on the server can patch code. Sell value + support, and
  watch activations/domains on the server — the `installation id` on
  Settings → License identifies each running copy.

## 6. Updates & renewal policy (put this in your price quote)

- Updates ship as `update-vX.Y.Z.zip`, applied from Settings → Updates with
  automatic backup + one-click rollback. Buyers who have the API credentials can
  also install straight from the license server (section 8).
- A license covers the major series current when it was sold: a 1.x license gets
  every 1.x release but not 2.0.0. Recommend buyers stay within one minor
  version: **annual renewal includes updates only on supported versions**.
- Major restores (server loss, migration help) and GST setup are billable
  onboarding — price them separately from the license.

## 7. Building and signing update packages

Build from the repo root (the version defaults to `config/version.php`):

```bash
php deploy/build-package.php --version=1.0.1 --out=../releases
```

It writes three files and refuses to finish if any check fails:

| File | Use |
| --- | --- |
| `crm-vX.Y.Z.zip` | Fresh install: upload, point the docroot at `/public`, run the installer. |
| `update-replace-vX.Y.Z.zip` | Byte-identical copy of the release zip, for replacing a damaged install. |
| `update-vX.Y.Z.zip` | In-app update: Settings → Updates → upload. |

The build never includes `.env`, a database, uploads, logs, caches,
`public/storage`, identity/licence files, or any `.bat/.exe/.sh/.cmd/.ps1/.dll/.so`
(the licence server's package validator rejects executables).

**Signing is mandatory.** Every update zip carries `manifest.json` (version,
notes and a SHA-256 for every payload file) and `signature.json` (an Ed25519
signature over the manifest bytes). Installs verify both against the public
key pinned in `config/trust_root.php` **before** taking a backup or writing a
single file. Unsigned or tampered packages are refused, and nothing inside a
package is ever `include`d or `eval`d.

```bash
# The private key is gitignored at deploy/keys/update-signing.key.
# Normal build: signs automatically and copies artifacts to releases/<version>/.
php deploy/build-package.php

# Re-sign an existing update zip:
php artisan update:sign --zip=update-vX.Y.Z.zip \
  --private-key=deploy/keys/update-signing.key

# Per-order watermark (shown in Settings → License on the installed copy):
php deploy/build-package.php --licensee="Acme / ORD-123"
```

- Never put the private key in `.env`, a zip, Git or a support ticket. If it
  leaks, generate a new keypair, pin the new public key under a new kid in
  `config/trust_root.php`, ship that release, then retire the old pin.
- `UPDATE_RELEASE_KEY` no longer exists: an empty value used to mean "accept
  anything". Legacy HMAC packages are accepted only if that value is set
  explicitly, and only until the next release line.
- Per-order API credentials: issue `LICENSE_API_KEY` / `LICENSE_API_SECRET`
  unique to each order and scope them server-side to that order's key and
  domain. A shared reseller secret in every zip gives every buyer the whole
  product's API access.

**Optional protected build.** If you distribute encoded bytecode (ionCube /
SourceGuardian), use the second build channel described in
`deploy/ENCODED-BUILD.md`:

```bash
php deploy/build-package.php --variant=encoded --encoded-dir=build/encoded
```

It writes a separate signed release to `releases/<version>/encoded/`, with the
11 gate-boundary files from `deploy/encoded-files.php` replaced by encoder
output. Buyers need the matching loader on their PHP, and an encoded install
must only receive encoded updates — do not mix the two channels. This raises
the cost of casual nulling; it is not a substitute for server-side entitlement
checks.

**Before any update** the app now writes `storage/app/backups/crm-backup-<stamp>.zip`
(database + uploaded files) and aborts if that backup fails. Hosts that cannot
afford the disk or time can opt out with `UPDATE_BACKUP_BEFORE_UPDATE=false`,
but a failed update then has no restore point. Backups are pruned to the newest
three (Settings → Updates).

## 8. Automatic updates through the license server

Settings → Updates has an **Automatic updates** card. With the customer key and
the product API credentials in `.env`, the install asks the Licensify server
what it may run instead of waiting for a zip to be emailed:

```
LICENSE_KEY=XXXX-XXXX-XXXX-XXXX-XXXX
LICENSE_API_KEY=lk_...
LICENSE_API_SECRET=...
```

**Check for updates** calls `POST /updates/check` with the product code, license
key, current version, domain and installation id. The server answers with the
release and the requirements that gate it:

- `update_available`, `latest_version`, `release_type`, `release_notes`,
  `published_at`, `changelog_url` — what the page shows.
- `download_available: false` — the server is not offering a package; the page
  explains why and hides the install button.
- `requirements.minimum_php_version` / `minimum_laravel_version` — checked again
  locally, so an installation that cannot run the release is never handed it.
- `requirements.new_license_required: true` — the release sits outside the major
  series this license covers (a 1.x license does not get 2.0.0). Existing 1.x
  releases stay visible; the page says a new license is required.
- `requirements.requires_backup_acknowledgement` — already satisfied, because
  every update takes a database + uploads backup first.

**Download & install** calls `POST /downloads/request` for a short-lived,
single-use URL, streams the package to a `.part` file, verifies the SHA-256 the
server returned, refuses a non-HTTPS URL, and only then renames it into
`storage/app/updates/` and runs the normal apply path: backup → extract →
migrations → version bump.

Support notes:

- The verdict is cached in `storage/app/updates/.remote-check.json`, so the page
  does not call the server on every load. "Check for updates" refreshes it.
- `/license/validate` also returns `update_available` and `latest_version`, so
  the install knows about releases even when nobody opens Settings.
- Manual upload still works, and is the fallback when the license server is
  unreachable or the buyer has no API credentials.
- Rebuilding a zip on your machine does **not** change what the server serves:
  upload the package on Licensify (product → versions) before you tell buyers to
  update. The install downloads whatever the server holds for that version and
  checks it against the checksum the server reports at grant time.
- Rate limits apply per installation (60/min by default); a click-happy admin
  gets a plain "rate limit" message, not a failed update.
