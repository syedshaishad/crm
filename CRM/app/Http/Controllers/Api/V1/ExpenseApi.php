<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ExpenseResource;
use Illuminate\Http\Request;
use Modules\Expenses\Expense;
use Modules\Settings\SettingStore;

class ExpenseApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Expense::with('project')->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['number', 'title', 'vendor', 'category'], ['category' => 'category']);

        return ExpenseResource::collection($rows);
    }

    public function show(Expense $expense)
    {
        return new ExpenseResource($expense->load('project'));
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'title' => 'required|string', 'category' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'required|numeric|min:0', 'spent_on' => 'required|date',
            'vendor' => 'nullable|string', 'method' => 'nullable|string|max:20',
            'reference' => 'nullable|string|max:100', 'notes' => 'nullable|string',
        ]);
        $prefix = $s->get('expense.prefix', 'EXP-');
        $data['number'] = $prefix.str_pad((string) ((Expense::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        $data['created_by'] = $r->user()->id;
        if ($r->user()->isAdmin() || $r->user()->isManager()) {
            $data['status'] = 'approved';
            $data['approved_by'] = $r->user()->id;
            $data['approved_on'] = now()->toDateString();
        } else {
            $data['status'] = 'pending';
        }

        return (new ExpenseResource(Expense::create($data)->load('project')))->response()->setStatusCode(201);
    }

    public function update(Request $r, Expense $expense)
    {
        $data = $r->validate([
            'title' => 'sometimes|required|string', 'category' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'amount' => 'sometimes|required|numeric|min:0', 'spent_on' => 'sometimes|required|date',
            'vendor' => 'nullable|string', 'method' => 'nullable|string|max:20',
            'reference' => 'nullable|string|max:100', 'notes' => 'nullable|string',
        ]);
        $expense->update($data);

        return new ExpenseResource($expense->fresh()->load('project'));
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}

