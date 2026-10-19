<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PaymentResource;
use App\Notifications\PaymentReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Invoices\Invoice;
use Modules\Payments\Payment;
use Modules\Settings\SettingStore;

class PaymentApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Payment::with(['invoice', 'client'])->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['number', 'reference', 'client.name', 'client.company'], ['method' => 'method']);

        return PaymentResource::collection($rows);
    }

    public function show(Payment $payment)
    {
        return new PaymentResource($payment->load(['invoice', 'client']));
    }

    public function store(Request $r, SettingStore $s)
    {
        $data = $r->validate([
            'invoice_id' => 'nullable|exists:invoices,id', 'client_id' => 'required|exists:clients,id',
            'amount' => 'required|numeric|min:0.01', 'method' => 'required|string',
            'reference' => 'nullable|string', 'paid_on' => 'required|date', 'notes' => 'nullable|string',
        ]);
        $inv = ! empty($data['invoice_id']) ? Invoice::find($data['invoice_id']) : null;
        if ($inv) {
            if ($inv->status === 'cancelled' || ($inv->total > 0 && $inv->paid_amount >= $inv->total)) {
                return response()->json(['message' => 'This invoice is already fully paid.'], 409);
            }
            $bal = $inv->balance();
            if (round((float) $data['amount'], 2) > $bal + 0.009) {
                return response()->json(['message' => 'Amount exceeds the outstanding balance of '.money($bal).'.', 'balance' => $bal], 409);
            }
        }
        $prefix = $s->get('payments.prefix', 'PAY-');
        $data['number'] = $prefix.str_pad((string) ((Payment::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT);
        $data['public_token'] = Str::random(32);
        $payment = Payment::create($data);
        notify_roles(['admin', 'manager'], new PaymentReceived($payment));

        return (new PaymentResource($payment->fresh()->load(['invoice', 'client'])))->response()->setStatusCode(201);
    }

    public function destroy(Payment $payment)
    {
        // Mirror the web controller: reversing a credit allocation frees the
        // credit note balance back up instead of corrupting it.
        if ($payment->method === 'credit' && $payment->reference) {
            if ($cn = \Modules\Invoices\CreditNote::where('number', $payment->reference)->first()) {
                $cn->decrement('applied_amount', min((float) $payment->amount, (float) $cn->applied_amount));
                if ($cn->fresh()->remaining() > 0 && $cn->status === 'applied') {
                    $cn->update(['status' => 'open']);
                }
            }
        }
        $payment->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}

