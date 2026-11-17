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
  automatic backup + one-click rollback. Recommend buyers stay within one
  minor version: **annual renewal includes updates only on supported versions**.
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

**Signing.** An install that has `UPDATE_RELEASE_KEY` set refuses any package
whose `signature.txt` does not verify, so buyers cannot be tricked into
installing a tampered zip:

```bash
# once, per release line: generate a key and keep it in your password manager
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"

# every build (or pass --sign-key=... to build-package.php)
php artisan update:sign --zip=update-vX.Y.Z.zip --key=<key>
```

Then put the same key in each install's `.env`:

```
UPDATE_RELEASE_KEY=<key>
```

- Installs **without** the key accept unsigned packages — that is the
  self-hosting default, so only set it where you control the deployment.
- Rotate by re-signing and updating every install in the same window;
  a mismatched key rejects the update with *"not an official release"*.
- Never commit the key. Sign `update-*.zip` only: the updater checks
  signatures on the update path, not on a fresh install.

**Before any update** the app now writes `storage/app/backups/crm-backup-<stamp>.zip`
(database + uploaded files) and aborts if that backup fails. Hosts that cannot
afford the disk or time can opt out with `UPDATE_BACKUP_BEFORE_UPDATE=false`,
but a failed update then has no restore point. Backups are pruned to the newest
three (Settings → Updates).
