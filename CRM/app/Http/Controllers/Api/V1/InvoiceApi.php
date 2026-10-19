<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Invoices\Invoice;
use Modules\Settings\SettingStore;

class InvoiceApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Invoice::with(['client', 'assignee'])->visibleTo($r->user())->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['number', 'client.name', 'client.company'], ['status' => 'status']);

        return InvoiceResource::collection($rows);
    }

    public function show(Invoice $invoice)
    {
        if (! $this->canView($invoice, request()->user())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return new InvoiceResource($invoice->load(['client', 'items', 'assignee']));
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'project_id' => 'nullable|exists:projects,id',
            'issue_date' => 'required|date', 'due_date' => 'nullable|date',
            'gst_rate' => 'required|numeric', 'notes' => 'nullable|string',
            'place_of_supply' => 'nullable|string|max:100', 'reverse_charge' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1', 'items.*.description' => 'required|string',
            'items.*.hsn_code' => 'nullable|string|max:20', 'items.*.unit' => 'nullable|string|max:10',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:28',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.rate' => 'required|numeric|min:0', 'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);
        $prefix = $s->get('invoice.prefix', config('branding.invoice_prefix', 'INV-'));
        $number = $prefix.str_pad((string) ((Invoice::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        $inv = Invoice::create([
            'number' => $number, 'client_id' => $data['client_id'], 'project_id' => $data['project_id'] ?? null,
            'issue_date' => $data['issue_date'], 'due_date' => $data['due_date'] ?? null,
            'status' => 'draft', 'gst_rate' => $data['gst_rate'], 'notes' => $data['notes'] ?? null,
            'place_of_supply' => $data['place_of_supply'] ?? null, 'reverse_charge' => (bool) ($data['reverse_charge'] ?? false),
            'discount' => $data['discount'] ?? 0, 'public_token' => Str::random(32),
            'created_by' => $r->user()->id, 'assigned_to' => $r->user()->id,
        ]);
        foreach ($data['items'] as $it) {
            $inv->items()->create(['description' => $it['description'], 'hsn_code' => $it['hsn_code'] ?? null, 'unit' => $it['unit'] ?? 'NOS', 'tax_rate' => $it['tax_rate'] ?? null, 'qty' => $it['qty'], 'rate' => $it['rate'], 'discount_percent' => $it['discount_percent'] ?? 0, 'amount' => round($it['qty'] * $it['rate'], 2)]);
        }
        $inv->recalc();

        return (new InvoiceResource($inv->fresh()->load(['client', 'items'])))->response()->setStatusCode(201);
    }

    /** Flip a draft/sent invoice to sent. */
    public function markSent(Request $r, Invoice $invoice)
    {
        if (! $this->canView($invoice, $r->user())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return response()->json(['message' => 'A '.$invoice->status.' invoice cannot be marked sent.'], 409);
        }
        $invoice->update(['status' => 'sent']);

        return new InvoiceResource($invoice->fresh()->load('client'));
    }

    private function canView(Invoice $invoice, $user): bool
    {
        return $invoice->isVisibleTo($user);
    }
}

