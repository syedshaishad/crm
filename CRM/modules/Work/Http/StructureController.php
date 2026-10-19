<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\SalaryStructure;

class StructureController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index()
    {
        return view('structures.index', [
            'rows' => SalaryStructure::with('user')->orderByDesc('effective_from')->paginate(perPage()),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => 'required|exists:users,id',
            'effective_from' => 'required|date',
            'basic' => 'required|numeric|min:0', 'hra' => 'nullable|numeric|min:0', 'allowances' => 'nullable|numeric|min:0',
        ]);
        SalaryStructure::create([...$data, 'hra' => $data['hra'] ?? 0, 'allowances' => $data['allowances'] ?? 0, 'is_active' => true]);

        return back()->with('ok', 'Salary structure saved — applies from '.d($data['effective_from']).'.');
    }

    public function destroy(SalaryStructure $structure)
    {
        $structure->delete();

        return back()->with('ok', 'Structure removed.');
    }
}
