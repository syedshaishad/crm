<?php

namespace Modules\Core\WhatsApp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Invoices\Invoice;
use Modules\Settings\SettingStore;

/**
 * Meta WhatsApp Cloud API sender for payment reminders. Credentials live in
 * .env (WHATSAPP_PHONE_NUMBER_ID / WHATSAPP_TOKEN); template + toggles in
 * settings. Every attempt is journaled to whatsapp_logs.
 */
class WhatsAppSender
{
    public static function configured(SettingStore $store = null): bool
    {
        $store ??= app(SettingStore::class);

        return $store->get('whatsapp.enabled', '0') === '1'
            && config('whatsapp.phone_number_id') !== ''
            && config('whatsapp.token') !== '';
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
        $template = $store->get('whatsapp.template', 'payment_reminder');
        $lang = $store->get('whatsapp.lang', 'en');
        $params = [
            $invoice->client->name, $invoice->number, money($balance),
            $invoice->due_date ? d($invoice->due_date, 'j M Y') : 'on receipt',
        ];
        $log = function ($status, $wamid = null, $error = null) use ($invoice, $to, $template, $source) {
            DB::table('whatsapp_logs')->insert([
                'invoice_id' => $invoice->id, 'client_id' => $invoice->client_id,
                'recipient' => $to, 'template' => $template, 'source' => $source,
                'status' => $status, 'wamid' => $wamid ? substr((string) $wamid, 0, 255) : null,
                'error' => $error ? substr((string) $error, 0, 500) : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        try {
            $res = Http::timeout(25)->withToken(config('whatsapp.token'))->post(
                'https://graph.facebook.com/v21.0/'.config('whatsapp.phone_number_id').'/messages',
                [
                    'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
                    'template' => [
                        'name' => $template, 'language' => ['code' => $lang],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(fn ($t) => ['type' => 'text', 'text' => (string) $t], $params),
                        ]],
                    ],
                ]
            );
        } catch (\Throwable $e) {
            $log('failed', null, $e->getMessage());

            return ['ok' => false, 'message' => 'WhatsApp send failed: '.$e->getMessage()];
        }
        if (! $res->successful()) {
            $err = $res->json('error.message') ?? 'HTTP '.$res->status();
            $log('failed', null, $err);

            return ['ok' => false, 'message' => 'WhatsApp rejected the message: '.$err];
        }
        $wamid = $res->json('messages.0.id');
        $log('sent', $wamid);
        $invoice->update(['last_whatsapp_at' => now()]);

        return ['ok' => true, 'message' => 'WhatsApp reminder sent to +'.$to];
    }
}
