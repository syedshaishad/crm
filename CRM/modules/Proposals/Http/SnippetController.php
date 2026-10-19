<?php

namespace Modules\Proposals\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Proposals\Snippet;

class SnippetController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function index()
    {
        return view('settings.snippets', ['snippets' => Snippet::orderBy('kind')->orderBy('title')->get()]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:120',
            'kind' => 'required|in:'.implode(',', array_keys(Snippet::kinds())),
            'body' => 'required|string|max:5000',
        ]);
        Snippet::create($data);

        return back()->with('ok', 'Snippet saved.');
    }

    public function update(Request $r, Snippet $snippet)
    {
        $data = $r->validate([
            'title' => 'required|string|max:120',
            'kind' => 'required|in:'.implode(',', array_keys(Snippet::kinds())),
            'body' => 'required|string|max:5000',
        ]);
        $snippet->update($data);

        return back()->with('ok', 'Snippet updated.');
    }

    public function destroy(Snippet $snippet)
    {
        $snippet->delete();

        return back()->with('ok', 'Snippet deleted.');
    }
}
