<?php

namespace App\Providers;

use App\Console\Commands\GenerateRecurringExpenses;
use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\ProposalsMarkExpired;
use App\Console\Commands\RunBackups;
use App\Console\Commands\SendReminders;
use App\Models\User;
use App\Observers\ActivityObserver;
use App\Services\WebPushService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Modules\Branding\Brand;
use Modules\Clients\Client;
use Modules\Documents\Document;
use Modules\Expenses\Expense;
use Modules\Invoices\Invoice;
use Modules\Invoices\MarkOverdueCommand;
use Modules\Leads\Lead;
use Modules\Licensing\LicenseManager;
use Modules\Payments\Payment;
use Modules\Projects\Project;
use Modules\Proposals\Proposal;
use Modules\Settings\SettingStore;
use Modules\Tasks\Task;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingStore::class);
        $this->app->singleton(Brand::class);
        $this->app->singleton(LicenseManager::class);
        $this->app->singleton(WebPushService::class);
    }

    public function boot(): void
    {
        // Themed pill pager instead of the framework's unstyled default.
        Paginator::defaultView('pagination.simple');
        // Password reset email honors Settings → Email templates.
        try {
            ResetPassword::toMailUsing(function ($notifiable, $token) {
                $app = config('app.name');
                $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
                $msg = (new MailMessage)
                    ->subject(render_template('email.reset_subject', ['app_name' => $app]))
                    ->greeting('Hello '.($notifiable->name ?? 'there').'!');
                foreach (preg_split('/\R{2,}/', render_template('email.reset_body', ['app_name' => $app])) as $para) {
                    if (trim($para) !== '') {
                        $msg->line(trim($para));
                    }
                }

                return $msg->action('Reset Password', $url);
            });
        } catch (\Throwable $e) {
        }
        // White-label sender: Settings > Branding mail-from wins over .env.
        try {
            $store = app(SettingStore::class);
            $fromAddr = $store->get('mail.from_address', config('mail.from.address'));
            $fromName = $store->get('mail.from_name', config('mail.from.name'));
            if ($fromAddr) {
                Mail::alwaysFrom($fromAddr, $fromName ?: config('app.name'));
            }
        } catch (\Throwable $e) {
        }

        if ($this->app->runningInConsole()) {
            $this->commands([MarkOverdueCommand::class, ProposalsMarkExpired::class, SendReminders::class, GenerateRecurringInvoices::class, GenerateRecurringExpenses::class, RunBackups::class]);
        }
        // Activity log: automatic who-did-what on the main records.
        try {
            foreach ([
                Client::class, Project::class,
                Task::class, Lead::class,
                Proposal::class, Invoice::class,
                Payment::class, Expense::class,
                Document::class, User::class,
            ] as $model) {
                $model::observe(ActivityObserver::class);
            }
        } catch (\Throwable $e) {
        }
        // Mail outbox log: every sent email lands here (no model = no recursion).
        try {
            Event::listen(
                MessageSent::class,
                function ($event) {
                    try {
                        // Laravel 12 renamed the payload ($message → $sent) and it
                        // is a wrapper: unwrap to the Symfony message first.
                        $sent = $event->sent ?? $event->message ?? null;
                        $sym = null;
                        if (is_object($sent)) {
                            if (method_exists($sent, 'getSymfonySentMessage')) {
                                $sym = $sent->getSymfonySentMessage();
                            } elseif (method_exists($sent, 'getOriginalMessage')) {
                                $sym = $sent->getOriginalMessage();
                            }
                        }
                        $orig = ($sym && method_exists($sym, 'getOriginalMessage')) ? $sym->getOriginalMessage() : $sym;
                        if (! $orig || ! method_exists($orig, 'getTo')) {
                            return;
                        }
                        $to = collect($orig->getTo() ?? [])->map(fn ($a) => is_object($a) ? ($a->getAddress() ?? '') : (string) $a)->filter()->implode(', ');
                        DB::table('mail_logs')->insert([
                            'to' => mb_substr($to, 0, 500), 'subject' => mb_substr((string) $orig->getSubject(), 0, 255),
                            'status' => 'sent', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                    }
                }
            );
        } catch (\Throwable $e) {
        }
        // Share white-label brand with every view. No hard-coded names in templates.
        // Pre-install (no lock file): skip the database entirely — there is no
        // database yet, and probing it here hangs installer AND error pages.
        view()->composer('*', function ($view) {
            try {
                if (! file_exists(config('installer.lock_file')) && ! config('app.installed')) {
                    $view->with('brand', array_merge(config('branding'), ['app_name' => config('app.name')]));

                    return;
                }
                $view->with('brand', app(Brand::class)->all());
            } catch (\Throwable $e) {
                $view->with('brand', config('branding'));
            }
        });
        // Keep the per-request settings snapshot fresh in queue/Octane workers.
        $this->app->terminating(function () {
            try {
                $this->app->make(SettingStore::class)->flush();
            } catch (\Throwable $e) {
            }
        });
    }
}
