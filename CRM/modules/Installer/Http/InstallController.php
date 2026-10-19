<?php

namespace Modules\Installer\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Installer\EnvWriter;
use Modules\Installer\RequirementsChecker;
use Modules\Settings\SettingStore;

class InstallController extends Controller
{
    public function welcome()
    {
        return view('installer.welcome');
    }

    public function requirements(RequirementsChecker $check)
    {
        return view('installer.requirements', ['report' => $check->check()]);
    }

    public function database()
    {
        return view('installer.database');
    }

    public function databaseStore(Request $request, RequirementsChecker $check)
    {
        $data = $request->validate([
            'DB_HOST' => 'required|string', 'DB_PORT' => 'required|numeric',
            'DB_DATABASE' => 'required|string', 'DB_USERNAME' => 'required|string',
            'DB_PASSWORD' => 'nullable|string',
        ]);
        $test = $check->testDatabase($data['DB_HOST'], $data['DB_PORT'], $data['DB_DATABASE'], $data['DB_USERNAME'], $data['DB_PASSWORD'] ?? '');
        if (! $test['ok']) {
            return back()->withErrors(['db' => $test['error']])->withInput();
        }
        // Real domain from the first write — asset() links and emailed public
        // URLs must point at the host, never localhost.
        app(EnvWriter::class)->write($data + ['APP_URL' => $request->getSchemeAndHttpHost()]);
        // .env was written, but its values are NOT reloaded within this request —
        // the running process still holds the bootstrap defaults (root/empty).
        // Apply the submitted credentials to the live config too, or the migrate
        // call below reconnects as root@localhost and fails with access denied.
        config([
            'database.connections.mysql.host' => $data['DB_HOST'],
            'database.connections.mysql.port' => $data['DB_PORT'],
            'database.connections.mysql.database' => $data['DB_DATABASE'],
            'database.connections.mysql.username' => $data['DB_USERNAME'],
            'database.connections.mysql.password' => $data['DB_PASSWORD'] ?? '',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            return back()->withErrors(['db' => 'Migration failed: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('installer.admin');
    }

    public function admin()
    {
        return view('installer.admin');
    }

    public function adminStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        DB::table('users')->updateOrInsert(
            ['email' => $data['email']],
            ['name' => $data['name'], 'password' => Hash::make($data['password']), 'role' => 'admin', 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
        );

        return redirect()->route('installer.company');
    }

    public function company()
    {
        return view('installer.company');
    }

    public function companyStore(Request $request, SettingStore $settings)
    {
        $data = $request->validate([
            'app_name' => 'required|string|max:100',
            'company_name' => 'required|string|max:255',
            'company_email' => 'nullable|email',
            'company_phone' => 'nullable|string|max:50',
            'company_address' => 'nullable|string',
            'company_gstin' => 'nullable|string|max:20',
        ]);
        $settings->set('app.name', $data['app_name'], 'brand');
        $settings->set('brand.company_name', $data['company_name'], 'brand');
        $settings->set('company.email', $data['company_email'] ?? '', 'company');
        $settings->set('company.phone', $data['company_phone'] ?? '', 'company');
        $settings->set('company.address', $data['company_address'] ?? '', 'company');
        $settings->set('company.gstin', $data['company_gstin'] ?? '', 'company');
        app(EnvWriter::class)->write(['APP_NAME' => $data['app_name']]);

        return redirect()->route('installer.mail');
    }

    public function mail()
    {
        return view('installer.mail');
    }

    public function mailStore(Request $request)
    {
        $data = $request->validate([
            'MAIL_HOST' => 'required|string', 'MAIL_PORT' => 'required|numeric',
            'MAIL_USERNAME' => 'nullable|string', 'MAIL_PASSWORD' => 'nullable|string',
            'MAIL_ENCRYPTION' => 'nullable|string', 'MAIL_FROM_ADDRESS' => 'required|email',
            'MAIL_FROM_NAME' => 'required|string',
        ]);
        app(EnvWriter::class)->write($data + ['MAIL_MAILER' => 'smtp']);

        return redirect()->route('installer.integrations');
    }

    public function integrations()
    {
        return view('installer.integrations');
    }

    public function integrationsStore(Request $request, SettingStore $settings)
    {
        $data = $request->validate([
            'RAZORPAY_KEY_ID' => 'nullable|string',
            'RAZORPAY_KEY_SECRET' => 'nullable|string',
            'TAX_DEFAULT_GST_RATE' => 'nullable|numeric|in:0,5,12,18,28',
            'TAX_ENABLE_GST' => 'nullable|boolean',
            'invoice_prefix' => 'nullable|string|max:20',
        ]);
        app(EnvWriter::class)->write([
            'RAZORPAY_KEY_ID' => $data['RAZORPAY_KEY_ID'] ?? '',
            'RAZORPAY_KEY_SECRET' => $data['RAZORPAY_KEY_SECRET'] ?? '',
            'TAX_DEFAULT_GST_RATE' => $data['TAX_DEFAULT_GST_RATE'] ?? 18,
            'TAX_ENABLE_GST' => isset($data['TAX_ENABLE_GST']) ? 'true' : 'true',
        ]);
        $settings->set('invoice.prefix', $data['invoice_prefix'] ?? 'INV-', 'invoice');
        $settings->set('tax.default_gst_rate', $data['TAX_DEFAULT_GST_RATE'] ?? 18, 'tax');
        // Seed default version + demo-free branding seed
        $settings->set('app.version', config('version.version', '1.0.0'), 'system');
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $e) {
        }
        // Self-healing: an extracted real directory at public/storage defeats
        // BOTH the symlink and the fallback route (the web server serves the
        // dead directory and Laravel never runs). Convert it to a live link.
        if ($linkProblem = \Modules\Installer\StorageLink::ensure()) {
            // Non-fatal: the storage.fallback route still serves uploads. Surface it
            // as a warning so a missing link is not later misread as broken uploads.
            return redirect()->route('installer.finish')->with('storageWarning', $linkProblem.' '.
                'Uploaded files still work through the built-in fallback route, but for best '.
                'performance create it manually: php artisan storage:link');
        }

        return redirect()->route('installer.license');
    }

    public function license()
    {
        return view('installer.license', [
            'apiConfigured' => trim((string) config('licensing.api_key', '')) !== '' && trim((string) config('licensing.api_secret', '')) !== '',
        ]);
    }

    public function licenseStore(Request $request)
    {
        $data = $request->validate(['license_key' => 'required|string|max:200']);
        // Reseller API credentials ship in .env; if this copy lacks them the
        // buyer can paste them here once (saved to .env, used immediately).
        $apiKey = trim((string) $request->input('api_key', ''));
        $apiSecret = trim((string) $request->input('api_secret', ''));
        if ($apiKey !== '' && $apiSecret !== '') {
            app(EnvWriter::class)->write(['LICENSE_API_KEY' => $apiKey, 'LICENSE_API_SECRET' => $apiSecret]);
            config(['licensing.api_key' => $apiKey, 'licensing.api_secret' => $apiSecret]);
        }
        /** @var \Modules\Licensing\LicenseManager $lm */
        $lm = app(\Modules\Licensing\LicenseManager::class);
        [$ok, $msg] = $lm->activate($data['license_key']);
        if (! $ok) {
            return back()->withErrors(['license_key' => $msg])->withInput();
        }

        return redirect()->route('installer.finish')->with('ok', $msg);
    }

    public function finish()
    {
        return view('installer.finish');
    }

    public function complete(EnvWriter $env)
    {
        // Defense in depth: the finish form is only reachable after a
        // successful activation, but never lock an unlicensed install.
        /** @var \Modules\Licensing\LicenseManager $lm */
        $lm = app(\Modules\Licensing\LicenseManager::class);
        if (! $lm->accessAllowed()) {
            return redirect()->route('installer.license')
                ->withErrors(['license_key' => 'Activate a license key before completing the installation.']);
        }
        try {
            Artisan::call('key:generate', ['--force' => true]);
        } catch (\Throwable $e) {
        }
        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        } catch (\Throwable $e) {
        }
        $env->setInstalled();

        return view('installer.done');
    }
}
