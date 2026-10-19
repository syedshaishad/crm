<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationsController extends Controller
{
    public function index(Request $r)
    {
        $notes = $r->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->paginate(perPage())
            ->withQueryString();

        return view('notifications.index', ['notes' => $notes]);
    }

    public function markRead(Request $r, string $id)
    {
        $note = $r->user()->notifications()->where('id', $id)->first();
        $note?->markAsRead();

        $msg = $note ? 'Notification marked as read.' : 'Notification not found.';
        if ($r->wantsJson() || $r->has('ajax')) {
            return response()->json(['ok' => true, 'unread' => $r->user()->unreadNotifications()->count(), 'msg' => $msg]);
        }

        return back()->with('ok', $msg);
    }

    public function markAllRead(Request $r)
    {
        $r->user()->unreadNotifications->each->markAsRead();

        if ($r->wantsJson() || $r->has('ajax')) {
            return response()->json(['ok' => true, 'unread' => $r->user()->unreadNotifications()->count(), 'msg' => 'All notifications marked as read.']);
        }

        return back()->with('ok', 'All notifications marked as read.');
    }
}
