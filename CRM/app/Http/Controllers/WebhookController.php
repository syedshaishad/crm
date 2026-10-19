<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Core\Webhooks\Webhook;

class WebhookController extends Controller
{
    public function index()
    {
        return view('settings.api', [
            'tokens' => auth()->user()->tokens()->orderByDesc('id')->get(),
            'webhooks' => Webhook::withCount(['deliveries as fails' => fn ($q) => $q->where('status', 'failed')])->orderByDesc('id')->get(),
            'events' => Webhook::events(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120', 'url' => 'required|url|max:500',
            'secret' => 'nullable|string|max:64', 'events' => 'required|array|min:1',
            'events.*' => 'in:'.implode(',', array_keys(Webhook::events())),
        ]);
        Webhook::create([...$data, 'is_active' => true]);

        return back()->with('ok', 'Webhook added — fires on the next matching event.');
    }

    public function toggle(Webhook $webhook)
    {
        $webhook->update(['is_active' => ! $webhook->is_active]);

        return back()->with('ok', $webhook->is_active ? 'Webhook resumed.' : 'Webhook paused.');
    }

    public function destroy(Webhook $webhook)
    {
        $webhook->delete();

        return back()->with('ok', 'Webhook removed.');
    }

    /** Fire a test ping so the receiving end can be verified immediately. */
    public function ping(Webhook $webhook)
    {
        $body = json_encode(['event' => 'ping', 'at' => now()->toIso8601String(), 'data' => ['webhook' => $webhook->name]]);
        $sig = $webhook->secret ? hash_hmac('sha256', $body, $webhook->secret) : null;
        try {
            $res = Http::timeout(15)->withHeaders(array_filter(['X-CRM-Event' => 'ping', 'X-CRM-Signature' => $sig ? 'sha256='.$sig : null]))->withBody($body, 'application/json')->post($webhook->url);
            $last = $webhook->deliveries()->create(['event' => 'ping', 'status' => $res->successful() ? 'sent' : 'failed', 'http_code' => $res->status(), 'error' => $res->successful() ? null : substr('HTTP '.$res->status().': '.$res->body(), 0, 500)]);
        } catch (\Throwable $e) {
            $last = $webhook->deliveries()->create(['event' => 'ping', 'status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
        }

        return back()->with($last->status === 'sent' ? 'ok' : 'warn', 'Ping '.($last->status === 'sent' ? 'delivered (HTTP '.$last->http_code.').' : 'failed: '.($last->error ?? 'unknown').' — check the URL.'));
    }

    public function deliveries(Webhook $webhook)
    {
        return view('settings.webhook-deliveries', [
            'webhook' => $webhook,
            'rows' => $webhook->deliveries()->orderByDesc('id')->paginate(perPage()),
        ]);
    }
}
