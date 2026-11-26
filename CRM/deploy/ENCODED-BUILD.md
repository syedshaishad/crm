# Encoded (protected) build

This produces a **second** signed release in which the licence/update gate is
shipped as encoded bytecode instead of readable PHP. It does not replace the
readable development build.

> Encoding raises the cost of casual nulling. It is not unbreakable: a buyer
> with root can still patch a caller, swap PHP binaries, or run a decoded dump.
> Keep updates signed and keep valuable behaviour server-side.

## What gets encoded

`deploy/encoded-files.php` is the canonical list. It covers the whole decision
boundary — encoding only the crypto class would be pointless because a nuller
would patch the caller instead:

- `bootstrap/app.php` — middleware alias registration
- `routes/web.php` — the `['installed', 'license']` route group
- `app/Http/Middleware/CheckLicense.php` — the gate
- `app/helpers.php` — `module_visible()` → `moduleAllowed()`
- `modules/Licensing/*.php` — verdict, certificate checks, pinned keys, API client
- `modules/Updater/PackageSigner.php`, `UpdateManager.php` — package verification
- `config/trust_root.php` — public-key pins

When you add a new licence check, add its file to that list in the same commit.

## Workflow A — run the encoder yourself (recommended)

Encode the listed files into a directory that **preserves their relative
paths**, then point the builder at it:

```powershell
php deploy/build-package.php --variant=encoded --encoded-dir=build/encoded
```

Example layout of `build/encoded/`:

```
build/encoded/bootstrap/app.php
build/encoded/routes/web.php
build/encoded/app/Http/Middleware/CheckLicense.php
build/encoded/modules/Licensing/LicenseManager.php
...
```

The builder refuses a file that is missing, empty, or byte-identical to the
readable source, so a forgotten file cannot silently ship unencoded.

## Workflow B — let the builder invoke the encoder

```powershell
php deploy/build-package.php --variant=encoded `
  --encode-cmd="ioncube_encoder --into {out} {file}"
```

`{file}` is the readable staged file, `{out}` is the destination the encoder
must write, `{root}` is the project root. Both are shell-quoted for you.
Encoder CLI flags differ between products and versions — check your encoder's
documentation and adjust the template. SourceGuardian uses a different CLI
name; the same placeholders apply.

## Output

```
releases/1.0.0/                 readable build (crm-v…, update-replace-v…, update-v…)
releases/1.0.0/encoded/         protected build, same file names, different bytes
```

Both update zips are Ed25519-signed with the same private key and carry an
exact per-file manifest. The encoded variant's manifest contains the **encoded**
bytes, so a buyer on the protected build can only install protected updates.

## Operational rules

1. **Buyers need the loader.** ionCube Loader / SourceGuardian Loader must be
   installed and enabled on the customer's PHP before an encoded release is
   unpacked. Without it the app cannot boot. Check `php -m` on the target.
2. **Never mix variants.** An encoded install must only receive
   `encoded/update-v*.zip`. A readable update would replace the encoded gate
   files with source. Keep two channels in your release feed and label them.
3. **Re-encode on PHP major upgrades.** Encoded bytecode is tied to a PHP
   version range; regenerate the protected build for each supported line.
4. **Keep the private key out of both variants.** `deploy/keys/*.key` is
   gitignored and excluded from every zip, encoded or not.
5. **Keep the readable build for development and support.** Debugging an
   encoded stack trace is miserable; reproduce issues on the plaintext build.

## Verification

The build self-checks that every listed gate file is present in both zips and
differs from the readable source. After building, also run:

```powershell
php -r '$z=new ZipArchive; $z->open("releases/1.0.0/encoded/crm-v1.0.0.zip"); var_dump($z->getFromName("app/Http/Middleware/CheckLicense.php") !== file_get_contents("app/Http/Middleware/CheckLicense.php"));'
```

And verify the update package on a clean install before publishing it.
