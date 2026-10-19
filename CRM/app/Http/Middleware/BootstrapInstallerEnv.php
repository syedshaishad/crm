<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Runs FIRST on every request (before sessions/encryption). Fresh uploads
 * ship WITHOUT .env — without an APP_KEY every session request 500s before
 * the installer wizard can even open. So when the app is not installed yet:
 * create .env from the example, mint a key, and apply both to this request
 * immediately (file values only load on the next request). Debug stays on
 * during install so errors are visible; completion flips it back off.
 */
class BootstrapInstallerEnv
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Normal fresh upload: no lock file yet -> create .env, mint key.
            // Self-heal: a lock file with no key (stray lock shipped in the
            // zip, or a lost .env) would otherwise 500 every page with
            // MissingAppKeyException before the wizard can even open.
            if ((! file_exists(config('installer.lock_file')) && ! config('app.installed')) || ! config('app.key')) {
                // No database exists yet: file drivers or every session/cache
                // call dials MySQL and the wizard dies. (The seeded baseline
                // pins file/file for installed life.)
                config(['session.driver' => 'file', 'cache.default' => 'file']);
                $this->bootstrapEnv();
            }
        } catch (\Throwable $e) {
        }

        return $next($request);
    }

    protected function bootstrapEnv(): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            $example = base_path('.env.example');
            if (file_exists($example)) {
                copy($example, $envPath);
            } else {
                // Distributable zips ship no dot-env files at all (the license
                // server refuses them). Seed the same baseline the example
                // used to carry so a fresh upload still boots and installs.
                $scheme = request()->getScheme();
                $host = request()->getHost();
                file_put_contents($envPath, implode("\n", [
                    'APP_NAME="CRM"',
                    'APP_ENV=production',
                    'APP_DEBUG=true',
                    'APP_URL='.$scheme.'://'.$host,
                    'APP_TIMEZONE=Asia/Kolkata',
                    'APP_INSTALLED=false',
                    'DB_CONNECTION=mysql',
                    'DB_HOST=127.0.0.1',
                    'DB_PORT=3306',
                    'DB_DATABASE=crm',
                    'DB_USERNAME=root',
                    'DB_PASSWORD=',
                    'SESSION_DRIVER=file',
                    'SESSION_SECURE_COOKIE=false',
                    'CACHE_STORE=file',
                    'QUEUE_CONNECTION=database',
                    'FILESYSTEM_DISK=public',
                    'MAIL_MAILER=smtp',
                    'MAIL_HOST=127.0.0.1',
                    'MAIL_PORT=2525',
                    'MAIL_USERNAME=null',
                    'MAIL_PASSWORD=null',
                    'MAIL_ENCRYPTION=null',
                    'MAIL_FROM_ADDRESS="hello@example.com"',
                    'MAIL_FROM_NAME="CRM"',
                    'LICENSE_SERVER_URL=https://license.shaishad.com',
                    'LICENSE_PRODUCT_CODE=SSCCRM1000V1',
                    'LICENSE_TIMEOUT=10',
                    'LICENSE_VERIFY_SSL=true',
                    'LICENSE_REVALIDATE_MINUTES=60',
                    'APP_VERSION='.config('version.version', '1.0.0'),
                    'UPDATE_BACKUP_BEFORE_UPDATE=true',
                    'UPDATE_PACKAGE_DIR=storage/app/updates',
                    '',
                ]));
            }
        }
        $content = file_get_contents($envPath) ?: '';
        if (! preg_match('/^APP_KEY=(.+)$/m', $content, $m) || trim($m[1], "\"' \t") === '') {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $content = preg_match('/^APP_KEY=.*$/m', $content)
                ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $content)
                : rtrim($content)."\nAPP_KEY=".$key."\n";
            file_put_contents($envPath, $content);
        } else {
            $key = trim($m[1], "\"' \t");
        }
        if (! preg_match('/^APP_DEBUG=true$/m', $content)) {
            $content = file_get_contents($envPath) ?: '';
            file_put_contents($envPath, preg_match('/^APP_DEBUG=.*$/m', $content)
                ? preg_replace('/^APP_DEBUG=.*$/m', 'APP_DEBUG=true', $content)
                : rtrim($content)."\nAPP_DEBUG=true\n");
        }
        config(['app.key' => $key, 'app.debug' => true]);
    }
}
