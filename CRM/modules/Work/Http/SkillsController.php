<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Work\Skill;
use Modules\Work\Training;
use Modules\Work\UserSkill;

class SkillsController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    public function index()
    {
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $matrix = UserSkill::with(['user', 'skill'])->orderByDesc('level')->get()->groupBy('skill_id');
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $trainings = Training::with('user')->orderByDesc('completed_on')->limit(50)->get();

        return view('skills.index', [
            'skills' => Skill::orderBy('name')->get(), 'matrix' => $matrix,
            'users' => $users, 'trainings' => $trainings, 'canAll' => $canAll,
        ]);
    }

    public function storeSkill(Request $r)
    {
        $this->authorizeManager();
        $data = $r->validate(['name' => 'required|string|max:120|unique:skills,name']);
        Skill::create($data);

        return back()->with('ok', 'Skill added to the matrix.');
    }

    public function destroySkill(Skill $skill)
    {
        $this->authorizeManager();
        $skill->delete();

        return back()->with('ok', 'Skill removed.');
    }

    public function rate(Request $r)
    {
        $this->authorizeManager();
        $data = $r->validate([
            'user_id' => 'required|exists:users,id', 'skill_id' => 'required|exists:skills,id',
            'level' => 'required|integer|min:1|max:5',
            'certified' => 'nullable|boolean', 'cert_expires_on' => 'nullable|date|after:today',
        ]);
        UserSkill::updateOrCreate(
            ['user_id' => $data['user_id'], 'skill_id' => $data['skill_id']],
            ['level' => $data['level'], 'certified' => (bool) ($data['certified'] ?? false), 'cert_expires_on' => $data['cert_expires_on'] ?? null]
        );

        return back()->with('ok', 'Rating saved.');
    }

    public function unrate(UserSkill $userSkill)
    {
        $this->authorizeManager();
        $userSkill->delete();

        return back()->with('ok', 'Rating removed.');
    }

    public function train(Request $r)
    {
        $this->authorizeManager();
        $data = $r->validate([
            'user_id' => 'required|exists:users,id', 'title' => 'required|string|max:200',
            'completed_on' => 'required|date|before_or_equal:today',
            'valid_till' => 'nullable|date|after:completed_on', 'note' => 'nullable|string|max:255',
        ]);
        Training::create($data);

        return back()->with('ok', 'Training recorded.');
    }

    protected function authorizeManager(): void
    {
        $me = auth()->user();
        if (! ($me->isAdmin() || $me->isManager())) {
            abort(403, 'Only managers maintain the skills matrix.');
        }
    }
}
