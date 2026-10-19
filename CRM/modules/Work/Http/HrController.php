<?php

namespace Modules\Work\Http;

use App\Models\User;
use Illuminate\Routing\Controller;
use Modules\Work\Appraisal;
use Modules\Work\Attendance;
use Modules\Work\Bonus;
use Modules\Work\ExitRecord;
use Modules\Work\FieldVisit;
use Modules\Work\Leave;
use Modules\Work\OvertimeEntry;
use Modules\Work\Payroll;
use Modules\Work\Reimbursement;
use Modules\Work\StaffLoan;
use Modules\Work\UserSkill;

class HrController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:work');
    }

    /** HR home: people, approvals, pay and growth at a glance. */
    public function index()
    {
        $today = now()->toDateString();
        $me = auth()->user();
        $canAll = $me->isAdmin() || $me->isManager();
        $scope = fn ($q) => $canAll ? $q : $q->where('user_id', $me->id);
        $present = Attendance::whereDate('day', $today)->where('status', 'present')->count()
            + Attendance::whereDate('day', $today)->where('status', 'half')->count() * 0.5;
        $onLeave = Attendance::whereDate('day', $today)->where('status', 'leave')->count();
        $openVisits = $scope(FieldVisit::query())->whereNull('check_out_at')->count();
        $monthStart = now()->startOfMonth()->toDateString();
        $payroll = Payroll::whereDate('month', $monthStart)->first();
        $certs = UserSkill::with(['user', 'skill'])->where('certified', true)->whereNotNull('cert_expires_on')
            ->whereDate('cert_expires_on', '>=', $today)->whereDate('cert_expires_on', '<=', now()->addDays(30)->toDateString())
            ->orderBy('cert_expires_on')->limit(5)->get();

        return view('hr.index', [
            'headcount' => User::where('is_active', true)->count(),
            'present' => $present, 'onLeave' => $onLeave, 'openVisits' => $openVisits,
            'pendingLeave' => $scope(Leave::query())->where('status', 'pending')->count(),
            'pendingOt' => $scope(OvertimeEntry::query())->where('status', 'pending')->count(),
            'pendingClaims' => $scope(Reimbursement::query())->where('status', 'pending')->count(),
            'pendingBonus' => Bonus::where('status', 'pending')->count(),
            'activeLoans' => StaffLoan::where('status', 'active')->count(),
            'openExits' => ExitRecord::where('status', 'pending')->count(),
            'reviews' => Appraisal::count(),
            'payroll' => $payroll, 'certs' => $certs, 'canAll' => $canAll,
        ]);
    }
}
