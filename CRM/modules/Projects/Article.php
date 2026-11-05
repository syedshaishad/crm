<?php

namespace Modules\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = ['title', 'category', 'body', 'pinned', 'created_by'];

    protected $casts = ['pinned' => 'boolean'];

    public static function categories(): array
    {
        return ['SOP', 'Guide', 'Checklist', 'Policy', 'Template', 'Notes'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
