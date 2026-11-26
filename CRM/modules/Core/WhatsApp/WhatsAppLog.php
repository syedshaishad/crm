<?php

namespace Modules\Core\WhatsApp;

use Illuminate\Support\Facades\DB;

/**
 * One journal row per WhatsApp attempt, regardless of provider. Keeping the
 * insert here means Meta and AiSensy log the same columns and the reminders
 * audit page stays uniform.
 */
class WhatsAppLog
{
    /**
     * @param  array{invoice_id?: int|null, client_id?: int|null, recipient?: string|null,
     *               template?: string|null, provider?: string, source?: string,
     *               status?: string, wamid?: string|null, error?: string|null}  $data
     */
    public static function record(array $data): void
    {
        DB::table('whatsapp_logs')->insert([
            'invoice_id' => $data['invoice_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'recipient' => isset($data['recipient']) ? mb_substr((string) $data['recipient'], 0, 30) : null,
            'template' => isset($data['template']) ? mb_substr((string) $data['template'], 0, 100) : null,
            'provider' => in_array($data['provider'] ?? 'meta', ['meta', 'aisensy'], true) ? ($data['provider'] ?? 'meta') : 'meta',
            'source' => mb_substr((string) ($data['source'] ?? 'manual'), 0, 20),
            'status' => $data['status'] ?? 'sent',
            'wamid' => isset($data['wamid']) ? mb_substr((string) $data['wamid'], 0, 255) : null,
            'error' => isset($data['error']) ? mb_substr((string) $data['error'], 0, 500) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
