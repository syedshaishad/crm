<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use Illuminate\Http\Request;

class SavedViewController extends Controller
{
    /** Views belong to one staff member and one list route. */
    private function own(SavedView $view): SavedView
    {
        abort_unless((int) $view->user_id === (int) auth()->id(), 403);

        return $view;
    }

    private function payload(SavedView $view): array
    {
        return [
            'id' => $view->id,
            'name' => $view->name,
            'query' => $view->query ?: [],
            'is_default' => (bool) $view->is_default,
        ];
    }

    public function index(Request $r)
    {
        $route = trim((string) $r->input('route', ''));
        $views = auth()->user()->savedViews()
            ->when($route !== '', fn ($q) => $q->where('route', $route))
            ->orderBy('name')
            ->get()
            ->map(fn ($v) => $this->payload($v))
            ->all();

        return response()->json(['views' => $views]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:80',
            'route' => 'required|string|max:120',
            'query' => 'present|array',
        ]);
        // Keep the query small: a saved view is a filter, not a data dump.
        $query = array_slice($data['query'], 0, 30);
        $view = auth()->user()->savedViews()->updateOrCreate(
            ['route' => $data['route'], 'name' => $data['name']],
            ['query' => $query]
        );

        return response()->json(['ok' => true, 'view' => $this->payload($view)]);
    }

    public function makeDefault(SavedView $savedView)
    {
        $view = $this->own($savedView);
        auth()->user()->savedViews()->where('route', $view->route)->update(['is_default' => false]);
        $view->update(['is_default' => true]);

        return response()->json(['ok' => true, 'view' => $this->payload($view)]);
    }

    public function destroy(SavedView $savedView)
    {
        $this->own($savedView)->delete();

        return response()->json(['ok' => true]);
    }
}
