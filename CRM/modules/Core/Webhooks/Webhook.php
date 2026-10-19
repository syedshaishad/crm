<?php

namespace Modules\Core\Webhooks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Webhook extends Model
{
    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'events' => 'array'];

    public static function events(): array
    {
        return [
            'payment.received' => 'Payment received',
            'invoice.sent' => 'Invoice sent',
            'invoice.paid' => 'Invoice paid in full',
            'proposal.accepted' => 'Proposal accepted',
            'proposal.declined' => 'Proposal declined',
            'lead.won' => 'Lead won',
        ];
    }

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class)->orderByDesc('id');
    }

    /** Fire an event to every active subscriber (signed, logged, best-effort). */
    public static function fire(string $event, array $payload): void
    {
        $hooks = self::where('is_active', true)->get()->filter(fn ($h) => in_array($event, $h->events ?? [], true));
        if ($hooks->isEmpty()) {
            return;
        }
        $body = json_encode(['event' => $event, 'at' => now()->toIso8601String(), 'data' => $payload]);
        foreach ($hooks as $h) {
            $sig = $h->secret ? hash_hmac('sha256', $body, $h->secret) : null;
            try {
                $res = Http::timeout(15)->withHeaders(array_filter(['X-CRM-Event' => $event, 'X-CRM-Signature' => $sig ? 'sha256='.$sig : null]))->withBody($body, 'application/json')->post($h->url);
                $h->deliveries()->create([
                    'event' => $event,
                    'status' => $res->successful() ? 'sent' : 'failed',
                    'http_code' => $res->status(),
                    'error' => $res->successful() ? null : substr('HTTP '.$res->status().': '.$res->body(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                $h->deliveries()->create(['event' => $event, 'status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
            }
        }
    }
}
