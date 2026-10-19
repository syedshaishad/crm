<?php

namespace Modules\Reports\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Reports\ReportSchedule;

class ReportScheduleController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:billing');
    }

    public function index()
    {
        return view('reports.schedules', ['rows' => ReportSchedule::orderByDesc('id')->paginate(perPage())]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'type' => 'required|in:sales,receivables,pnl',
            'frequency' => 'required|in:weekly,monthly',
            'recipients' => 'required|string|max:2000',
            'channel' => 'required|in:email,whatsapp',
        ]);
        $emails = collect(preg_split('/[\s,;]+/', $data['recipients']))->map(fn ($e) => trim($e))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values();
        if ($data['channel'] === 'email' && $emails->isEmpty()) {
            return back()->withErrors(['recipients' => 'Add at least one valid email address.'])->withInput();
        }
        ReportSchedule::create([...$data, 'is_active' => true]);

        return back()->with('ok', 'Scheduled report saved — fires automatically.');
    }

    public function toggle(ReportSchedule $schedule)
    {
        $schedule->update(['is_active' => ! $schedule->is_active]);

        return back()->with('ok', $schedule->is_active ? 'Schedule resumed.' : 'Schedule paused.');
    }

    public function destroy(ReportSchedule $schedule)
    {
        $schedule->delete();

        return back()->with('ok', 'Scheduled report removed.');
    }
}
