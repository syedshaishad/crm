<?php

namespace Modules\Expenses;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Recurring\Schedule;
use Modules\Projects\Project;

class RecurringExpenseProfile extends Model
{
    protected $fillable = ['title', 'category', 'amount', 'vendor', 'method', 'project_id', 'notes', 'frequency', 'next_run_on', 'last_run_on', 'is_active', 'created_by'];

    protected $casts = ['next_run_on' => 'date', 'last_run_on' => 'date', 'is_active' => 'boolean'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public static function frequencies(): array
    {
        return Schedule::frequencies();
    }

    public function advance(): void
    {
        $this->update([
            'last_run_on' => $this->next_run_on,
            'next_run_on' => Schedule::next($this->next_run_on, $this->frequency),
        ]);
    }
}
