<?php

namespace Modules\Expenses;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Projects\Project;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = ['number', 'project_id', 'title', 'category', 'amount', 'spent_on', 'vendor', 'method', 'reference', 'receipt_path', 'notes', 'created_by', 'status', 'approved_by', 'approved_on'];

    protected $casts = ['spent_on' => 'date', 'approved_on' => 'date'];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
