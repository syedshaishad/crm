<?php

namespace Modules\Work\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Holiday;

class HolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index()
    {
        return view('holidays.index', ['rows' => Holiday::orderBy('day')->paginate(perPage())]);
    }

    public function store(Request $r)
    {
        $data = $r->validate(['day' => 'required|date|unique:holidays,day', 'name' => 'required|string|max:120']);
        Holiday::create($data);

        return back()->with('ok', 'Holiday marked — payroll skips it automatically.');
    }

    public function destroy(Holiday $holiday)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers manage holidays.');
        }
        $holiday->delete();

        return back()->with('ok', 'Holiday removed.');
    }
}
