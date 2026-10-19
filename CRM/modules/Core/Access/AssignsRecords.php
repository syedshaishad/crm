<?php

namespace Modules\Core\Access;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Shared assignee rules for client-facing documents (invoices, proposals):
 * admins assign anyone, managers assign active staff (or themselves),
 * everyone else keeps their own work.
 */
trait AssignsRecords
{
    protected function staffOptions()
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']);
    }

    protected function resolveAssignee(Request $r, $actor, $fallback)
    {
        if (! $actor->canManageInvoices()) {
            return $fallback;
        }
        $id = $r->input('assigned_to');
        if (! $id) {
            return null;
        }
        $target = User::find($id);
        if (! $target || ! $target->is_active) {
            return $fallback;
        }
        if ($actor->isManager() && $target->role !== 'staff' && $target->id !== $actor->id) {
            return $fallback;
        }

        return $target->id;
    }
}
