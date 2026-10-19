<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

abstract class BaseApi extends Controller
{
    /** Apply the common ?q search + ?status filter + per_page sizing. */
    protected function applySearch(Request $r, $q, array $searchFields, array $filterFields = [])
    {
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(function ($w) use ($s, $searchFields) {
                foreach ($searchFields as $i => $f) {
                    if (str_contains($f, '.')) {
                        [$rel, $col] = explode('.', $f, 2);
                        $i === 0 ? $w->whereHas($rel, fn ($x) => $x->where($col, 'like', $s)) : $w->orWhereHas($rel, fn ($x) => $x->where($col, 'like', $s));
                    } else {
                        $i === 0 ? $w->where($f, 'like', $s) : $w->orWhere($f, 'like', $s);
                    }
                }
            });
        }
        foreach ($filterFields as $param => $column) {
            if ($r->filled($param)) {
                $q->where($column, $r->{$param});
            }
        }

        return $q->paginate(min(100, max(5, (int) ($r->per_page ?? perPage()))));
    }
}
