<?php

use App\Http\Middleware\BootstrapInstallerEnv;
use App\Http\Middleware\CheckLicense;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Middleware\NoCacheHeaders;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Branding\BrandingServiceProvider;
use Modules\Cheques\ChequeServiceProvider;
use Modules\Clients\ClientsServiceProvider;
use Modules\Challans\ChallansServiceProvider;
use Modules\Amc\AmcServiceProvider;
use Modules\Core\CoreServiceProvider;
use Modules\Documents\DocumentsServiceProvider;
use Modules\Expiry\ComplianceServiceProvider;
use Modules\Expenses\ExpensesServiceProvider;
use Modules\Installer\InstallerServiceProvider;
use Modules\Invoices\InvoicesServiceProvider;
use Modules\Leads\LeadsServiceProvider;
use Modules\Licensing\LicensingServiceProvider;
use Modules\Payments\PaymentsServiceProvider;
use Modules\Petty\PettyServiceProvider;
use Modules\Products\ProductsServiceProvider;
use Modules\Projects\ProjectsServiceProvider;
use Modules\Proposals\ProposalsServiceProvider;
use Modules\Purchases\PurchasesServiceProvider;
use Modules\Reports\ReportsServiceProvider;
use Modules\Settings\SettingsServiceProvider;
use Modules\Tasks\TasksServiceProvider;
use Modules\Updater\UpdaterServiceProvider;
use Modules\Work\WorkServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Innermost first layer: fresh uploads have no .env — mint key/debug
        // BEFORE sessions/encryption boot, or /install 500s immediately.
        $middleware->prepend(BootstrapInstallerEnv::class);
        // API JSON normalisation must also run before auth (see class doc).
        $middleware->prepend(\App\Http\Middleware\ForceJsonResponse::class);
        // Outermost layer: final word on response headers (kills browser + bfcache caching of pages).
        $middleware->append(NoCacheHeaders::class);
        // Poor-man's cron last: web traffic fires due scheduled tasks (backups,
        // reminders, recurring) in the background when no system cron exists.
        $middleware->append(\App\Http\Middleware\SchedulerHeartbeat::class);
        // Razorpay calls back server-to-server with its own HMAC signature — no session/CSRF.
        // Same for the public client checkout (token-guarded + throttled API-style calls).
        $middleware->validateCsrfTokens(except: ['webhooks/razorpay', 'invoices/pay/*']);
        $middleware->alias([
            'installed' => EnsureInstalled::class,
            'not.installed' => EnsureNotInstalled::class,
            'license' => CheckLicense::class,
            'admin' => EnsureAdmin::class,
            'api.active' => \App\Http\Middleware\EnsureApiUserActive::class,
            'api.json' => \App\Http\Middleware\ForceJsonResponse::class,
            'token.write' => \App\Http\Middleware\EnsureTokenWrite::class,
            'client.active' => \App\Http\Middleware\EnsureClientActive::class,
            'module' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'perm' => \App\Http\Middleware\EnsurePerm::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        AppServiceProvider::class,
        CoreServiceProvider::class,
        InstallerServiceProvider::class,
        BrandingServiceProvider::class,
        LicensingServiceProvider::class,
        UpdaterServiceProvider::class,
        ClientsServiceProvider::class,
        ChallansServiceProvider::class,
        AmcServiceProvider::class,
        LeadsServiceProvider::class,
        ProjectsServiceProvider::class,
        TasksServiceProvider::class,
        InvoicesServiceProvider::class,
        ProposalsServiceProvider::class,
        PurchasesServiceProvider::class,
        PaymentsServiceProvider::class,
        PettyServiceProvider::class,
        WorkServiceProvider::class,
        ExpensesServiceProvider::class,
        ProductsServiceProvider::class,
        ReportsServiceProvider::class,
        DocumentsServiceProvider::class,
        ComplianceServiceProvider::class,
        ChequeServiceProvider::class,
        SettingsServiceProvider::class,
    ])
    ->create();
