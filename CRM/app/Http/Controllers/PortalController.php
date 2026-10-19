<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Invoices\Invoice;
use Modules\Proposals\Proposal;

/**
 * Client portal: everything here is scoped to the signed-in portal user's
 * own client. Document viewing/signing/paying reuses the proven public
 * token flows — the portal only lists and links, never duplicates them.
 */
class PortalController extends Controller
{
    private function client()
    {
        return auth('client')->user()->client;
    }

    public function dashboard()
    {
        $client = $this->client();
        $invoices = $client->invoices()->orderByDesc('id')->get();
        $live = $invoices->whereNotIn('status', ['cancelled']);
        $outstanding = max(0, $live->sum('total') - $live->sum('paid_amount') - $live->sum('writeoff_amount'));
        $proposals = $client->proposals()->orderByDesc('id')->get();

        return view('portal.dashboard', [
            'client' => $client,
            'outstanding' => $outstanding,
            'openInvoices' => $live->whereNotIn('status', ['paid'])->count(),
            'pendingProposals' => $proposals->where('status', 'sent')->count(),
            'recentInvoices' => $invoices->take(5),
            'recentProposals' => $proposals->take(5),
        ]);
    }

    public function invoices()
    {
        $invoices = $this->client()->invoices()->orderByDesc('id')->paginate(perPage());

        return view('portal.invoices', compact('invoices'));
    }

    public function proposals()
    {
        $proposals = $this->client()->proposals()->orderByDesc('id')->paginate(perPage());

        return view('portal.proposals', compact('proposals'));
    }

    public function receipts()
    {
        $receipts = \Modules\Payments\Payment::where('client_id', $this->client()->id)
            ->with('invoice')->orderByDesc('paid_on')->orderByDesc('id')->paginate(perPage());

        return view('portal.receipts', compact('receipts'));
    }

    /** Client's projects with live task progress + a feedback box per project. */
    public function work()
    {
        $projects = $this->client()->projects()->with(['tasks' => fn ($q) => $q->orderBy('due_date')])->orderByDesc('id')->get();
        $feedback = \Modules\Projects\ProjectFeedback::whereIn('project_id', $projects->pluck('id'))->orderByDesc('id')->get()->groupBy('project_id');

        return view('portal.work', compact('projects', 'feedback'));
    }

    /** CSAT for a finished (or ongoing) project, straight from the portal. */
    public function feedback(\Illuminate\Http\Request $r, int $project)
    {
        $proj = $this->client()->projects()->findOrFail($project);
        $data = $r->validate(['score' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:1000', 'author' => 'nullable|string|max:255']);
        \Modules\Projects\ProjectFeedback::create(['project_id' => $proj->id, 'score' => $data['score'], 'comment' => $data['comment'] ?? null, 'author' => $data['author'] ?? auth('client')->user()->name]);

        return back()->with('ok', 'Thanks — your rating helps us do better.');
    }

    /** Full account statement: invoices, payments, credit notes, TDS with running balance. */
    public function statement()    {
        $client = $this->client();
        $data = app(\Modules\Clients\Http\ClientController::class)->ledgerData($client, true);

        return view('portal.statement', array_merge(compact('client'), $data));
    }

    public function passwordForm()
    {
        return view('portal.password');
    }

    public function passwordStore(Request $r)
    {
        $data = $r->validate([
            'current' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $u = auth('client')->user();
        if (! Hash::check($data['current'], $u->password)) {
            return back()->withErrors(['current' => 'Current password is wrong.']);
        }
        $u->update(['password' => Hash::make($data['password'])]);

        return back()->with('ok', 'Password changed.');
    }

    /** Defensive scoping helpers for anything that loads by id. */
    public static function ownInvoice(int $id): Invoice
    {
        return auth('client')->user()->client->invoices()->findOrFail($id);
    }

    public static function ownProposal(int $id): Proposal
    {
        return auth('client')->user()->client->proposals()->findOrFail($id);
    }
}
