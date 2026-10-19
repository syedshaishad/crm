<?php

namespace App\Observers;

/**
 * Automatic who-did-what log for the main records. Quiet by design:
 * creates/deletes/restores always, updates only when status/stage flips
 * (so invoice recalculations don't spam the log).
 */
class ActivityObserver
{
    public function created($m)
    {
        activity('created', $m);
    }

    public function updated($m)
    {
        try {
            if ($m->wasChanged(['status', 'stage'])) {
                $field = $m->isDirty('status') || isset($m->getChanges()['status']) ? 'status' : 'stage';
                $changes = $m->getChanges();
                activity('status', $m, ($m->getOriginal($field) ?? '—').' → '.($changes[$field] ?? '—'));
            }
        } catch (\Throwable $e) {
        }
    }

    public function deleted($m)
    {
        try {
            if (method_exists($m, 'isForceDeleting') && $m->isForceDeleting()) {
                return;
            } // forceDeleted() covers it
            activity($m->trashed() ? 'trashed' : 'deleted', $m);
        } catch (\Throwable $e) {
            activity('deleted', $m);
        }
    }

    public function restored($m)
    {
        activity('restored', $m);
    }

    public function forceDeleted($m)
    {
        activity('deleted', $m, 'permanently deleted');
    }
}
