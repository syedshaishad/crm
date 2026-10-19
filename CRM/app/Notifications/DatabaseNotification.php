<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base for in-app (database) notifications. Subclasses just supply a
 * title/body/link; the shape stored is intentionally flat so the bell
 * dropdown and /notifications page can render without knowing types.
 */
abstract class DatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url = '',
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
