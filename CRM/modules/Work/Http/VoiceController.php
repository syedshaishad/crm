<?php

namespace Modules\Work\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Poll;
use Modules\Work\PollVote;
use Modules\Work\Suggestion;

class VoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index()
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $poll = Poll::where('is_active', true)->orderByDesc('month')->first();
        $mine = $poll ? PollVote::where('poll_id', $poll->id)->where('voter_hash', $this->hash($me->id, $poll->id))->exists() : false;

        return view('voice.index', [
            'suggestions' => Suggestion::orderByDesc('id')->paginate(perPage(), ['*'], 'spage'),
            'poll' => $poll,
            'polls' => Poll::withCount('votes')->orderByDesc('month')->limit(12)->get(),
            'mine' => $mine, 'canAll' => $canAll,
        ]);
    }

    /** Anonymous drop-box: no author is ever stored. */
    public function suggest(Request $r)
    {
        $data = $r->validate([
            'body' => 'required|string|min:3|max:2000',
            'mood' => 'nullable|in:idea,praise,gripe,question',
        ]);
        Suggestion::create(['body' => $data['body'], 'mood' => $data['mood'] ?? null, 'status' => 'new']);

        return back()->with('ok', 'Heard — thank you. It stays anonymous.');
    }

    public function review(Request $r, Suggestion $suggestion)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers triage suggestions.');
        }
        $data = $r->validate(['status' => 'required|in:new,reviewed,done']);
        $suggestion->update(['status' => $data['status']]);

        return back()->with('ok', 'Suggestion marked '.$data['status'].'.');
    }

    public function poll(Request $r)
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers run polls.');
        }
        $data = $r->validate(['question' => 'required|string|max:255', 'month' => 'required|date_format:Y-m']);
        Poll::updateOrCreate(['month' => $data['month'].'-01'], ['question' => $data['question'], 'is_active' => true]);

        return back()->with('ok', 'Poll open for '.$data['month'].'.');
    }

    /** One anonymous vote per staffer (hash proves uniqueness, hides identity). */
    public function vote(Request $r, Poll $poll)
    {
        $data = $r->validate(['score' => 'required|integer|min:0|max:10', 'comment' => 'nullable|string|max:500']);
        PollVote::firstOrCreate(
            ['poll_id' => $poll->id, 'voter_hash' => $this->hash(auth()->id(), $poll->id)],
            ['score' => $data['score'], 'comment' => $data['comment'] ?? null]
        );

        return back()->with('ok', 'Vote counted — thank you.');
    }

    protected function hash(int $userId, int $pollId): string
    {
        return hash_hmac('sha256', $userId.':'.$pollId, config('app.key'));
    }
}
