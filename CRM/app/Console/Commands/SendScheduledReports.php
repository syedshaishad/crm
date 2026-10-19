<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Modules\Branding\Brand;
use Modules\Core\WhatsApp\WhatsAppSender;
use Modules\Reports\ReportSchedule;
use Modules\Settings\SettingStore;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled {--dry-run : Print what would send} {--force : Run all active schedules regardless of day}';

    protected $description = 'Email/WhatsApp scheduled reports (weekly Monday, monthly 1st).';

    public function handle(): int
    {
        $today = now()->toDateString();
        $rows = ReportSchedule::where('is_active', true)->get()->filter(fn ($s) => $this->option('force') || $s->isDue($today));
        if ($rows->isEmpty()) {
            $this->info('No scheduled reports due.');

            return self::SUCCESS;
        }
        foreach ($rows as $s) {
            [$subject, $lines, $label] = $s->compile($today);
            if ($this->option('dry-run')) {
                $this->line('Would send "'.$s->name.'" ('.$s->type.'/'.$s->frequency.') to '.$s->recipients);
                $this->line('  '.$subject);

                continue;
            }
            if ($s->channel === 'whatsapp') {
                $this->sendWhatsapp($s, $subject, $lines);
            } else {
                $this->sendEmail($s, $subject, $lines, $label);
            }
            $s->update(['last_run_on' => $today]);
        }

        return self::SUCCESS;
    }

    protected function sendEmail(ReportSchedule $s, string $subject, array $lines, string $label): void
    {
        $brand = app(Brand::class)->all();
        $to = collect(preg_split('/[\s,;]+/', $s->recipients))->map(fn ($e) => trim($e))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->values()->all();
        if (empty($to)) {
            $this->error('Skipped "'.$s->name.'": no valid recipients.');

            return;
        }
        $body = $s->name.' — '.$label."\n\n".implode("\n", $lines)."\n\n— ".($brand['company_name'] ?? config('app.name'));
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
            $this->info('Emailed "'.$s->name.'" to '.implode(', ', $to).'.');
        } catch (\Throwable $e) {
            $this->error('Mail failed for "'.$s->name.'": '.$e->getMessage());
        }
    }

    /**
     * WhatsApp reports ride the approved template (Cloud API is
     * template-only): name, period and the headline figure.
     */
    protected function sendWhatsapp(ReportSchedule $s, string $subject, array $lines): void
    {
        $store = app(SettingStore::class);
        if (! WhatsAppSender::configured($store)) {
            $this->error('Skipped "'.$s->name.'": WhatsApp not configured.');

            return;
        }
        $template = $store->get('whatsapp.report_template', 'report_ready');
        foreach (collect(preg_split('/[\s,;]+/', $s->recipients))->map(fn ($e) => trim($e))->filter() as $phone) {
            $to = WhatsAppSender::waId($phone, $store->get('whatsapp.country_code', '91'));
            if (! $to) {
                $this->error('Bad number in "'.$s->name.'": '.$phone);

                continue;
            }
            try {
                $res = Http::timeout(25)->withToken(config('whatsapp.token'))->post(
                    'https://graph.facebook.com/v21.0/'.config('whatsapp.phone_number_id').'/messages',
                    ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                        'template' => ['name' => $template, 'language' => ['code' => $store->get('whatsapp.lang', 'en')],
                            'components' => [['type' => 'body', 'parameters' => array_map(fn ($t) => ['type' => 'text', 'text' => mb_substr((string) $t, 0, 200)], [$s->name, $subject, $lines[0] ?? ''])]]]
                    ]
                );
                $res->successful() ? $this->info('WhatsApped "'.$s->name.'" to +'.$to.'.') : $this->error('WhatsApp rejected for +'.$to.': '.($res->json('error.message') ?? 'HTTP '.$res->status()));
            } catch (\Throwable $e) {
                $this->error('WhatsApp failed for +'.$to.': '.$e->getMessage());
            }
        }
    }
}
