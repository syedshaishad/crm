<?php

namespace App\Notifications;

class TaskAssigned extends DatabaseNotification
{
    public function __construct(string $taskTitle, string $assignee, int $taskId)
    {
        parent::__construct(
            'Task assigned to you',
            $taskTitle.' — assigned by '.$assignee,
            route('tasks.edit', $taskId)
        );
    }
}
