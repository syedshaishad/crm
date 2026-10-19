<?php

namespace Modules\Tasks;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplate extends Model
{
    protected $fillable = ['name', 'lines'];

    public function lines(): array
    {
        return collect(preg_split('/\r?\n/', (string) ($this->lines ?? '')))->map(fn ($l) => trim($l))->filter()->values()->all();
    }
}
