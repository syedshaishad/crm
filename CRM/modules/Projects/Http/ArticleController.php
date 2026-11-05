<?php

namespace Modules\Projects\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Projects\Article;

class ArticleController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $q = Article::with('author')->orderByDesc('pinned')->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('title', 'like', $s)->orWhere('body', 'like', $s));
        }
        if ($r->filled('category')) {
            $q->where('category', $r->category);
        }

        $counts = Article::query()
            ->selectRaw('category, COUNT(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');
        $kinds = collect(Article::categories())
            ->merge($counts->keys())
            ->unique()
            ->values();

        return view('knowledge.index', [
            'rows' => $q->paginate(perPage())->withQueryString(),
            'q' => $r->q,
            'category' => $r->category,
            'counts' => $counts,
            'kinds' => $kinds,
            'total' => (int) $counts->sum(),
            'pinned' => Article::query()->where('pinned', true)->orderByDesc('updated_at')->limit(4)->get(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', Article::categories()),
            'body' => 'required|string',
        ]);
        $a = Article::create([...$data, 'created_by' => auth()->id()]);

        return redirect()->route('knowledge.show', $a)->with('ok', 'Article published.');
    }

    public function show(Article $article)
    {
        return view('knowledge.show', compact('article'));
    }

    public function update(Request $r, Article $article)
    {
        $data = $r->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', Article::categories()),
            'body' => 'required|string',
        ]);
        $article->update($data);

        return back()->with('ok', 'Article updated.');
    }

    public function pin(Article $article)
    {
        $article->update(['pinned' => ! $article->pinned]);

        return back()->with('ok', $article->pinned ? 'Pinned to the top.' : 'Unpinned.');
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return redirect()->route('knowledge.index')->with('ok', 'Article deleted.');
    }
}
