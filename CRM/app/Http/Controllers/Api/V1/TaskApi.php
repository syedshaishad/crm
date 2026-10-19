<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;
use Modules\Tasks\Task;

class TaskApi extends BaseApi
{
    public function index(Request $r)
    {
        $q = Task::with(['project', 'assignee'])->orderByDesc('id');
        $rows = $this->applySearch($r, $q, ['title'], ['status' => 'status', 'priority' => 'priority']);

        return TaskResource::collection($rows);
    }

    public function show(Task $task)
    {
        return new TaskResource($task->load(['project', 'assignee']));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'project_id' => 'nullable|exists:projects,id', 'client_id' => 'nullable|exists:clients,id',
            'assigned_to' => 'nullable|exists:users,id', 'title' => 'required|string',
            'description' => 'nullable|string', 'status' => 'nullable|string',
            'priority' => 'nullable|string', 'due_date' => 'nullable|date',
        ]);
        $data['status'] ??= 'todo';
        $data['priority'] ??= 'medium';

        return (new TaskResource(Task::create($data)->load(['project', 'assignee'])))->response()->setStatusCode(201);
    }

    public function update(Request $r, Task $task)
    {
        $data = $r->validate([
            'project_id' => 'nullable|exists:projects,id', 'client_id' => 'nullable|exists:clients,id',
            'assigned_to' => 'nullable|exists:users,id', 'title' => 'sometimes|required|string',
            'description' => 'nullable|string', 'status' => 'nullable|string',
            'priority' => 'nullable|string', 'due_date' => 'nullable|date',
        ]);
        $task->update($data);

        return new TaskResource($task->fresh()->load(['project', 'assignee']));
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /** Move a task's status (kanban). */
    public function move(Request $r, Task $task)
    {
        $data = $r->validate(['status' => 'required|string|in:todo,in_progress,review,done']);
        $task->update($data);

        return new TaskResource($task->fresh()->load(['project', 'assignee']));
    }
}
