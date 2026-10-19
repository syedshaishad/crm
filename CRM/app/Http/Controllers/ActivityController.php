<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $r)
    {
        $q = Activity::with('user')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('label', 'like', $s)->orWhere('description', 'like', $s));
        }
        if ($r->filled('action')) {
            $q->where('action', $r->action);
        }
        if ($r->filled('user_id')) {
            $q->where('user_id', $r->user_id);
        }

        return view('activity.index', [
            'items' => $q->paginate(perPage())->withQueryString(),
            'q' => $r->q, 'action' => $r->action, 'user_id' => $r->user_id,
            'actions' => Activity::distinct()->pluck('action'),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
