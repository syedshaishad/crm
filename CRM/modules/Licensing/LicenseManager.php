<?php

namespace Modules\Licensing;

/**
 * Licensify-backed license manager (license.shaishad.com).
 *
 * One source of truth: the server. This install reports facts (license key,
 * domain, installation id, version) and obeys the answer. Successful
 * validations return a signed Ed25519 certificate that covers a
 * server-decided offline grace window; while it verifies, the app runs
 * without network. When it lapses and the server is unreachable, the app
 * blocks. Deleting local state only forces an immediate re-validation.
 *
 * Public surface kept stable for existing call sites (middleware, helpers,
 * settings UI): driver(), status(), accessAllowed(), moduleAllowed(),
 * moduleForRoute(). The old offline file/key/Envato drivers are gone —
 * there is no open mode and no local minting.
 */
class LicenseManager
{
    private static ?array $memo = null;

    /** Drop the per-request verdict (tests, CLI loops, deactivation). */
    public static function forget(): void
    {
        self::$memo = null;
    }

    public function driver(): string
    {
        return 'licensify';
    }

    /** Stable per-install id (generated once, survives restarts/DB moves). */
    public function installationId(): string
    {
        $path = (string) config('licensing.installation_file');
        try {
            if (is_file($path)) {
                $id = trim((string) file_get_contents($path));
                if (preg_match('/^inst_[0-9a-f]{32}$/', $id)) {
                    return $id;
                }
            }
            $id = 'inst_'.bin2hex(random_bytes(16));
            @file_put_contents($path, $id);
            @chmod($path, 0600);

            return $id;
        } catch (\Throwable $e) {
            return 'inst_'.substr(hash('sha256', (string) config('app.key').'|install'), 0, 32);
        }
    }

