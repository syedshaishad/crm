<?php

namespace Modules\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WorkflowRule extends Model
{
    protected $fillable = ['name', 'trigger', 'after_days', 'action', 'reassign_to', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function triggers(): array
    {
        return [
            'task.overdue' => 'Task overdue',
            'invoice.overdue' => 'Invoice overdue',
            'lead.stale' => 'Lead gone stale',
            'proposal.expiring' => 'Proposal expiring soon',
        ];
    }

    public static function actions(): array
    {
        return [
            'notify' => 'Notify admins + managers',
            'bump' => 'Raise priority one step',
            'reassign' => 'Reassign to someone',
        ];
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'reassign_to');
    }
}
