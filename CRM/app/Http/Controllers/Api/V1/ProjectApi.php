<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ProjectResource;
use Illuminate\Http\Request;
use Modules\Projects\Project;

class ProjectApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Project::with('client')->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['name', 'client.name', 'client.company'], ['status' => 'status']);

        return ProjectResource::collection($rows);
    }

    public function show(Project $project)
    {
        return new ProjectResource($project->load('client'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'client_id' => 'required|exists:clients,id', 'name' => 'required|string',
            'description' => 'nullable|string', 'status' => 'nullable|string',
            'start_date' => 'nullable|date', 'due_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
        ]);

        return (new ProjectResource(Project::create($data)->load('client')))->response()->setStatusCode(201);
    }

    public function update(Request $r, Project $project)
    {
        $data = $r->validate([
            'client_id' => 'sometimes|required|exists:clients,id', 'name' => 'sometimes|required|string',
            'description' => 'nullable|string', 'status' => 'nullable|string',
            'start_date' => 'nullable|date', 'due_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
        ]);
        $project->update($data);

        return new ProjectResource($project->fresh()->load('client'));
    }

    public function destroy(Project $project)
    {
        // Mirror the web controller: keep expenses for the books, just unlink
        // them instead of leaving a project_id dangling at a trashed project.
        \Modules\Expenses\Expense::where('project_id', $project->id)->update(['project_id' => null]);
        $project->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
