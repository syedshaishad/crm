<?php

namespace Modules\Tasks;

use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    protected $fillable = ['name', 'title', 'description', 'priority', 'estimate_minutes', 'checklist'];

    /** One line per row of the stored checklist text. */
    public function lines(): array
    {
        return collect(preg_split('/\r?\n/', (string) ($this->checklist ?? '')))->map(fn ($l) => trim($l))->filter()->values()->all();
    }
}
