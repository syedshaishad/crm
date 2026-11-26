<?php

namespace Modules\Core\WhatsApp;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\Settings\SettingStore;

/**
 * AiSensy WhatsApp Business API sender.
 *
 * AiSensy delivers business-initiated messages through a "campaign" that wraps
 * an approved Meta template. The API key is saved from Settings → Integrations
 * and encrypted at rest; campaign names are plain settings. Every attempt is
 * journaled to whatsapp_logs with provider = aisensy.
 *
 * @see https://wiki.aisensy.com/en/articles/11501889-api-reference-docs
 */
class AiSensySender
{
    /** Logical campaign slots the CRM knows how to send. */
    public const KINDS = ['reminder', 'report', 'test'];

    public static function configured(SettingStore $store = null): bool
    {
        $store ??= app(SettingStore::class);

        return $store->get('whatsapp.enabled', '0') === '1'
            && self::apiKey($store) !== '';
    }

    /** True when a key exists (without exposing it to views). */
    public static function hasKey(SettingStore $store = null): bool
    {
        return self::apiKey($store) !== '';
    }

    /** Decrypt the saved API key; falls back to the .env value. */
    public static function apiKey(SettingStore $store = null): string
    {
        $store ??= app(SettingStore::class);
        $stored = (string) $store->get('aisensy.api_key', '');
        if ($stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (\Throwable $e) {
                // APP_KEY changed or the value was hand-edited — never leak it.
                return '';
            }
        }

        return (string) config('aisensy.api_key', '');
    }

    /**
     * Campaign name for a slot. No cross-slot fallback: a reminder template and
     * a report template take different parameters, so silently reusing one for
     * the other would fail at AiSensy with a confusing error.
     */
    public static function campaign(SettingStore $store, string $kind = 'reminder'): string
    {
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'reminder';
        $name = trim((string) $store->get('aisensy.campaign.'.$kind, ''));

        return $name !== '' ? $name : (string) config('aisensy.campaigns.'.$kind, '');
    }

    /**
     * Send one campaign message.
     *
     * @param  array<int, string>  $params  Template body parameters, in template order.
     * @param  array{invoice_id?: int|null, client_id?: int|null, client_name?: string|null, user_name?: string|null}  $context
     * @return array{ok: bool, message: string, id?: string}
     */
    public static function send(string $to, string $campaign, array $params, array $context = [], string $source = 'manual'): array
    {
        $store = app(SettingStore::class);
        if ($store->get('whatsapp.enabled', '0') !== '1') {
            return ['ok' => false, 'message' => 'WhatsApp is disabled — enable it under Settings → Integrations.'];
        }
        $apiKey = self::apiKey($store);
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'AiSensy API key is missing — add it under Settings → Integrations.'];
        }
        if ($campaign === '') {
            return ['ok' => false, 'message' => 'AiSensy campaign name is missing — add it under Settings → Integrations.'];
        }

        $params = array_values(array_map(fn ($v) => (string) $v, $params));
        $payload = [
            'apiKey' => $apiKey,
            'campaignName' => $campaign,
            'destination' => $to,
            'userName' => (string) ($context['user_name'] ?? $context['client_name'] ?? setting('brand.company_name', config('app.name'))),
            'templateParams' => $params,
            'source' => (string) $store->get('aisensy.source', config('aisensy.source', 'CRM')),
            'media' => (object) [],
            'buttons' => [],
            'carouselCards' => [],
            'location' => (object) [],
            'attributes' => (object) [],
            'paramsFallbackValue' => (object) [],
        ];

        $journal = fn (string $status, ?string $wamid = null, ?string $error = null) => WhatsAppLog::record([
            'invoice_id' => $context['invoice_id'] ?? null,
            'client_id' => $context['client_id'] ?? null,
            'recipient' => $to,
            'template' => $campaign,
            'provider' => 'aisensy',
            'source' => $source,
            'status' => $status,
            'wamid' => $wamid,
            'error' => $error,
        ]);

        try {
            $res = Http::timeout(25)->acceptJson()->asJson()->post((string) config('aisensy.endpoint'), $payload);
        } catch (\Throwable $e) {
            $journal('failed', null, $e->getMessage());

            return ['ok' => false, 'message' => 'AiSensy send failed: '.$e->getMessage()];
        }

        $json = $res->json();
        $explicitFailure = is_array($json) && array_key_exists('success', $json) && $json['success'] === false;
        if (! $res->successful() || $explicitFailure) {
            $err = self::errorFrom($json, $res->status());
            $journal('failed', null, $err);

            return ['ok' => false, 'message' => 'AiSensy rejected the message: '.$err];
        }

        $wamid = self::idFrom($json);
        $journal('sent', $wamid);

        return [
            'ok' => true,
            'message' => 'WhatsApp sent to +'.$to.' via AiSensy',
            'id' => (string) ($wamid ?? ''),
        ];
    }

    /** Pull a useful error string out of AiSensy's varying response shapes. */
    private static function errorFrom($json, int $status): string
    {
        foreach (['message', 'error', 'description', 'errors'] as $key) {
            $value = is_array($json) ? ($json[$key] ?? null) : null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_array($value)) {
                $first = reset($value);
                if (is_string($first) && $first !== '') {
                    return $first;
                }
                if (is_array($first)) {
                    $nested = $first['message'] ?? $first['error'] ?? null;
                    if (is_string($nested) && $nested !== '') {
                        return $nested;
                    }
                }
            }
        }

        return 'HTTP '.$status;
    }

    private static function idFrom($json): ?string
    {
        if (! is_array($json)) {
            return null;
        }
        foreach (['messageId', 'id'] as $key) {
            if (isset($json[$key]) && is_scalar($json[$key])) {
                return (string) $json[$key];
            }
        }
        $data = $json['data'] ?? null;
        if (is_array($data)) {
            foreach (['messageId', 'id'] as $key) {
                if (isset($data[$key]) && is_scalar($data[$key])) {
                    return (string) $data[$key];
                }
            }
        }

        return null;
    }
}
