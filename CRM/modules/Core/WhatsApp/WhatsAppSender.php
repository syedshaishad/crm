<?php

namespace Modules\Core\WhatsApp;

use Illuminate\Support\Facades\Http;
use Modules\Invoices\Invoice;
use Modules\Settings\SettingStore;

/**
 * WhatsApp dispatcher for payment reminders and scheduled reports.
 *
 * Two providers are supported:
 *  - Meta WhatsApp Cloud API (credentials in .env, approved template in settings)
 *  - AiSensy (API key encrypted in settings, approved API campaign)
 *
 * The rest of the CRM keeps calling this class; the selected provider decides
 * which HTTP API is used and every attempt is journaled to whatsapp_logs.
 */
class WhatsAppSender
{
    public const PROVIDERS = ['meta', 'aisensy'];

    /** Active provider. Auto-detects AiSensy when it is the only one configured. */
    public static function provider(SettingStore $store = null): string
    {
        $store ??= app(SettingStore::class);
        $chosen = (string) $store->get('whatsapp.provider', '');
        if (in_array($chosen, self::PROVIDERS, true)) {
            return $chosen;
        }

        // Legacy installs (no provider saved): stay on Meta unless only
        // AiSensy has credentials.
        if (self::metaReady() || ! AiSensySender::hasKey($store)) {
            return 'meta';
        }

        return 'aisensy';
    }

    public static function configured(SettingStore $store = null): bool
    {
        $store ??= app(SettingStore::class);
        if ($store->get('whatsapp.enabled', '0') !== '1') {
            return false;
        }

        return self::provider($store) === 'aisensy'
            ? AiSensySender::configured($store)
            : self::metaReady();
    }

    /** Meta credentials present in .env. */
    public static function metaReady(): bool
    {
        return (string) config('whatsapp.phone_number_id') !== ''
            && (string) config('whatsapp.token') !== '';
    }

    /** Normalize an Indian client phone to wa_id digits. */
    public static function waId(?string $phone, string $countryCode = '91'): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 10) {
            $digits = $countryCode.$digits;
        }
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /**
     * Send one template/campaign message through the active provider.
     *
     * @param  array<int, string>  $params  Template body parameters, in template order.
     * @param  array{invoice_id?: int|null, client_id?: int|null, client_name?: string|null, user_name?: string|null}  $context
     * @return array{ok: bool, message: string, id?: string}
     */
    public static function sendTemplate(string $to, string $kind, array $params, array $context = [], string $source = 'manual'): array
    {
        $store = app(SettingStore::class);
        if (! self::configured($store)) {
            return ['ok' => false, 'message' => 'WhatsApp is not configured — add credentials under Settings → Integrations.'];
        }

        if (self::provider($store) === 'aisensy') {
            return AiSensySender::send($to, AiSensySender::campaign($store, $kind), $params, $context, $source);
        }

        $template = match ($kind) {
            'report' => (string) $store->get('whatsapp.report_template', 'report_ready'),
            default => (string) $store->get('whatsapp.template', 'payment_reminder'),
        };
        $lang = (string) $store->get('whatsapp.lang', 'en');
        $journal = fn (string $status, ?string $wamid = null, ?string $error = null) => WhatsAppLog::record([
            'invoice_id' => $context['invoice_id'] ?? null,
            'client_id' => $context['client_id'] ?? null,
            'recipient' => $to,
            'template' => $template,
            'provider' => 'meta',
            'source' => $source,
            'status' => $status,
            'wamid' => $wamid,
            'error' => $error,
        ]);

        try {
            $res = Http::timeout(25)->withToken((string) config('whatsapp.token'))->post(
                'https://graph.facebook.com/v21.0/'.config('whatsapp.phone_number_id').'/messages',
                [
                    'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                    'template' => [
                        'name' => $template, 'language' => ['code' => $lang],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(fn ($t) => ['type' => 'text', 'text' => (string) $t], array_values($params)),
                        ]],
                    ],
                ]
            );
        } catch (\Throwable $e) {
            $journal('failed', null, $e->getMessage());

            return ['ok' => false, 'message' => 'WhatsApp send failed: '.$e->getMessage()];
        }

        if (! $res->successful()) {
            $err = $res->json('error.message');
            $err = is_scalar($err) ? (string) $err : 'HTTP '.$res->status();
            $journal('failed', null, $err);

            return ['ok' => false, 'message' => 'WhatsApp rejected the message: '.$err];
        }

        $wamid = $res->json('messages.0.id');
        $journal('sent', is_scalar($wamid) ? (string) $wamid : null);

        return ['ok' => true, 'message' => 'WhatsApp sent to +'.$to, 'id' => is_scalar($wamid) ? (string) $wamid : ''];
    }

    /** @return array{ok: bool, message: string} */
    public static function sendReminder(Invoice $invoice, string $source = 'manual'): array
    {
        $store = app(SettingStore::class);
        $invoice->loadMissing('client');
        if (! self::configured($store)) {
            return ['ok' => false, 'message' => 'WhatsApp is not configured — add credentials under Settings → Integrations.'];
        }
        $to = self::waId($invoice->client->phone ?? null, $store->get('whatsapp.country_code', '91'));
        if (! $to) {
            return ['ok' => false, 'message' => 'Client has no usable mobile number.'];
        }
        $balance = $invoice->balance();
        if ($balance <= 0) {
            return ['ok' => false, 'message' => 'Nothing due — invoice is settled.'];
        }
        $params = [
            $invoice->client->name, $invoice->number, money($balance),
            $invoice->due_date ? d($invoice->due_date, 'j M Y') : 'on receipt',
        ];
        $res = self::sendTemplate($to, 'reminder', $params, [
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'client_name' => $invoice->client->name,
            'user_name' => $invoice->client->name,
        ], $source);
        if ($res['ok']) {
            $invoice->update(['last_whatsapp_at' => now()]);
        }

        return $res;
    }

    /**
     * Scheduled report nudge: report name, subject and headline figure.
     *
     * @param  array<int, string>  $lines
     * @return array{ok: bool, message: string}
     */
    public static function sendReport(string $to, string $reportName, string $subject, array $lines, string $source = 'auto'): array
    {
        $params = [$reportName, $subject, $lines[0] ?? ''];

        return self::sendTemplate($to, 'report', $params, [
            'user_name' => setting('brand.company_name', config('app.name')),
        ], $source);
    }

    /**
     * Test the active provider. Meta's stock payment_reminder template expects
     * four parameters, so keep the historical sample when none are supplied.
     *
     * @param  array<int, string>  $params
     * @return array{ok: bool, message: string}
     */
    public static function sendTest(string $to, array $params = [], string $source = 'test'): array
    {
        $store = app(SettingStore::class);
        if ($params === [] && self::provider($store) === 'meta') {
            $params = ['CRM test', 'oka', 'ok', 'ok'];
        }

        return self::sendTemplate($to, 'test', $params, [
            'user_name' => setting('brand.company_name', config('app.name')),
        ], $source);
    }
}
