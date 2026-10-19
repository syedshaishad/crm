<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index()
    {
        return view('settings.api', [
            'tokens' => auth()->user()->tokens()->orderByDesc('id')->get(),
            'webhooks' => \Modules\Core\Webhooks\Webhook::withCount(['deliveries as fails' => fn ($q) => $q->where('status', 'failed')])->orderByDesc('id')->get(),
            'events' => \Modules\Core\Webhooks\Webhook::events(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:60',
            'scope' => 'nullable|in:full,readonly',
        ]);
        $abilities = ($data['scope'] ?? 'full') === 'readonly' ? ['read'] : ['*'];
        $token = $r->user()->createToken($data['name'], $abilities);

        return back()->with('ok', 'Token created — copy it now, it will not be shown again.')
            ->with('plain_token', $token->plainTextToken);
    }

    public function destroy(Request $r, $id)
    {
        $token = $r->user()->tokens()->where('id', $id)->first();
        if (! $token) {
            return back()->withErrors(['token' => 'Token not found.']);
        }
        $token->delete();

        return back()->with('ok', 'Token revoked.');
    }
}
