<?php

namespace Modules\Clients;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Documents\Document;
use Modules\Invoices\Invoice;
use Modules\Projects\Project;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'company', 'email', 'phone', 'address', 'gstin', 'pan', 'state', 'status', 'credit_limit', 'loyalty_balance', 'currency', 'notes', 'custom_fields', 'birthday'];

    protected $casts = ['credit_limit' => 'float', 'loyalty_balance' => 'float', 'custom_fields' => 'array', 'birthday' => 'date'];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function tasks()
    {
        return $this->hasMany(\Modules\Tasks\Task::class);
    }

    public function proposals()
    {
        return $this->hasMany(\Modules\Proposals\Proposal::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'linkable');
    }

    public function portalUsers()
    {
        return $this->hasMany(\App\Models\ClientUser::class);
    }
}
