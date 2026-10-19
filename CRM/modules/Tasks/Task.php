<?php

namespace Modules\Tasks;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Projects\Project;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = ['project_id', 'client_id', 'assigned_to', 'title', 'description', 'status', 'priority', 'estimate_minutes', 'hourly_rate', 'start_date', 'baseline_due', 'recurs', 'recurs_next', 'public_token', 'due_date', 'handover_note', 'handed_over_to', 'handed_over_at', 'handed_over_by'];

    protected $casts = ['due_date' => 'date', 'start_date' => 'date', 'baseline_due' => 'date', 'recurs_next' => 'date', 'handed_over_at' => 'datetime'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(\Modules\Clients\Client::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class)->orderBy('id');
    }

    public function times()
    {
        return $this->hasMany(TaskTime::class)->orderBy('worked_on')->orderBy('id');
    }

    public function checklist()
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('position')->orderBy('id');
    }

    public function blockers()
    {
        return $this->hasMany(TaskDependency::class);
    }

    /** Incomplete dependencies blocking this task. */
    public function openBlockers()
    {
        return Task::whereIn('id', $this->blockers()->pluck('depends_on_task_id'))->whereNotIn('status', ['done'])->get();
    }

    public function isBlocked(): bool
    {
        return $this->openBlockers()->isNotEmpty();
    }

    /** Gantt bar: start (or derived) through due date. */
    public function bar(string $min, string $max): ?array
    {
        if (! $this->due_date) {
            return null;
        }
        $end = $this->due_date->toDateString();
        $days = max(1, (int) ceil(($this->estimate_minutes ?: 60) / 480));
        $start = $this->start_date ? $this->start_date->toDateString() : \Carbon\Carbon::parse($end)->subDays($days - 1)->toDateString();
        $span = max(1, (int) \Carbon\Carbon::parse($min)->diffInDays(\Carbon\Carbon::parse($max)) + 1);
        $off = max(0, (int) \Carbon\Carbon::parse($min)->diffInDays(\Carbon\Carbon::parse($start), false));
        $len = max(1, (int) \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1);

        return ['off' => round($off / $span * 100, 2), 'len' => round($len / $span * 100, 2)];
    }

    /** Effective hourly rate: task override, else the global default. */
    public function rate(): float
    {
        if ($this->hourly_rate !== null) {
            return (float) $this->hourly_rate;
        }

        return (float) setting('projects.hourly_rate', 0);
    }

    public function minutesWorked(): int
    {
        return (int) $this->times()->sum('minutes');
    }

    public function laborCost(): float
    {
        return round($this->minutesWorked() / 60 * $this->rate(), 2);
    }

    /** Site-proof photo attached? (image file on any comment) */
    public function hasPhoto(): bool
    {
        foreach ($this->comments as $c) {
            if ($c->file_path && preg_match('/\.(jpe?g|png|gif|webp|heic)$/i', $c->file_path)) {
                return true;
            }
        }

        return false;
    }
}
