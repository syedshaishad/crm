<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ClientResource;
use Illuminate\Http\Request;
use Modules\Clients\Client;
use Modules\Expenses\Expense;
use Modules\Payments\Payment;

class ClientApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Client::withSum('invoices as billed', 'total')->withSum('invoices as collected', 'paid_amount')->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['name', 'company', 'email', 'phone'], ['status' => 'status']);

        return ClientResource::collection($rows);
    }

    public function show(Client $client)
    {
        return new ClientResource($client->load('invoices'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string', 'company' => 'nullable|string',
            'email' => 'nullable|email', 'phone' => 'nullable|string',
            'address' => 'nullable|string', 'gstin' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        return (new ClientResource(Client::create($data)))->response()->setStatusCode(201);
    }

    public function update(Request $r, Client $client)
    {
        $data = $r->validate([
            'name' => 'sometimes|required|string', 'company' => 'nullable|string',
            'email' => 'nullable|email', 'phone' => 'nullable|string',
            'address' => 'nullable|string', 'gstin' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
        ]);
        $client->update($data);

        return new ClientResource($client->fresh());
    }

    public function destroy(Client $client)
    {
        // Same guards and soft-cascade as the web controller so API deletes
        // never orphan projects/proposals or bypass Trash.
        if ($client->invoices()->exists() || Payment::where('client_id', $client->id)->exists()) {
            return response()->json(['message' => 'This client has invoices or payments. Delete those first, or mark the client inactive instead.'], 409);
        }
        Expense::whereIn('project_id', $client->projects()->pluck('id'))->update(['project_id' => null]);
        foreach ($client->proposals()->get() as $proposal) {
            $proposal->items()->delete();
            $proposal->delete();
        }
        foreach ($client->projects()->get() as $project) {
            $project->delete();
        }
        $client->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
