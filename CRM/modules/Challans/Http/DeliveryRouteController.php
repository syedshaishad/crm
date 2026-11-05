<?php

namespace Modules\Challans\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Challans\DeliveryChallan;
use Modules\Challans\DeliveryRoute;
use Modules\Challans\DeliveryRouteStop;

class DeliveryRouteController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }

    public function index(Request $r)
    {
        $q = DeliveryRoute::withCount(['stops', 'stops as stops_done_count' => fn ($w) => $w->whereNotNull('done_at')])
            ->orderByDesc('route_date')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('vehicle_no', 'like', $s)->orWhere('driver', 'like', $s));
        }
        if ($r->filled('status') && in_array($r->status, ['planned', 'on_route', 'done'], true)) {
            $q->where('status', $r->status);
        }
        $stats = [
            'total' => DeliveryRoute::count(),
            'planned' => DeliveryRoute::where('status', 'planned')->count(),
            'on_route' => DeliveryRoute::where('status', 'on_route')->count(),
            'done' => DeliveryRoute::where('status', 'done')->count(),
            'today' => DeliveryRoute::whereDate('route_date', now()->toDateString())->count(),
        ];

        return view('routes.index', ['routes' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status, 'stats' => $stats]);
    }

    public function create()
    {
        $open = DeliveryChallan::with('client')->where('status', 'issued')->orderBy('challan_date')->limit(200)->get();

        return view('routes.form', ['route' => new DeliveryRoute(['route_date' => now()->toDateString()]), 'open' => $open]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'route_date' => 'required|date', 'vehicle_no' => 'nullable|string|max:40',
            'driver' => 'nullable|string|max:120', 'notes' => 'nullable|string',
            'challans' => 'required|array|min:1', 'challans.*' => 'exists:delivery_challans,id',
        ]);
        $route = DeliveryRoute::create([
            'number' => 'RT-'.str_pad((string) ((DeliveryRoute::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
            'route_date' => $data['route_date'], 'vehicle_no' => $data['vehicle_no'] ?? null,
            'driver' => $data['driver'] ?? null, 'notes' => $data['notes'] ?? null,
            'status' => 'planned', 'created_by' => auth()->id(),
        ]);
        $pos = 0;
        foreach (collect($data['challans'])->unique() as $cid) {
            $route->stops()->create(['challan_id' => $cid, 'position' => $pos++]);
        }

        return redirect()->route('routes.show', $route)->with('ok', 'Route planned with '.$pos.' stops.');
    }

    public function show(DeliveryRoute $route)
    {
        $route->load(['stops.challan.client']);

        return view('routes.show', ['route' => $route, 'progress' => $route->progress()]);
    }

    public function move(Request $r, DeliveryRoute $route, DeliveryRouteStop $stop)
    {
        abort_unless($stop->route_id === $route->id, 404);
        $data = $r->validate(['dir' => 'required|in:up,down']);
        $ordered = $route->stops()->orderBy('position')->pluck('id')->values();
        $i = $ordered->search($stop->id);
        $j = $data['dir'] === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ordered[$j])) {
            $a = $route->stops()->find($ordered[$i]);
            $b = $route->stops()->find($ordered[$j]);
            [$pa, $pb] = [$a->position, $b->position];
            $a->update(['position' => $pb]);
            $b->update(['position' => $pa]);
        }

        return back();
    }

    public function toggle(DeliveryRoute $route, DeliveryRouteStop $stop)
    {
        abort_unless($stop->route_id === $route->id, 404);
        $stop->update(['done_at' => $stop->done_at ? null : now()]);
        if ($route->stops()->whereNull('done_at')->count() === 0) {
            $route->update(['status' => 'done']);
        } elseif ($route->status === 'done') {
            $route->update(['status' => 'on_route']);
        } elseif ($route->status === 'planned' && $stop->done_at) {
            $route->update(['status' => 'on_route']);
        }

        return back();
    }

    public function destroy(DeliveryRoute $route)
    {
        $route->delete();

        return back()->with('ok', 'Route moved to Trash.');
    }
}
