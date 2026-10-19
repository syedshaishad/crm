<?php

namespace Modules\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clients\Client;
use Modules\Documents\Document;
use Modules\Expenses\Expense;
use Modules\Tasks\Task;
use Modules\Tasks\TaskDependency;
use Modules\Tasks\TaskTime;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = ['client_id', 'name', 'description', 'status', 'start_date', 'due_date', 'budget'];

    protected $casts = ['start_date' => 'date', 'due_date' => 'date'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'linkable');
    }

    public function feedback()
    {
        return $this->hasMany(ProjectFeedback::class);
    }

    public function csat(): ?float
    {
        $n = $this->feedback()->count();
        if ($n === 0) {
            return null;
        }

        return round($this->feedback()->avg('score'), 1);
    }

    public function costTotal(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    /** Labor cost: logged hours × each task's rate. */
    public function laborTotal(): float
    {
        return round($this->tasks()->with('times')->get()->sum(fn ($t) => $t->laborCost()), 2);
    }

    /** Approved costs grouped by category (for the finance view). */
    public function costByCategory(): array
    {
        return $this->expenses()->where('status', 'approved')
            ->selectRaw('COALESCE(category,\'other\') as category, SUM(amount) as t')
            ->groupBy('category')->orderByDesc('t')->pluck('t', 'category')->all();
    }

    /** Estimate to complete: open tasks' remaining hours × their rates. */
    public function remainingCost(): float
    {
        $total = 0;
        foreach ($this->tasks()->with('times')->whereNotIn('status', ['done'])->get() as $t) {
            $left = max(0, (int) $t->estimate_minutes - (int) $t->times->sum('minutes'));
            $total += $left / 60 * $t->rate();
        }

        return round($total, 2);
    }

    /** Forecast profit if the rest goes to estimate. */
    public function forecastProfit(): float
    {
        $spent = (float) $this->expenses()->where('status', 'approved')->sum('amount');

        return round($this->billedTotal() - $spent - $this->laborTotal() - $this->remainingCost(), 2);
    }

    public function estimateMinutes(): int
    {
        return (int) $this->tasks()->sum('estimate_minutes');
    }

    public function workedMinutes(): int
    {
        return (int) TaskTime::whereIn('task_id', $this->tasks()->pluck('id'))->sum('minutes');
    }

    /** Longest dependency chain (the critical path): task ids in order. */
    public function criticalPath(): array
    {
        $deps = TaskDependency::whereIn('task_id', $this->tasks()->pluck('id'))->get()->groupBy('task_id');
        $memo = [];
        $depth = function ($id) use (&$depth, &$memo, $deps) {
            if (isset($memo[$id])) {
                return $memo[$id];
            }
            $max = 0;
            foreach ($deps->get($id, collect()) as $d) {
                $max = max($max, $depth((int) $d->depends_on_task_id));
            }

            return $memo[$id] = $max + 1;
        };
        $best = [];
        $bestLen = 0;
        foreach ($this->tasks()->pluck('id') as $id) {
            // rebuild one chain by walking deepest blockers
            $chain = [(int) $id];
            $cur = (int) $id;
            while (($deps->get($cur, collect())->sortByDesc(fn ($d) => $depth((int) $d->depends_on_task_id))->first()) !== null) {
                $next = $deps->get($cur)->sortByDesc(fn ($d) => $depth((int) $d->depends_on_task_id))->first();
                $cur = (int) $next->depends_on_task_id;
                if (in_array($cur, $chain, true)) {
                    break;
                }
                $chain[] = $cur;
            }
            if (count($chain) > $bestLen) {
                $bestLen = count($chain);
                $best = $chain;
            }
        }

        return $bestLen > 1 ? $best : [];
    }

    /** Unbilled time entries with their task context. */
    public function unbilledTime()
    {
        return TaskTime::with(['task', 'user'])
            ->whereIn('task_id', $this->tasks()->pluck('id'))->whereNull('invoice_id')
            ->orderBy('worked_on')->orderBy('id')->get();
    }

    public function invoices()
    {
        return $this->hasMany(\Modules\Invoices\Invoice::class);
    }

    /** Filed revenue (drafts + cancelled never count as billed). */
    public function billedTotal(): float
    {
        return (float) $this->invoices()->whereIn('status', ['sent', 'partial', 'paid', 'overdue'])->sum('total');
    }

    public function profit(): float
    {
        return round($this->billedTotal() - $this->costTotal(), 2);
    }
}
