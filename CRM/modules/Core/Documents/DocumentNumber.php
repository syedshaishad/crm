<?php

namespace Modules\Core\Documents;

/**
 * Behavior shared by every client-facing document (Invoice, Proposal):
 * staff-visible scoping — managers/admins see everything, staff only their own.
 */
trait DocumentNumber
{
    /** Staff see only their own documents; managers/admins see everything. */
    public function scopeVisibleTo($query, $user)
    {
        if ($user && method_exists($user, 'canManageInvoices') && $user->canManageInvoices()) {
            return $query;
        }
        $id = $user ? $user->id : 0;

        return $query->where(fn ($w) => $w->where('assigned_to', $id)->orWhere('created_by', $id));
    }

    /** Whether this document is visible to the given user. */
    public function isVisibleTo($user): bool
    {
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'canManageInvoices') && $user->canManageInvoices()) {
            return true;
        }

        return $this->assigned_to === $user->id || $this->created_by === $user->id;
    }
}
