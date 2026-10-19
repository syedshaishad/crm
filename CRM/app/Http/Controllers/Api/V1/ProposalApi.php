<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\InvoiceResource;
use App\Http\Resources\ProposalResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Invoices\Invoice;
use Modules\Proposals\Proposal;
use Modules\Settings\SettingStore;

class ProposalApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Proposal::with('client')->visibleTo($r->user())->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['number', 'client.name', 'client.company'], ['status' => 'status']);

        return ProposalResource::collection($rows);
    }

    public function show(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal, $r->user())) {
            return response()->json(['message' => 'This proposal is not assigned to you.'], 403);
        }

        return new ProposalResource($proposal->load(['client', 'items']));
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'nullable|date', 'valid_until' => 'nullable|date|after_or_equal:issue_date',
            'gst_rate' => 'required|numeric', 'notes' => 'nullable|string', 'terms' => 'nullable|string',
            'place_of_supply' => 'nullable|string|max:100', 'reverse_charge' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0', 'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $prefix = $s->get('proposal.prefix', 'PROP-');
        $number = $prefix.str_pad((string) ((Proposal::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        $prop = Proposal::create([
            'number' => $number, 'client_id' => $data['client_id'], 'project_id' => $data['project_id'] ?? null,
            'issue_date' => $data['issue_date'] ?? now()->toDateString(), 'valid_until' => $data['valid_until'] ?? now()->addDays((int) $s->get('proposal.valid_days', 15))->toDateString(),
            'status' => 'draft', 'gst_rate' => $data['gst_rate'], 'notes' => $data['notes'] ?? null, 'terms' => $data['terms'] ?? null,
            'place_of_supply' => $data['place_of_supply'] ?? null, 'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
            'discount' => $data['discount'] ?? 0, 'sign_token' => Str::random(32),
            'created_by' => $r->user()->id, 'assigned_to' => $r->user()->id,
        ]);
        foreach ($data['items'] as $it) {
            $prop->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $it['rate'], 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $it['rate'], 2)]);
        }
        $prop->recalc();

        return (new ProposalResource($prop->fresh()->load(['client', 'items'])))->response()->setStatusCode(201);
    }

    /**
     * Update header fields; when `items[]` is supplied the whole line set
     * is replaced (simpler JSON contract than the web form's line diffing).
     */
    public function update(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal, $r->user())) {
            return response()->json(['message' => 'This proposal is not assigned to you.'], 403);
        }
        if ($proposal->status !== 'draft') {
            return response()->json(['message' => 'Only draft proposals can be edited.'], 409);
        }
        $data = $r->validate([
            'client_id' => 'sometimes|required|exists:clients,id', 'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'nullable|date', 'valid_until' => 'nullable|date',
            'gst_rate' => 'sometimes|required|numeric', 'notes' => 'nullable|string', 'terms' => 'nullable|string',
            'place_of_supply' => 'nullable|string|max:100', 'reverse_charge' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'sometimes|array|min:1', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0', 'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $proposal->fill(collect($data)->except('items')->all());
        $proposal->save();
        if (array_key_exists('items', $data)) {
            $proposal->items()->delete();
            foreach ($data['items'] as $it) {
                $proposal->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $it['rate'], 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $it['rate'], 2)]);
            }
        }
        $proposal->recalc();

        return new ProposalResource($proposal->fresh()->load(['client', 'items']));
    }

    public function destroy(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal, $r->user())) {
            return response()->json(['message' => 'This proposal is not assigned to you.'], 403);
        }
        if (in_array($proposal->status, ['accepted', 'invoiced'])) {
            return response()->json(['message' => 'A signed proposal cannot be deleted.'], 409);
        }
        $proposal->items()->delete();
        $proposal->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /** Draft → sent. Generates the client signing link. */
    public function send(Request $r, Proposal $proposal)
    {
        if (! $this->canView($proposal, $r->user())) {
            return response()->json(['message' => 'This proposal is not assigned to you.'], 403);
        }
        if ($proposal->status !== 'draft') {
            return response()->json(['message' => 'Only draft proposals can be sent.'], 409);
        }
        if ($proposal->items()->count() === 0) {
            return response()->json(['message' => 'Add at least one service line first.'], 409);
        }
        $proposal->update(['status' => 'sent', 'sign_token' => $proposal->sign_token ?: Str::random(32)]);

        // sign_token is intentionally only exposed here (the share moment),
        // never in list/detail responses.
        return (new ProposalResource($proposal->fresh()->load(['client', 'items'])))->additional(['sign_token' => $proposal->fresh()->sign_token]);
    }

    /** Accepted → draft invoice (idempotent: returns the existing invoice). */
    public function convert(Request $r, Proposal $proposal, SettingStore $s)
    {
        if (! $this->canView($proposal, $r->user())) {
            return response()->json(['message' => 'This proposal is not assigned to you.'], 403);
        }
        if ($proposal->converted_invoice_id && $inv = Invoice::find($proposal->converted_invoice_id)) {
            return new InvoiceResource($inv->load(['client', 'items']));
        }
        if ($proposal->status !== 'accepted') {
            return response()->json(['message' => 'Only signed (accepted) proposals can be converted to an invoice.'], 409);
        }
        $prefix = $s->get('invoice.prefix', 'INV-');
        $number = $prefix.str_pad((string) ((Invoice::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        $inv = Invoice::create([
            'number' => $number, 'client_id' => $proposal->client_id, 'project_id' => $proposal->project_id,
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays((int) $s->get('invoice.due_days', 7))->toDateString(),
            'status' => 'draft', 'gst_rate' => $proposal->gst_rate, 'notes' => trim(($proposal->notes ? $proposal->notes."\n" : '').'Converted from proposal '.$proposal->number.'.'),
            'place_of_supply' => $proposal->place_of_supply, 'reverse_charge' => (bool) $proposal->reverse_charge,
            'discount' => $proposal->discount, 'public_token' => Str::random(32),
            'created_by' => $r->user()->id, 'assigned_to' => $proposal->assigned_to,
        ]);
        foreach ($proposal->items as $it) {
            $inv->items()->create(['description' => $it->description, 'hsn_code' => $it->hsn_code, 'unit' => $it->unit, 'tax_rate' => $it->tax_rate, 'qty' => $it->qty, 'rate' => $it->rate, 'amount' => $it->amount]);
        }
        $inv->recalc();
        $proposal->update(['converted_invoice_id' => $inv->id, 'status' => 'invoiced']);

        return (new InvoiceResource($inv->fresh()->load(['client', 'items'])))->response()->setStatusCode(201);
    }

    private function canView(Proposal $proposal, $user): bool
    {
        return $proposal->isVisibleTo($user);
    }
}
