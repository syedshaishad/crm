<?php

namespace Modules\Work;

use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    protected $fillable = ['question', 'month', 'is_active'];

    protected $casts = ['month' => 'date', 'is_active' => 'boolean'];

    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }

    /** eNPS = % promoters (9-10) minus % detractors (0-6). */
    public function enps(): ?int
    {
        $n = $this->votes()->count();
        if ($n === 0) {
            return null;
        }
        $pro = $this->votes()->where('score', '>=', 9)->count() / $n * 100;
        $det = $this->votes()->where('score', '<=', 6)->count() / $n * 100;

        return (int) round($pro - $det);
    }

    public function buckets(): array
    {
        return [
            'promoters' => $this->votes()->where('score', '>=', 9)->count(),
            'passive' => $this->votes()->whereBetween('score', [7, 8])->count(),
            'detractors' => $this->votes()->where('score', '<=', 6)->count(),
        ];
    }
}
