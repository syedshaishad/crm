# Release signing key

`update-signing.key` is the **private** Ed25519 seed used by
`deploy/build-package.php` to sign `update-v<version>.zip`. It is gitignored:
never commit it, never ship it in a release zip, never paste it into a ticket.

- The matching **public** key is pinned in `config/trust_root.php`
  (`trust_root.updates.keys`, currently `rel_2026_1`). Buyer installs verify
  manifests with that public key only.
- Build: `php deploy/build-package.php` (it finds this file by default).
- Re-sign an existing zip:
  `php artisan update:sign --zip=update-v1.0.0.zip --private-key=deploy/keys/update-signing.key`
- Rotate: generate a new 32-byte seed, add its public key to `trust_root.php`
  under a new kid, ship a release that contains the new pin, then remove the
  old pin in the following release (old packages keep verifying until then).

```php
// generate a new seed + public key
$seed = sodium_crypto_sign_keypair();
echo bin2hex(sodium_crypto_sign_secretkey($seed)), "\n"; // 64-byte secret
echo base64_encode(sodium_crypto_sign_publickey($seed)), "\n";
```

Losing this key means no future update can be signed for the pinned kid.
Back it up securely (password manager / offline copy).
