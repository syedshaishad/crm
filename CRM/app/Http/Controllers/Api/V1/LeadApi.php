<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LeadResource;
use Illuminate\Http\Request;
use Modules\Leads\Lead;

class LeadApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Lead::with('assignee')->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['name', 'company', 'phone', 'email'], ['stage' => 'stage', 'source' => 'source']);

        return LeadResource::collection($rows);
    }

    public function show(Lead $lead)
    {
        return new LeadResource($lead->load('assignee'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255', 'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:50', 'stage' => 'nullable|in:new,contacted,qualified,proposal,won,lost',
            'value' => 'nullable|numeric|min:0', 'follow_up_on' => 'nullable|date',
            'notes' => 'nullable|string', 'assigned_to' => 'nullable|exists:users,id',
        ]);
        $data['stage'] ??= 'new';

        return (new LeadResource(Lead::create($data)))->response()->setStatusCode(201);
    }

    public function update(Request $r, Lead $lead)
    {
        $data = $r->validate([
            'name' => 'sometimes|required|string|max:255', 'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:100', 'state' => 'nullable|string|max:100',
            'source' => 'nullable|string|max:50', 'stage' => 'sometimes|in:new,contacted,qualified,proposal,won,lost',
            'value' => 'nullable|numeric|min:0', 'follow_up_on' => 'nullable|date',
            'notes' => 'nullable|string', 'assigned_to' => 'nullable|exists:users,id',
        ]);
        $lead->update($data);

        return new LeadResource($lead->fresh()->load('assignee'));
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /** Move a lead's stage (kanban-style). */
    public function move(Request $r, Lead $lead)
    {
        $data = $r->validate(['stage' => 'required|in:new,contacted,qualified,proposal,won,lost']);
        $lead->update(['stage' => $data['stage']]);

        return new LeadResource($lead->fresh()->load('assignee'));
    }
}
