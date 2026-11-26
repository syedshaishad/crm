<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckLicense;
use App\Http\Middleware\EnsureInstalled;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Clients\Client;
use Modules\Licensing\LicenseManager;
use Modules\Proposals\Proposal;
use Tests\TestCase;

class LicensingHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;
    private string $seed;
    private string $kid = 'test_cert_key';
    private string $installation = 'inst_aabbccddeeff00112233445566778899';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/lic-test-'.bin2hex(random_bytes(5));
        @mkdir($this->dir, 0777, true);
        $this->seed = bin2hex(random_bytes(32));
        file_put_contents($this->dir.'/installation', $this->installation);
        config([
            'licensing.certificate_file' => $this->dir.'/certificate.json',
            'licensing.keyring_file' => $this->dir.'/keyring.json',
            'licensing.installation_file' => $this->dir.'/installation',
            'licensing.license_key' => 'TEST-LICENSE-KEY',
            'licensing.api_key' => 'lk_test',
            'licensing.api_secret' => 'test-secret',
            'licensing.server_url' => 'https://license.example.test',
            'licensing.revalidate_minutes' => 60,
            'app.url' => 'https://example.test',
            'trust_root.product' => 'SSCCRM1000V1',
            'trust_root.issuers' => ['license.example.test'],
            'trust_root.certificates.keys' => [
                $this->kid => base64_encode($this->publicKey($this->seed)),
            ],
            'trust_root.certificates.require_binding' => true,
            'trust_root.certificates.max_offline_seconds' => 7 * 86400,
        ]);
        LicenseManager::forget();
    }

    protected function tearDown(): void
    {
        LicenseManager::forget();
        $this->rm($this->dir);
        parent::tearDown();
    }

    private function publicKey(string $seed): string
    {
        return sodium_crypto_sign_publickey_from_secretkey(
            sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(hex2bin($seed)))
        );
    }

    private function token(array $claims, string $seed, string $kid): string
    {
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $header = $b64((string) json_encode(['alg' => 'EdDSA', 'kid' => $kid, 'typ' => 'LIC', 'v' => 1]));
        $payload = $b64((string) json_encode($claims));
        $signed = $header.'.'.$payload;
        $secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(hex2bin($seed)));

        return $signed.'.'.$b64(sodium_crypto_sign_detached($signed, $secret));
    }

    private function claims(array $overrides = []): array
    {
        return array_merge([
            'dom' => 'example.test',
            'inst' => $this->installation,
            'iss' => 'https://license.example.test',
            'pcode' => 'SSCCRM1000V1',
            'lid' => 'lic_123',
            'status' => 'active',
            'plan' => 'development',
            'rev' => 1,
            'iat' => time() - 60,
            'nbf' => time() - 60,
            'exp' => time() + 3600,
        ], $overrides);
    }

    private function writeState(string $token, array $extra = []): void
    {
        file_put_contents($this->dir.'/certificate.json', json_encode(array_merge([
            'token' => $token,
            'license_id' => 'lic_123',
            'rev' => 1,
            'checked_at' => time(),
        ], $extra)));
    }

    private function writeKeyring(string $seed, string $kid): void
    {
        file_put_contents($this->dir.'/keyring.json', json_encode([
            'fetched_at' => time(),
            'keys' => [$kid => base64_encode($this->publicKey($seed))],
        ]));
    }

    private function fakeRefusal(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'valid' => false, 'message' => 'invalid'], 200)]);
    }

    private function rm(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir.'/*') ?: [] as $f) {
            is_dir($f) ? $this->rm($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    public function test_a_self_signed_keyring_cannot_mint_a_certificate(): void
    {
        $this->fakeRefusal();
        $attackerSeed = bin2hex(random_bytes(32));
        $this->writeKeyring($attackerSeed, 'attacker_key');
        $this->writeState($this->token($this->claims(), $attackerSeed, 'attacker_key'));

        $status = app(LicenseManager::class)->status();
        $this->assertFalse($status['active'], json_encode($status));
    }

    public function test_a_pinned_certificate_bound_to_this_install_is_accepted(): void
    {
        $this->writeKeyring($this->seed, $this->kid);
        $this->writeState($this->token($this->claims(), $this->seed, $this->kid));

        $status = app(LicenseManager::class)->status();
        $this->assertTrue($status['active'], json_encode($status));
        $this->assertSame('example.test', $status['bound_domain']);
    }

    public function test_certificates_for_another_domain_or_installation_are_rejected(): void
    {
        $this->fakeRefusal();
        $this->writeKeyring($this->seed, $this->kid);
        $this->writeState($this->token($this->claims(['dom' => 'evil.test']), $this->seed, $this->kid));
        $this->assertFalse(app(LicenseManager::class)->status()['active']);

        LicenseManager::forget();
        $this->writeState($this->token($this->claims(['inst' => 'inst_someone_else']), $this->seed, $this->kid));
        $this->assertFalse(app(LicenseManager::class)->status()['active']);
    }

    public function test_offline_grace_cannot_be_extended_through_claims_or_env(): void
    {
        $this->fakeRefusal();
        config(['licensing.revalidate_minutes' => 999999]);
        $this->writeKeyring($this->seed, $this->kid);
        $this->writeState($this->token($this->claims([
            'iat' => time() - 100 * 86400,
            'exp' => time() + 100 * 86400,
            'grace' => 100 * 86400,
        ]), $this->seed, $this->kid));

        $this->assertFalse(app(LicenseManager::class)->status()['active']);
    }

    public function test_live_module_claims_are_honoured(): void
    {
        Http::fake(['*' => Http::response([
            'success' => true,
            'valid' => true,
            'license' => ['id' => 'lic_123'],
            'certificate' => $this->token($this->claims(['modules' => ['Clients']]), $this->seed, $this->kid),
        ], 200)]);

        $lm = app(LicenseManager::class);
        $status = $lm->status(true);
        $this->assertTrue($status['active'], json_encode($status));
        $this->assertTrue($lm->moduleAllowed('Clients'));
        $this->assertFalse($lm->moduleAllowed('Invoices'));
    }

    public function test_unnamed_routes_do_not_bypass_the_license_gate(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);
        $fake = new class extends LicenseManager {
            public function accessAllowed(): bool
            {
                return false;
            }

            public function status(bool $force = false): array
            {
                return ['active' => false, 'reason' => 'blocked', 'error_code' => 'TEST'];
            }
        };
        $this->app->instance(LicenseManager::class, $fake);
        $this->actingAs(User::create([
            'name' => 'T', 'email' => 'gate@example.test', 'password' => bcrypt('password'),
            'role' => 'admin', 'is_active' => true,
        ]));

        $this->get('/clients')->assertStatus(402);
        // routes/web.php:216 is unnamed — it used to skip CheckLicense entirely.
        $client = Client::create(['name' => 'Test Client', 'status' => 'active']);
        $proposal = Proposal::create(['number' => 'P-HARD-1', 'client_id' => $client->id, 'status' => 'draft']);
        $this->patch('/proposals/'.$proposal->id, [])->assertStatus(402);
        // Plumbing stays reachable on a blocked install.
        $this->get('/settings/license')->assertOk();
    }
}
