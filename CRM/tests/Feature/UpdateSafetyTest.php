<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Updater\PackageSigner;
use Modules\Updater\UpdateManager;
use Tests\TestCase;

class UpdateSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/upd-test-'.bin2hex(random_bytes(5));
        @mkdir($this->dir, 0777, true);
        $this->seed = bin2hex(random_bytes(32));
        $this->kid = 'test_update_key';
        config(['trust_root.updates.keys' => [
            $this->kid => base64_encode(PackageSigner::publicFromSecret($this->seed)),
        ]]);
    }

    protected function tearDown(): void
    {
        $this->rm($this->dir);
        parent::tearDown();
    }

    private string $seed;
    private string $kid;

    /** Build a zip with a minimal files/ payload. */
    private function makeZip(string $name, string $code = '<?php // payload'): string
    {
        $path = $this->dir.'/'.$name;
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('files/app/example.php', $code);
        $zip->addFromString('migrations/2026_01_01_000001_test.php', '<?php return new class {};');
        $zip->close();

        return $path;
    }

    private function rm(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    public function test_a_signed_package_verifies_and_tampering_is_rejected(): void
    {
        $zip = $this->makeZip('signed.zip');
        $signed = PackageSigner::signZip($zip, $this->seed, $this->kid, ['version' => '1.0.0']);
        $this->assertTrue($signed['ok']);

        $updater = new UpdateManager();
        $this->assertTrue($updater->peekSignature($zip)['ok']);
        $this->assertSame('1.0.0', $updater->peekMeta($zip)['version'] ?? null);

        $root = $this->dir.'/extracted';
        @mkdir($root, 0777, true);
        $z = new \ZipArchive();
        $z->open($zip);
        $z->extractTo($root);
        $z->close();
        $this->assertTrue($updater->verifyPackage($root)['ok']);

        file_put_contents($root.'/files/app/example.php', '<?php // changed');
        $result = $updater->verifyPackage($root);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('differ', $result['reason']);
    }

    public function test_unlisted_file_and_unsigned_package_are_refused(): void
    {
        $zip = $this->makeZip('signed2.zip');
        PackageSigner::signZip($zip, $this->seed, $this->kid, ['version' => '1.0.0']);
        $updater = new UpdateManager();

        $root = $this->dir.'/extra';
        @mkdir($root, 0777, true);
        $z = new \ZipArchive();
        $z->open($zip);
        $z->extractTo($root);
        $z->close();
        file_put_contents($root.'/files/app/shell.php', '<?php echo "smuggled";');
        $this->assertStringContainsString('not listed', $updater->verifyPackage($root)['reason']);

        $bare = $this->dir.'/bare';
        @mkdir($bare.'/files', 0777, true);
        file_put_contents($bare.'/files/app.php', '<?php');
        $result = $updater->verifyPackage($bare);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Unsigned package', $result['reason']);
    }

    public function test_package_signed_by_an_unpinned_key_is_refused(): void
    {
        $zip = $this->makeZip('forged.zip');
        $other = bin2hex(random_bytes(32));
        PackageSigner::signZip($zip, $other, 'rel_attacker', ['version' => '9.9.9']);

        $result = (new UpdateManager())->peekSignature($zip);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('unknown key', strtolower($result['detail']));
    }

    public function test_update_php_is_never_executed_when_reading_metadata(): void
    {
        $marker = $this->dir.'/EXECUTED';
        $code = "<?php file_put_contents(".var_export($marker, true).", 'oops'); return ['version' => '7.7.7'];";
        $zip = $this->makeZip('legacy.zip');
        $z = new \ZipArchive();
        $z->open($zip);
        $z->addFromString('update.php', $code);
        $z->close();

        $meta = (new UpdateManager())->peekMeta($zip);
        $this->assertSame('7.7.7', $meta['version'] ?? null);
        $this->assertFileDoesNotExist($marker, 'Package PHP was executed!');
    }
}
