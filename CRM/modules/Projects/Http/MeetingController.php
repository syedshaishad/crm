<?php

namespace Modules\Projects\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Projects\Meeting;
use Modules\Projects\Project;

class MeetingController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index(Request $r)
    {
        $upcoming = Meeting::with('project')->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(50)->get();
        $past = Meeting::with('project')->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at')->paginate(perPage(), ['*'], 'ppage');
        $staff = User::orderBy('name')->get(['id', 'name', 'avatar_path', 'is_active']);

        return view('meetings.index', [
            'upcoming' => $upcoming, 'past' => $past,
            'users' => $staff->where('is_active', true)->values(),
            'staff' => $staff->keyBy('id'),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'attendees' => 'nullable|array', 'attendees.*' => 'integer|exists:users,id',
            'agenda' => 'nullable|string', 'project_id' => 'nullable|exists:projects,id',
        ]);
        Meeting::create([
            'title' => $data['title'], 'starts_at' => $data['starts_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 30,
            'attendees' => array_values(array_unique(array_map('intval', $data['attendees'] ?? []))),
            'agenda' => $data['agenda'] ?? null, 'project_id' => $data['project_id'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return back()->with('ok', 'Meeting scheduled.');
    }

    /** Fill in minutes after the call (agenda becomes record). */
    public function notes(Request $r, Meeting $meeting)
    {
        $data = $r->validate(['notes' => 'nullable|string']);
        $meeting->update(['notes' => $data['notes'] ?? null]);

        return back()->with('ok', 'Minutes saved.');
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();

        return back()->with('ok', 'Meeting removed.');
    }
}
