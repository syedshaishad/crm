<?php

namespace App\Channels;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Notifications\Notification;

class WebPushChannel
{
    public function __construct(private WebPushService $push) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        $payload['icon'] = $payload['icon'] ?? ($notifiable->avatarUrl() ?: asset('favicon.ico'));
        $payload['badge'] = $payload['badge'] ?? asset('favicon.ico');
        $payload['tag'] = $payload['tag'] ?? 'crm-'.substr(hash('sha256', ($payload['title'] ?? '').($payload['url'] ?? '')), 0, 24);
        $payload['vibrate'] = $payload['vibrate'] ?? [120, 60, 120];

        if (function_exists('defer') && ! app()->runningInConsole()) {
            defer(fn () => $this->push->send($notifiable, $payload));

            return;
        }

        $this->push->send($notifiable, $payload);
    }
}
