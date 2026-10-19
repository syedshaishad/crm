<?php

namespace Modules\Proposals\Http;

use App\Notifications\ProposalSigned;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Branding\Brand;
use Modules\Clients\Client;
use Modules\Core\Access\AssignsRecords;
use Modules\Core\Numbering\NextNumber;
use Modules\Invoices\Invoice;
use Modules\Invoices\RecurringProfile;
use Modules\Projects\Project;
use Modules\Proposals\Proposal;
use Modules\Proposals\ProposalFollowupSender;
use Modules\Proposals\ProposalReply;
use Modules\Proposals\Snippet;
use Modules\Settings\SettingStore;

class ProposalController extends Controller
{
    use AssignsRecords;

    public function __construct()
    {
        // Guest signing links stay public — everything else needs sales access.
        $this->middleware('perm:sales')->except(['sign', 'signStore', 'decline', 'signReply']);
    }

    public function index(Request $r)
    {
        $q = Proposal::with(['client', 'assignee'])->visibleTo(auth()->user())->orderByDesc('id');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)));
        }
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        return view('proposals.index', ['proposals' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'status' => $r->status]);
    }

    protected function canView(Proposal $proposal): bool
    {
        return $proposal->isVisibleTo(auth()->user());
    }


    public function create(Request $r, SettingStore $s)
    {
        $prefix = $s->get('proposal.prefix', 'PROP-');
        $client = $r->client_id ? Client::find($r->client_id) : null;

        return view('proposals.form', ['proposal' => new Proposal(['gst_rate' => $s->get('tax.default_gst_rate', config('gst.default_gst_rate', 18)), 'place_of_supply' => $client->state ?? $s->get('company.state', ''), 'terms' => $s->get('proposal.terms', '')]), 'clients' => Client::orderBy('name')->get(), 'projects' => Project::orderBy('name')->get(['id', 'name', 'client_id']), 'prefix' => $prefix, 'default_client' => $r->client_id, 'staff' => $this->staffOptions(), 'snippets' => Snippet::orderBy('title')->get()]);
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'nullable|date', 'valid_until' => 'nullable|date|after_or_equal:issue_date',
            'gst_rate' => 'required|numeric', 'notes' => 'nullable|string', 'scope' => 'nullable|string', 'results' => 'nullable|string', 'terms' => 'nullable|string',
            'place_of_supply' => 'nullable|string|max:100', 'reverse_charge' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0', 'cover' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'items' => 'required|array|min:1', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28', 'items.*.billing' => 'nullable|in:once,monthly,yearly',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0', 'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $prefix = $s->get('proposal.prefix', 'PROP-');
        $number = NextNumber::take($prefix, 'proposals');
        $prop = Proposal::create([
            'number' => $number, 'client_id' => $data['client_id'], 'project_id' => $data['project_id'] ?? null,
            'issue_date' => $data['issue_date'] ?? now()->toDateString(), 'valid_until' => $data['valid_until'] ?? now()->addDays((int) $s->get('proposal.valid_days', 15))->toDateString(),
            'status' => 'draft', 'gst_rate' => $data['gst_rate'], 'notes' => $data['notes'] ?? null, 'scope' => $data['scope'] ?? null, 'results' => $data['results'] ?? null, 'terms' => $data['terms'] ?? null,
            'place_of_supply' => $data['place_of_supply'] ?? null, 'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
            'discount' => $data['discount'] ?? 0, 'sign_token' => Str::random(32),
            'created_by' => auth()->id(),
            'assigned_to' => $this->resolveAssignee($r, auth()->user(), auth()->id()),
        ]);
        foreach ($data['items'] as $it) {
            $prop->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS',
            'tax_rate' => $it['tax_rate'] ?? null, 'billing' => $it['billing'] ?? 'once', 'qty' => $it['qty'], 'rate' => $it['rate'], 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $it['rate'], 2)]);
        }
        $prop->recalc();
        if ($r->hasFile('cover')) {
            if ($reason = upload_reject_reason($r->file('cover'))) {
                return redirect()->route('proposals.edit', $prop)->withErrors(['cover' => $reason]);
            }
            $prop->update(['cover_path' => $r->file('cover')->store('proposal-covers', 'public')]);
        }

        return redirect()->route('proposals.show', $prop)->with('ok', 'Proposal created.');
    }

    public function show(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403, 'This proposal is not assigned to you.');
        }
        $proposal->load(['items', 'client', 'project', 'invoice', 'assignee', 'creator']);
        $followups = DB::table('proposal_followup_logs')->where('proposal_id', $proposal->id)->orderByDesc('id')->limit(5)->get();

        return view('proposals.show', ['proposal' => $proposal, 'followups' => $followups, 'replies' => ProposalReply::where('proposal_id', $proposal->id)->orderBy('id')->get()]);
    }

    /** Fullscreen pitch mode for screen-share walkthroughs. */
    public function pitch(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403, 'This proposal is not assigned to you.');
        }
        $proposal->load(['items', 'client']);
        $signUrl = $proposal->sign_token ? route('proposals.sign', $proposal->sign_token) : '';

        return view('proposals.pitch', compact('proposal', 'signUrl'));
    }

    public function edit(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403, 'This proposal is not assigned to you.');
        }
        if ($proposal->status !== 'draft') {
            return redirect()->route('proposals.show', $proposal)->withErrors(['status' => 'Only draft proposals can be edited.']);
        }
        $proposal->load(['items', 'client']);

        return view('proposals.edit', ['proposal' => $proposal, 'clients' => Client::orderBy('name')->get(), 'projects' => Project::where('client_id', $proposal->client_id)->orderBy('name')->get(), 'staff' => $this->staffOptions(), 'snippets' => Snippet::orderBy('title')->get()]);
    }

    public function update(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403, 'This proposal is not assigned to you.');
        }
        if ($proposal->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft proposals can be edited.']);
        }
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'nullable|date', 'valid_until' => 'nullable|date|after_or_equal:issue_date',
            'gst_rate' => 'required|numeric', 'notes' => 'nullable|string', 'scope' => 'nullable|string', 'results' => 'nullable|string', 'terms' => 'nullable|string',
            'place_of_supply' => 'nullable|string|max:100', 'reverse_charge' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'sometimes|array', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28', 'items.*.billing' => 'nullable|in:once,monthly,yearly',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0', 'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items_existing' => 'sometimes|array',
            'items_existing.*.description' => 'required|string',
            'items_existing.*.hsn_code' => 'nullable|string|max:20', 'items_existing.*.unit' => 'nullable|string|max:10',
            'items_existing.*.tax_rate' => 'nullable|numeric|min:0|max:28', 'items_existing.*.billing' => 'nullable|in:once,monthly,yearly',
            'items_existing.*.qty' => 'required|numeric|min:0.01', 'items_existing.*.rate' => 'required|numeric|min:0', 'items_existing.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'remove_items' => 'sometimes|array', 'remove_items.*' => 'integer',
            'cover' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);
        $proposal->update([
            'client_id' => $data['client_id'], 'project_id' => $data['project_id'] ?? null,
            'issue_date' => $data['issue_date'] ?? $proposal->issue_date, 'valid_until' => $data['valid_until'] ?? $proposal->valid_until,
            'gst_rate' => $data['gst_rate'], 'notes' => $data['notes'] ?? null, 'scope' => $data['scope'] ?? null, 'results' => $data['results'] ?? null, 'terms' => $data['terms'] ?? null,
            'place_of_supply' => $data['place_of_supply'] ?? null, 'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
            'discount' => $data['discount'] ?? 0,
            'assigned_to' => auth()->user()->canManageInvoices() ? $this->resolveAssignee($r, auth()->user(), $proposal->assigned_to) : $proposal->assigned_to,
        ]);
        $remove = collect($data['remove_items'] ?? [])->map(fn ($v) => (int) $v);
        foreach ($data['items_existing'] ?? [] as $id => $it) {
            $line = $proposal->items()->find((int) $id);
            if (! $line) {
                continue;
            }
            if ($remove->contains($line->id)) {
                $line->delete();

                continue;
            }
            $line->update(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'billing' => $it['billing'] ?? $line->billing ?? 'once', 'qty' => $it['qty'], 'rate' => $it['rate'], 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $it['rate'], 2)]);
        }
        if ($proposal->items()->whereIn('id', $remove)->exists()) {
            $proposal->items()->whereIn('id', $remove)->delete();
        }
        if (! empty($data['items'])) {
            foreach ($data['items'] as $it) {
                $proposal->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'billing' => $it['billing'] ?? 'once', 'qty' => $it['qty'], 'rate' => $it['rate'], 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $it['rate'], 2)]);
            }
        }
        $proposal->recalc();
        if ($r->hasFile('cover')) {
            if ($reason = upload_reject_reason($r->file('cover'))) {
                return back()->withErrors(['cover' => $reason])->withInput();
            }
            if ($proposal->cover_path) {
                Storage::disk('public')->delete($proposal->cover_path);
            }
            $proposal->update(['cover_path' => $r->file('cover')->store('proposal-covers', 'public')]);
        }

        return redirect()->route('proposals.show', $proposal)->with('ok', 'Proposal updated.');
    }

    public function destroy(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403, 'This proposal is not assigned to you.');
        }
        if (in_array($proposal->status, ['accepted', 'invoiced'])) {
            return back()->withErrors(['proposal' => 'A signed proposal cannot be deleted. Decline it first.']);
        }
        $proposal->items()->delete();
        $proposal->delete();

        return redirect()->route('proposals.index')->with('ok', 'Proposal deleted.');
    }

    /** Email the signing link to the client (uses the editable proposal template). */
    public function email(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        $proposal->load(['client']);
        if ($proposal->status !== 'sent') {
            return back()->withErrors(['email' => 'Send the proposal first — the signing link is created on send.']);
        }
        if (empty($proposal->client->email)) {
            return back()->withErrors(['email' => 'Client has no email address.']);
        }
        $brand = app(Brand::class)->all();
        $data = [
            'company_name' => $brand['company_name'] ?? config('app.name'), 'client_name' => $proposal->client->name,
            'proposal_number' => $proposal->number, 'total' => money($proposal->total),
            'valid_until' => $proposal->valid_until ? d($proposal->valid_until, 'j F Y') : '—',
            'link' => route('proposals.sign', $proposal->sign_token),
        ];
        try {
            Mail::send('emails.template', ['body' => render_template('email.proposal_body', $data)], function ($m) use ($proposal, $data) {
                $m->to($proposal->client->email, $proposal->client->name)
                    ->subject(render_template('email.proposal_subject', $data));
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Could not send email: '.$e->getMessage().' — check Settings → Integrations (SMTP).']);
        }

        return back()->with('ok', 'Proposal emailed to '.$proposal->client->email);
    }

    /** Manual expiry nudge — same throttle discipline as invoice reminders (3 days). */
    public function followup(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        if ($proposal->status !== 'sent') {
            return back()->withErrors(['followup' => 'Only open (sent) proposals can be followed up.']);
        }
        if ($proposal->last_followed_up_at && $proposal->last_followed_up_at->gt(now()->subDays(3))) {
            return back()->withErrors(['followup' => 'Already followed up '.$proposal->last_followed_up_at->diffForHumans().'. Follow-ups are limited to one every 3 days.']);
        }
        $res = ProposalFollowupSender::send($proposal, 'manual');
        if (! $res['ok']) {
            return back()->withErrors(['followup' => $res['message']]);
        }

        return back()->with('ok', $res['message']);
    }

    /** Draft → sent. Generates the client signing link. */
    public function send(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        if ($proposal->status !== 'draft') {
            return back()->withErrors(['status' => 'Only draft proposals can be sent.']);
        }
        if ($proposal->items()->count() === 0) {
            return back()->withErrors(['items' => 'Add at least one service line first.']);
        }
        // Big concessions need a manager: staff cannot send over-limit discounts
        // (line-level and invoice-level discounts count together).
        $limit = (float) setting('proposal.discount_limit', 20);
        $u = auth()->user();
        $gross = (float) $proposal->items()->sum('amount');
        $given = $gross > 0 ? round(($gross - (float) $proposal->subtotal + (float) $proposal->discount) / $gross * 100, 2) : 0;
        if ($limit > 0 && $gross > 0 && ! $u->isAdmin() && ! $u->isManager() && $given > $limit) {
            return back()->withErrors(['discount' => 'Discount exceeds the '.$limit.'% staff limit — ask a manager to review and send.']);
        }
        $proposal->update(['status' => 'sent', 'sign_token' => $proposal->sign_token ?: Str::random(32)]);

        return back()->with('ok', 'Proposal sent. Share the signing link with your client.');
    }

    /** Public signing page (no login — token link for the client). */
    public function sign(Request $request, string $token)
    {
        $proposal = Proposal::where('sign_token', $token)->with(['items', 'client'])->firstOrFail();
        // Open tracking: count the view, but collapse rapid refreshes/bots
        // from the same IP into one (30-minute window).
        $ip = $request->ip();
        if ($proposal->last_view_ip !== $ip || ! $proposal->last_viewed_at || $proposal->last_viewed_at->lt(now()->subMinutes(30))) {
            $proposal->update([
                'view_count' => ($proposal->view_count ?? 0) + 1,
                'first_viewed_at' => $proposal->first_viewed_at ?? now(),
                'last_viewed_at' => now(), 'last_view_ip' => $ip,
            ]);
        }

        return view('proposals.sign', ['proposal' => $proposal, 'replies' => ProposalReply::where('proposal_id', $proposal->id)->orderBy('id')->get()]);
    }

    /** Client draws/types signature → proposal accepted (closed). */
    public function signStore(Request $r, string $token)
    {
        $proposal = Proposal::where('sign_token', $token)->firstOrFail();
        if ($proposal->status !== 'sent') {
            return back()->withErrors(['proposal' => 'This proposal is no longer open for signing.']);
        }
        if ($proposal->valid_until && $proposal->valid_until->isPast()) {
            $proposal->update(['status' => 'expired']);

            return back()->withErrors(['proposal' => 'This proposal has expired. Ask us for a fresh one.']);
        }
        $data = $r->validate([
            'signed_by' => 'required|string|max:255',
            'signature' => 'required|string|max:3000000',
        ]);
        if (! preg_match('#^data:image/png;base64,#', $data['signature'])) {
            return back()->withErrors(['signature' => 'Invalid signature image. Please draw again.']);
        }
        $bin = base64_decode(substr($data['signature'], 22), true);
        if ($bin === false || strlen($bin) < 100 || strlen($bin) > 2000000 || substr($bin, 1, 3) !== 'PNG') {
            return back()->withErrors(['signature' => 'Invalid signature image. Please draw again.']);
        }
        if ($proposal->signature_path) {
            Storage::disk('public')->delete($proposal->signature_path);
        }
        $path = 'signatures/prop_'.$proposal->id.'_'.time().'.png';
        Storage::disk('public')->put($path, $bin);
        $proposal->update(['signature_path' => $path, 'signed_by' => $data['signed_by'], 'signed_at' => now(), 'status' => 'accepted']);
        notify_roles(['admin', 'manager'], new ProposalSigned($proposal));

        return back()->with('ok', 'Signed. Thank you — we will take it from here.');
    }

    /** Client replies from the public signing page (questions, haggling). */
    public function signReply(Request $r, string $token)
    {
        $proposal = Proposal::where('sign_token', $token)->firstOrFail();
        if (! in_array($proposal->status, ['sent'], true)) {
            return back()->withErrors(['reply' => 'This proposal is closed — replies are off.']);
        }
        $data = $r->validate(['author' => 'nullable|string|max:120', 'body' => 'required|string|max:2000']);
        ProposalReply::create(['proposal_id' => $proposal->id, 'side' => 'client', 'author' => $data['author'] ?: $proposal->client->name, 'body' => $data['body']]);
        notify_roles(['admin', 'manager'], new \App\Notifications\ProposalReplied($proposal));

        return back()->with('ok', 'Message sent — we will reply here shortly.');
    }

    /** Staff reply from the proposal page. */
    public function staffReply(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        $data = $r->validate(['body' => 'required|string|max:2000']);
        ProposalReply::create(['proposal_id' => $proposal->id, 'side' => 'staff', 'author' => auth()->user()->name, 'body' => $data['body']]);

        return back()->with('ok', 'Reply posted — the client sees it on their link.');
    }

    /** Staff records an offline acceptance (client confirmed on call/WhatsApp). */
    public function acceptOffline(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        if ($proposal->status !== 'sent') {
            return back()->withErrors(['proposal' => 'Only open (sent) proposals can be accepted.']);
        }
        $data = $r->validate([
            'accept_mode' => 'required|in:call,whatsapp,in_person,email',
            'accept_note' => 'nullable|string|max:255',
        ]);
        $proposal->update([
            'status' => 'accepted', 'signed_at' => now(), 'signed_by' => $proposal->client->name,
            'accept_mode' => $data['accept_mode'], 'accept_note' => $data['accept_note'] ?? null,
        ]);
        activity('proposal-accepted', $proposal, 'Proposal '.$proposal->number.' accepted offline ('.$data['accept_mode'].')');
        notify_roles(['admin', 'manager'], new ProposalSigned($proposal));

        return back()->with('ok', 'Marked accepted (offline '.$data['accept_mode'].'). Convert to invoice when ready.');
    }

    /** Client declines via the same public link. */
    public function decline(Request $r, string $token)
    {
        $proposal = Proposal::where('sign_token', $token)->firstOrFail();
        if ($proposal->status !== 'sent') {
            return back()->withErrors(['proposal' => 'This proposal is already closed.']);
        }
        $data = $r->validate([
            'close_reason' => 'nullable|string|max:255',
            'lost_reason' => 'nullable|in:price,budget,timing,competitor,features,ghosted,other',
            'competitor' => 'nullable|string|max:120',
        ]);
        $proposal->update([
            'status' => 'declined', 'close_reason' => $data['close_reason'] ?? null,
            'lost_reason' => $data['lost_reason'] ?? null, 'competitor' => $data['competitor'] ?? null,
        ]);

        return back()->with('ok', 'Noted. This proposal is now closed as declined.');
    }

    /** Staff records a lost deal (client told us on call/WhatsApp, or went silent). */
    public function declineOffline(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        if (! in_array($proposal->status, ['sent', 'expired'], true)) {
            return back()->withErrors(['proposal' => 'Only open or expired proposals can be marked declined.']);
        }
        $data = $r->validate([
            'lost_reason' => 'required|in:price,budget,timing,competitor,features,ghosted,other',
            'competitor' => 'nullable|string|max:120',
            'close_reason' => 'nullable|string|max:255',
        ]);
        $proposal->update([
            'status' => 'declined', 'lost_reason' => $data['lost_reason'],
            'competitor' => $data['competitor'] ?? null, 'close_reason' => $data['close_reason'] ?? null,
        ]);
        activity('proposal-declined', $proposal, 'Proposal '.$proposal->number.' marked declined ('.$data['lost_reason'].')');

        return back()->with('ok', 'Marked declined. It now counts in win-loss analytics.');
    }

    /** Side-by-side compare of two proposals (versions or alternatives). */
    public function compare(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        $other = null;
        if ($r->filled('with')) {
            $other = Proposal::with(['items', 'client'])->find($r->input('with'));
            if ($other && ! $this->canView($other)) {
                abort(403);
            }
        }
        $proposal->load(['items', 'client']);
        $options = Proposal::with('client')->orderByDesc('id')->limit(100)->get(['id', 'number', 'client_id', 'status', 'total']);

        return view('proposals.compare', compact('proposal', 'other', 'options'));
    }

    /** Accepted proposal → project with every scope line as a task. Idempotent. */
    public function startProject(Proposal $proposal)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        if (! in_array($proposal->status, ['accepted', 'invoiced'], true)) {
            return back()->withErrors(['proposal' => 'Only accepted proposals start projects.']);
        }
        if ($proposal->project_id && $p = \Modules\Projects\Project::find($proposal->project_id)) {
            return redirect()->route('projects.show', $p)->with('ok', 'Project already running — opened it.');
        }
        if (! trim((string) $proposal->scope)) {
            return back()->withErrors(['scope' => 'Add a scope of work first — each line becomes a task.']);
        }
        $project = \Modules\Projects\Project::create([
            'client_id' => $proposal->client_id, 'name' => $proposal->number.' — '.($proposal->client->company ?: $proposal->client->name),
            'description' => trim(($proposal->results ? "Expected results:\n".$proposal->results."\n\n" : '').'From proposal '.$proposal->number.'.'),
            'status' => 'in_progress', 'start_date' => now()->toDateString(), 'budget' => $proposal->total,
        ]);
        $n = 0;
        foreach (preg_split('/\R/', (string) $proposal->scope) as $line) {
            $line = trim(preg_replace('/^[\d\-\*\.\s\)\]]+/', '', trim($line)));
            if ($line === '') {
                continue;
            }
            \Modules\Tasks\Task::create(['project_id' => $project->id, 'client_id' => $proposal->client_id, 'assigned_to' => $proposal->assigned_to, 'title' => mb_substr($line, 0, 200), 'status' => 'todo', 'priority' => 'medium']);
            $n++;
        }
        $proposal->update(['project_id' => $project->id]);

        return redirect()->route('projects.show', $project)->with('ok', 'Project started with '.$n.' tasks from scope.');
    }

    /** Duplicate any proposal as a fresh draft (new number, new signing link). */
    public function duplicate(Proposal $proposal, SettingStore $s)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        $prefix = $s->get('proposal.prefix', 'PROP-');
        $copy = Proposal::create([
            'number' => NextNumber::take($prefix, 'proposals'),
            'client_id' => $proposal->client_id, 'project_id' => $proposal->project_id,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays((int) $s->get('proposal.valid_days', 15))->toDateString(),
            'status' => 'draft', 'gst_rate' => $proposal->gst_rate,
            'notes' => $proposal->notes, 'scope' => $proposal->scope, 'results' => $proposal->results, 'terms' => $proposal->terms,
            'place_of_supply' => $proposal->place_of_supply, 'reverse_charge' => (bool) $proposal->reverse_charge,
            'discount' => $proposal->discount, 'sign_token' => Str::random(32),
            'created_by' => auth()->id(), 'assigned_to' => $proposal->assigned_to,
            'cover_path' => $proposal->cover_path,
        ]);
        foreach ($proposal->items as $it) {
            $copy->items()->create(['description' => $it->description, 'hsn_code' => $it->hsn_code, 'unit' => $it->unit, 'tax_rate' => $it->tax_rate, 'billing' => $it->billing ?? 'once', 'qty' => $it->qty, 'rate' => $it->rate, 'discount_percent' => $it->discount_percent ?? 0, 'amount' => $it->amount]);
        }
        $copy->recalc();

        return redirect()->route('proposals.edit', $copy)->with('ok', 'Cloned '.$proposal->number.' as draft '.$copy->number.' — review and send.');
    }

    /** Accepted proposal → draft invoice carrying every pitched service line. Idempotent. */
    public function convert(Proposal $proposal, SettingStore $s)
    {
        if (! $this->canView($proposal)) {
            abort(403);
        }
        if ($proposal->converted_invoice_id && $inv = Invoice::find($proposal->converted_invoice_id)) {
            return redirect()->route('invoices.show', $inv)->with('ok', 'Already converted — opened the invoice.');
        }
        if ($proposal->status !== 'accepted') {
            return back()->withErrors(['proposal' => 'Only signed (accepted) proposals can be converted to an invoice.']);
        }
        $prefix = $s->get('invoice.prefix', 'INV-');
        $number = NextNumber::take($prefix, 'invoices');
        $inv = Invoice::create([
            'number' => $number, 'client_id' => $proposal->client_id, 'project_id' => $proposal->project_id,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays((int) $s->get('invoice.due_days', 7))->toDateString(),
            'status' => 'draft', 'gst_rate' => $proposal->gst_rate, 'notes' => trim(($proposal->notes ? $proposal->notes."\n" : '').'Converted from proposal '.$proposal->number.'.'),
            'place_of_supply' => $proposal->place_of_supply, 'reverse_charge' => (bool) $proposal->reverse_charge,
            'discount' => $proposal->discount,
            'created_by' => auth()->id(), 'assigned_to' => $proposal->assigned_to,
        ]);
        foreach ($proposal->items as $it) {
            $inv->items()->create(['description' => $it->description, 'hsn_code' => $it->hsn_code, 'unit' => $it->unit, 'tax_rate' => $it->tax_rate, 'qty' => $it->qty, 'rate' => $it->rate, 'discount_percent' => $it->discount_percent ?? 0, 'amount' => $it->amount]);
        }
        $inv->recalc();
        // Monthly/yearly lines keep billing automatically: first cycle lives on
        // this invoice, future cycles become recurring schedules from next period.
        $schedules = [];
        foreach (['monthly' => 'addMonthNoOverflow', 'yearly' => 'addYear'] as $cycle => $adder) {
            $lines = $proposal->items->where('billing', $cycle)->values();
            if ($lines->isEmpty()) {
                continue;
            }
            $first = now()->$adder()->toDateString();
            $profile = RecurringProfile::create([
                'client_id' => $proposal->client_id, 'project_id' => $proposal->project_id,
                'frequency' => $cycle, 'next_run_on' => $first, 'is_active' => true,
                'gst_rate' => $proposal->gst_rate, 'discount' => 0,
                'place_of_supply' => $proposal->place_of_supply,
                'notes' => 'Auto-created from proposal '.$proposal->number.' ('.$cycle.' lines).',
                'items' => $lines->map(fn ($it) => ['description' => $it->description, 'hsn_code' => $it->hsn_code, 'unit' => $it->unit, 'tax_rate' => $it->tax_rate, 'qty' => $it->qty, 'rate' => $it->rate])->all(),
                'created_by' => auth()->id(),
            ]);
            $schedules[] = $cycle.' from '.$first;
        }
        $proposal->update(['converted_invoice_id' => $inv->id, 'status' => 'invoiced']);

        return redirect()->route('invoices.show', $inv)->with('ok', 'Proposal '.$proposal->number.' converted to invoice '.$inv->number.'.'.($schedules ? ' Recurring: '.implode(', ', $schedules).'.' : ''));
    }
}