    /** Customer license key: env wins, else the key saved at activation. */
    public function licenseKey(): string
    {
        $env = trim((string) config('licensing.license_key', ''));
        if ($env === '') {
            try {
                $f = storage_path('app/license.key');
                if (is_file($f)) {
                    $env = trim((string) file_get_contents($f));
                }
            } catch (\Throwable $e) {
            }
        }
        // A key is a single line. Never let a storage file smuggle headers or
        // extra lines into the signed API request.
        $env = preg_replace('/[\r\n]+/', ' ', $env) ?? '';
        $env = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $env) ?? '');

        return substr($env, 0, 200);
    }

    public function serverHost(): string
    {
        $url = (string) config('licensing.server_url');

        return (string) parse_url($url, PHP_URL_HOST);
    }

    public function domain(): string
    {
        $hosts = $this->domainsForBinding();

        return $hosts[0] ?? '';
    }

    /**
     * Hosts a certificate may legitimately bind to.
     *
     * On HTTP the current Host header wins. In CLI (cron, artisan) APP_URL is
     * the only host: reading the domain out of the cached certificate would
     * let a copied certificate name its own acceptable host and defeat the
     * binding check. Keep APP_URL set to the licensed domain on cron hosts.
     */
    public function domainsForBinding(): array
    {
        $hosts = [];
        try {
            if (! app()->runningInConsole() && request()) {
                $hosts[] = request()->getHost();
            }
        } catch (\Throwable $e) {
        }
        foreach ([(string) parse_url((string) config('app.url'), PHP_URL_HOST)] as $h) {
            if (is_string($h) && $h !== '') {
                $hosts[] = $h;
            }
        }
        $out = [];
        foreach ($hosts as $h) {
            $n = TrustRoot::normalizeDomain($h);
            if ($n !== '' && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /** Read one claim from the cached (signature-verified) certificate, if any. */
    public function cachedClaim(string $key): ?string
    {
        try {
            $state = $this->readState();
            if (empty($state['token'])) {
                return null;
            }
            [$ok, $claims] = LicenseCertificate::verify(
                (string) $state['token'], $this->loadKeyring(), TrustRoot::product(), null, null, 300,
                ['max_offline_seconds' => TrustRoot::maxOfflineSeconds(), 'issuers' => TrustRoot::issuers()]
            );

            return $ok && is_array($claims) && isset($claims[$key]) ? (string) $claims[$key] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function client(): LicensifyClient
    {
        return LicensifyClient::fromConfig();
    }

    protected function basePayload(): array
    {
        try {
            $version = function_exists('app_version') ? app_version() : config('version.version', '1.0.0');
        } catch (\Throwable $e) {
            $version = (string) config('version.version', '1.0.0');
        }

        return [
            'license_key' => $this->licenseKey(),
            'product_code' => TrustRoot::product(),
            'domain' => $this->domain(),
            'installation_id' => $this->installationId(),
            'version' => (string) $version,
        ];
    }

    protected function activatePayload(): array
    {
        return $this->basePayload() + [
            'php_version' => PHP_VERSION,
            'laravel_version' => function_exists('app') ? app()->version() : 'unknown',
            'server_info' => [
                'os' => PHP_OS,
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'cli',
                'database' => (string) config('database.default'),
            ],
        ];
    }

    /**
     * Activate a customer license key against the server and persist it.
     * Returns [true, message] or [false, reason]. The key file is kept even
     * on refusal so status() keeps reporting the server's real reason.
     */
    public function activate(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return [false, 'Paste the license key from your reseller first.'];
        }
        $client = $this->client();
        if (! $client->configured()) {
            return [false, 'This install is not connected to the license server (missing API credentials) — ask your reseller for a complete install.'];
        }
        try {
            @file_put_contents(storage_path('app/license.key'), $key);
            @chmod(storage_path('app/license.key'), 0600);
        } catch (\Throwable $e) {
            return [false, 'Could not save the key (storage not writable).'];
        }
        // Temporarily act as this key for the activation call itself.
        config(['licensing.license_key' => $key]);
        self::$memo = null;
        try {
            $res = $client->activate($this->activatePayload());
        } catch (LicensifyException $e) {
            return [false, $this->humanError($e)];
        }
        if (empty($res['success'])) {
            return [false, $this->refusal($res, 'Activation refused.')];
        }
        $saved = $this->storeCertificate($res['certificate'] ?? null);
        if (! $saved) {
            return [false, 'The server accepted the key but did not issue a usable certificate. Contact support.'];
        }

        return [true, (string) ($res['message'] ?? 'License activated.')];
    }

    /**
     * Release this installation's activation slot, then wipe local state so
     * nothing usable remains until a key is activated again.
     */
    public function deactivate(string $reason = ''): array
    {
        $client = $this->client();
        if (! $client->configured()) {
            return [false, 'This install is not connected to the license server.'];
        }
        if ($this->licenseKey() === '') {
            return [false, 'No license key on this install.'];
        }
        try {
            $payload = $this->basePayload();
            if ($reason !== '') {
                $payload['reason'] = $reason;
            }
            $res = $client->deactivate($payload);
        } catch (LicensifyException $e) {
            return [false, $this->humanError($e)];
        }
        if (empty($res['success']) && empty($res['deactivated'])) {
            return [false, $this->refusal($res, 'Deactivation refused.')];
        }
        $this->wipeLocal();

        return [true, 'Activation released. Paste a license key to use this install again.'];
    }

    /** Force a live re-validation right now (Settings button, cron, CLI). */
    public function refresh(): array
    {
        self::$memo = null;
        $s = $this->status(true);
        if (! $s['active']) {
            return [false, $s['reason'] ?? 'License check failed.'];
        }

        return [true, 'License verified with the server.'];
    }

    public function refreshKeyring(): array
    {
        try {
            $data = $this->client()->publicKey(TrustRoot::product());
        } catch (LicensifyException $e) {
            return [false, $this->humanError($e)];
        }
        $fetched = LicenseCertificate::normalizeKeyring(is_array($data) && isset($data['data']) ? $data['data'] : $data);
        // The server is only a transport for keys. Only bytes pinned in code
        // are accepted, so a replaced keyring file cannot mint certificates.
        $keys = $this->pinnedFrom($fetched);
        if (! $keys) {
            return [false, 'The server returned no signing keys pinned by this release — install the latest app version to trust them.'];
        }
        $this->saveKeyring($keys);

        return [true, 'Keyring refreshed ('.count($keys).' trusted key'.(count($keys) === 1 ? '' : 's').', '.count($fetched).' offered).'];
    }

    /**
     * The single verdict the whole app obeys. Fail-closed: any doubt means
     * inactive. Memoized per request; network at most on first need, on the
     * revalidation schedule, or when forced.
     */
    public function status(bool $force = false): array
    {
        if (self::$memo !== null && ! $force) {
            return self::$memo;
        }
        try {
            $s = $this->check($force);
        } catch (\Throwable $e) {
            $s = $this->out(false, 'License check crashed — blocking to stay safe.', 'STATUS_CRASH');
        }
        self::$memo = $s;

        return $s;
    }

    /** Everything a certificate is bound to at runtime (never read from state). */
    protected function verifyContext(): array
    {
        return [
            'domains' => $this->domainsForBinding(),
            'installation_id' => $this->installationId(),
            'max_offline_seconds' => TrustRoot::maxOfflineSeconds(),
            'issuers' => TrustRoot::issuers(),
        ];
    }

    protected function check(bool $force): array
    {
        if ($this->licenseKey() === '') {
            return $this->out(false, 'No license key on this install. Activate one in Settings → License (or during installation) before using the app.', 'NO_LICENSE_KEY');
        }
        $client = $this->client();
        if (! $client->configured()) {
            return $this->out(false, 'This install is not connected to the license server (missing API credentials). Ask your reseller for a complete install.', 'SERVER_NOT_CONFIGURED');
        }
        $state = $this->readState();
        $keys = $this->loadKeyring();
        $context = $this->verifyContext();
        // License binding uses the server-issued license id (responses carry
        // it; claims carry it as lid). The raw key never appears in claims.
        $bindId = $state['license_id'] ?? null;
        $cached = null;
        if (! empty($state['token'])) {
            [$ok, $claimsOrReason] = LicenseCertificate::verify(
                $state['token'], $keys, TrustRoot::product(), $bindId, null, 300, $context
            );
            if ($ok && is_array($claimsOrReason)) {
                $rev = LicenseCertificate::revOf($claimsOrReason);
                if ($rev < (int) ($state['rev'] ?? 0)) {
                    return $this->out(false, 'Cached certificate is older than the last known license state — re-validating is required.', 'CERTIFICATE_ROLLBACK');
                }
                $cached = $claimsOrReason;
            } elseif ($claimsOrReason === 'Unknown signing key — refresh the keyring.') {
                // Key rotation recovery path (docs): refresh once, retry verify.
                $this->refreshKeyring();
                $keys = $this->loadKeyring();
                [$ok2, $claims2] = LicenseCertificate::verify(
                    $state['token'], $keys, TrustRoot::product(), $bindId, null, 300, $context
                );
                if ($ok2 && is_array($claims2) && LicenseCertificate::revOf($claims2) >= (int) ($state['rev'] ?? 0)) {
                    $cached = $claims2;
                }
            }
        }
        // Bound the schedule: a buyer cannot set "revalidate_minutes=999999"
        // in .env and turn a 7-day offline grace into a permanent offline mode.
        $minutes = max(5, min(240, (int) config('licensing.revalidate_minutes', 60)));
        $due = $force || empty($state['checked_at']) || (time() - (int) $state['checked_at']) >= $minutes * 60;
        if ($cached !== null && ! $due) {
            return $this->fromClaims($cached, true, (int) ($state['checked_at'] ?? time()));
        }
        // Live validation (first run, schedule due, or forced).
        try {
            $res = $client->validate($this->basePayload());
        } catch (LicensifyException $e) {
            if ($cached !== null) {
                // Bounded offline: serve from the still-valid certificate.
                $s = $this->fromClaims($cached, true, (int) ($state['checked_at'] ?? time()));
                $s['stale'] = true;
                $until = LicenseCertificate::toTs($s['grace_until'] ?? time()) ?: time();
                $s['reason'] = 'License server unreachable — running on cached certificate until '.date('j M Y H:i', $until).'.';

                return $s;
            }

            return $this->out(false, 'License server unreachable and no cached certificate: '.$e->getMessage(), 'SERVER_UNREACHABLE', $e->requestId);
        }
        if (empty($res['success']) || empty($res['valid'])) {
            $this->writeState(['last_refusal' => $this->refusal($res, 'License not usable.'), 'last_refusal_at' => time(), 'checked_at' => time()] + $this->keepToken($state));
            $this->wipeToken();

            return $this->out(false, $this->refusal($res, 'License not usable.'), $res['error_code'] ?? $res['error']['code'] ?? 'LICENSE_INVALID', $res['meta']['request_id'] ?? null);
        }
        if (empty($res['certificate']) || ! is_string($res['certificate'])) {
            return $this->out(false, 'The server accepted the license but issued no certificate. Contact support.', 'NO_CERTIFICATE', $res['meta']['request_id'] ?? null);
        }
        if (! $this->storeCertificate($res['certificate'])) {
            return [false, 'The server certificate could not be verified on this install. Contact support.', 'CERTIFICATE_UNVERIFIABLE', $res['meta']['request_id'] ?? null];
        }
        $this->rememberLicenseId($res);

        return $this->fromResponse($res);
    }

    /** Build the active verdict from a live validate response. */
    protected function fromResponse(array $res): array
    {
        $claims = [];
        if (! empty($res['certificate']) && is_string($res['certificate'])) {
            $split = LicenseCertificate::split($res['certificate']);
            $claims = ($split && is_array($split[1])) ? $split[1] : [];
        }

        $graceUntil = $res['offline_until'] ?? null;
        $cap = time() + TrustRoot::maxOfflineSeconds();
        if ($graceUntil !== null && ($t = LicenseCertificate::toTs($graceUntil)) > $cap) {
            $graceUntil = date('c', $cap);
        }

        return $this->out(true, '', null, $res['meta']['request_id'] ?? null) + [
            'mode' => 'licensify',
            'plan' => $res['license_type'] ?? $claims['type'] ?? $claims['plan'] ?? 'licensed',
            'license_type' => $res['license_type'] ?? null,
            'status' => $res['status'] ?? 'active',
            'expires_at' => $res['expires_at'] ?? $claims['expires_at'] ?? null,
            'grace_until' => $graceUntil,
            'grace_expires_in' => $res['grace_expires_in'] ?? null,
            'update_available' => $res['update_available'] ?? false,
            'latest_version' => $res['latest_version'] ?? null,
            'installation_id' => $res['installation_id'] ?? $this->installationId(),
            'domain' => $res['domain'] ?? $this->domain(),
            'modules' => $res['modules'] ?? $claims['modules'] ?? ['*'],
            'user_limit' => $res['user_limit'] ?? $claims['user_limit'] ?? null,
            'bound_domain' => $claims['dom'] ?? null,
            'bound_installation' => $claims['inst'] ?? null,
        ];
    }

    /** Build the active verdict from verified cached claims (offline path). */
    protected function fromClaims(array $claims, bool $cached, int $checkedAt): array
    {
        $exp = LicenseCertificate::expOf($claims, TrustRoot::maxOfflineSeconds());

        return $this->out(true, '', null, null) + [
            'mode' => 'licensify',
            'plan' => $claims['type'] ?? $claims['plan'] ?? $claims['license_type'] ?? 'licensed',
            'license_type' => $claims['license_type'] ?? null,
            'status' => $claims['status'] ?? 'active',
            'expires_at' => $claims['expires_at'] ?? null,
            'grace_until' => $exp ? date('c', $exp) : null,
            'cached' => $cached,
            'checked_at' => $checkedAt,
            'installation_id' => $this->installationId(),
            'domain' => $this->domain(),
            'modules' => $claims['modules'] ?? ['*'],
            'user_limit' => $claims['user_limit'] ?? null,
            'bound_domain' => $claims['dom'] ?? null,
            'bound_installation' => $claims['inst'] ?? null,
        ];
    }

    protected function out(bool $active, string $reason = '', ?string $code = null, ?string $requestId = null): array
    {
        return [
            'active' => $active,
            'mode' => 'licensify',
            'reason' => $reason,
            'error_code' => $code,
            'request_id' => $requestId,
        ];
    }

    public function accessAllowed(): bool
    {
        try {
            return (bool) $this->status()['active'];
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function moduleAllowed(string $module): bool
    {
        try {
            $status = $this->status();
            if (! ($status['active'] ?? false)) {
                return false;
            }
            $allowed = $status['modules'] ?? ['*'];
            if (! is_array($allowed)) {
                $allowed = ['*'];
            }

            // A live response can carry a restricted module list. Older
            // certificates omit it and keep the historical all-modules behaviour.
            return in_array('*', $allowed, true) || in_array($module, $allowed, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Map a route name (or path) to its licensed module, null = always allowed. */
    public function moduleForRoute(?string $route): ?string
    {
        if (! $route) {
            return null;
        }
        $map = [
            'leads' => 'Leads', 'clients' => 'Clients', 'proposals' => 'Proposals',
            'products' => 'Products', 'projects' => 'Projects', 'tasks' => 'Tasks',
            'invoices' => 'Invoices', 'payments' => 'Payments', 'credits' => 'Invoices',
            'expenses' => 'Expenses', 'reports' => 'Reports', 'documents' => 'Documents',
            'expiry' => 'Compliance', 'cheques' => 'Cheques',
            'amc' => 'Amc', 'challans' => 'Challans', 'routes' => 'Challans', 'purchase-orders' => 'Purchases', 'petty' => 'Petty', 'dayclose' => 'Petty',
            'renewals' => 'Amc', 'client-requests' => 'Clients',
        ];
        foreach ($map as $prefix => $module) {
            if ($route === $prefix || str_starts_with($route, $prefix.'.') || str_starts_with($route, $prefix.'/')) {
                return $module;
            }
        }

        return null;
    }

    // ---------- local state: certificate, keyring, refusal cache ----------

    protected function statePath(): string
    {
        return (string) config('licensing.certificate_file');
    }

    protected function readState(): array
    {
        try {
            $raw = is_file($this->statePath()) ? file_get_contents($this->statePath()) : '';
            $d = json_decode((string) $raw, true);

            return is_array($d) ? $d : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function writeState(array $patch): void
    {
        try {
            $merged = array_merge($this->readState(), $patch);
            @file_put_contents($this->statePath(), json_encode($merged));
            @chmod($this->statePath(), 0600);
        } catch (\Throwable $e) {
        }
    }

    protected function keepToken(array $state): array
    {
        $keep = [];
        foreach (['token', 'rev'] as $k) {
            if (array_key_exists($k, $state)) {
                $keep[$k] = $state[$k];
            }
        }

        return $keep;
    }

    protected function wipeToken(): void
    {
        try {
            $s = $this->readState();
            unset($s['token'], $s['rev']);
            @file_put_contents($this->statePath(), json_encode($s));
        } catch (\Throwable $e) {
        }
    }

    /** Verify + persist a fresh certificate. False = unusable here. */
    protected function storeCertificate(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }
        $keys = $this->loadKeyring();
        $bindId = $this->readState()['license_id'] ?? null;
        $context = $this->verifyContext();
        [$ok, $claims] = LicenseCertificate::verify($token, $keys, TrustRoot::product(), $bindId, null, 300, $context);
        if (! $ok && $claims === 'Unknown signing key — refresh the keyring.') {
            $this->refreshKeyring();
            [$ok, $claims] = LicenseCertificate::verify($token, $this->loadKeyring(), TrustRoot::product(), $bindId, null, 300, $context);
        }
        if (! $ok || ! is_array($claims)) {
            return false;
        }
        $rev = LicenseCertificate::revOf($claims);
        $prev = (int) ($this->readState()['rev'] ?? 0);
        if ($rev < $prev) {
            return false;
        }
        $this->writeState(['token' => $token, 'rev' => $rev, 'checked_at' => time(), 'last_refusal' => null]);
        $this->rememberLicenseId([], $claims);

        return true;
    }

    /** Persist the server-issued license id for offline binding checks. */
    protected function rememberLicenseId(array $res, array $claims = []): void
    {
        $id = $res['license']['id'] ?? $claims['lid'] ?? $claims['license_id'] ?? null;
        if (is_string($id) && $id !== '') {
            $this->writeState(['license_id' => $id]);
        }
    }

    /**
     * Keyring from disk, filtered against the pins in code.
     *
     * The file is only a cache of the server's answer: a key whose bytes are
     * not pinned is dropped. That makes "write your own public key to
     * storage/app/.license_keyring.json and mint a certificate" impossible
     * without editing the application code itself.
     */
    protected function loadKeyring(): array
    {
        $pinned = TrustRoot::certKeys();
        $fetched = [];
        try {
            $path = (string) config('licensing.keyring_file');
            if (is_file($path)) {
                $d = json_decode((string) file_get_contents($path), true);
                if (is_array($d) && isset($d['keys']) && is_array($d['keys'])) {
                    foreach ($d['keys'] as $kid => $b64) {
                        $raw = base64_decode((string) $b64, true);
                        if (is_string($raw) && strlen($raw) === 32) {
                            $fetched[(string) $kid] = $raw;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        // Keep the file's key ids (so certificates keep verifying) but only
        // with pinned bytes, and always retain the pins themselves as a
        // fallback when the cache has been deleted.
        $trusted = $this->pinnedFrom($fetched);
        foreach ($pinned as $kid => $raw) {
            if (! isset($trusted[$kid])) {
                $trusted[$kid] = $raw;
            }
        }

        return $trusted;
    }

    /** Keep only fetched keys whose raw bytes match a pinned key. */
    protected function pinnedFrom(array $fetched): array
    {
        $pinnedBytes = [];
        foreach (TrustRoot::certKeys() as $raw) {
            $pinnedBytes[base64_encode($raw)] = true;
        }
        $out = [];
        foreach ($fetched as $kid => $raw) {
            if (is_string($raw) && isset($pinnedBytes[base64_encode($raw)])) {
                $out[(string) $kid] = $raw;
            }
        }

        return $out;
    }

    protected function saveKeyring(array $keys): void
    {
        try {
            $enc = [];
            foreach ($keys as $kid => $raw) {
                $enc[$kid] = base64_encode($raw);
            }
            @file_put_contents((string) config('licensing.keyring_file'), json_encode(['fetched_at' => time(), 'keys' => $enc]));
            @chmod((string) config('licensing.keyring_file'), 0600);
        } catch (\Throwable $e) {
        }
    }

    /** Remove every local trace (deactivation, uninstall). */
    public function wipeLocal(): void
    {
        self::$memo = null;
        foreach ([(string) config('licensing.certificate_file'), storage_path('app/license.key')] as $f) {
            try {
                if (is_file($f)) {
                    @unlink($f);
                }
            } catch (\Throwable $e) {
            }
        }
    }

    // ---------- error shaping ----------

    /** Refusal bodies (200 + success:false) in either documented shape. */
    protected function refusal(array $res, string $fallback): string
    {
        $msg = $res['message'] ?? $res['error']['message'] ?? $fallback;
        $code = $res['error_code'] ?? $res['error']['code'] ?? null;
        $msg = trim((string) $msg) !== '' ? trim((string) $msg) : $fallback;
        if ($code) {
            $msg .= ' ['.$code.']';
        }
        $rid = $res['meta']['request_id'] ?? null;
        if ($rid) {
            $msg .= ' (ref '.$rid.')';
        }

        return $msg;
    }

    protected function humanError(LicensifyException $e): string
    {
        $msg = $e->getMessage();
        if ($e->requestId) {
            $msg .= ' (ref '.$e->requestId.')';
        }

        return $msg;
    }
}
