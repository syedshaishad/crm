<?php

namespace App\Http\Controllers;

use App\Models\ClientRequest;
use Illuminate\Http\Request;

class ClientRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }

    public function index(Request $r)
    {
        $status = in_array($r->input('status'), ['open', 'in_progress', 'done', 'closed', 'all'], true)
            ? $r->input('status') : 'open';
        $rows = ClientRequest::with('client')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')->paginate(perPage())->withQueryString();
        $all = ClientRequest::get(['status']);

        return view('client-requests.index', compact('rows', 'status', 'all'));
    }

    public function show(ClientRequest $clientRequest)
    {
        $clientRequest->load(['client', 'clientUser', 'assignee']);

        return view('client-requests.show', compact('clientRequest'));
    }

    public function reply(Request $r, ClientRequest $clientRequest)
    {
        $d = $r->validate(['body' => 'required|string|max:4000']);
        $messages = $clientRequest->messages ?: [];
        $messages[] = [
            'from' => 'staff', 'author' => auth()->user()->name,
            'body' => $d['body'], 'at' => now()->toIso8601String(),
        ];
        $clientRequest->update([
            'messages' => $messages,
            'status' => $clientRequest->status === 'open' ? 'in_progress' : $clientRequest->status,
        ]);

        return back()->with('ok', 'Reply sent to the client portal.');
    }

    public function status(Request $r, ClientRequest $clientRequest)
    {
        $d = $r->validate(['status' => 'required|in:open,in_progress,done,closed']);
        $clientRequest->update(['status' => $d['status']]);

        return back()->with('ok', 'Request marked '.e($d['status']).'.');
    }
}
