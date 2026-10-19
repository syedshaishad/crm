<?php

namespace Modules\Billing\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Billing\Asset;

class AssetController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index()
    {
        $assets = Asset::with('holder')->orderBy('name')->get();
        $total = ['cost' => $assets->sum('cost'), 'book' => $assets->sum(fn ($a) => $a->bookValue())];
        $users = \App\Models\User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('assets.index', compact('assets', 'total', 'users'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:160',
            'bought_on' => 'required|date|before_or_equal:today',
            'cost' => 'required|numeric|min:0',
            'life_years' => 'required|integer|min:1|max:30',
            'location' => 'nullable|string|max:160', 'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);
        Asset::create($data + ['created_by' => auth()->id()]);

        return back()->with('ok', 'Asset registered.');
    }

    /** Hand an asset to a staffer (custody) or take it back. */
    public function assign(Request $r, Asset $asset)
    {
        $data = $r->validate(['assigned_to' => 'nullable|exists:users,id']);
        $asset->update(['assigned_to' => $data['assigned_to'] ?? null]);

        return back()->with('ok', $asset->assigned_to ? 'Asset handed to '.$asset->holder->name.'.' : 'Asset returned to office.');
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return back()->with('ok', 'Asset moved to Trash.');
    }
}
