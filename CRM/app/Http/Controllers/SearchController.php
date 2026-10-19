<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Clients\Client;
use Modules\Documents\Document;
use Modules\Expenses\Expense;
use Modules\Invoices\Invoice;
use Modules\Leads\Lead;
use Modules\Payments\Payment;
use Modules\Products\Product;
use Modules\Projects\Project;
use Modules\Proposals\Proposal;
use Modules\Tasks\Task;

class SearchController extends Controller
{
    /** Universal search across every module (min 2 chars, 8 hits per group). */
    public function index(Request $r)
    {
        $q = trim((string) $r->input('q', ''));
        $groups = [];
        if (mb_strlen($q) >= 2) {
            $s = '%'.$q.'%';
            $u = auth()->user();

            $clients = Client::where(fn ($w) => $w->where('name', 'like', $s)->orWhere('company', 'like', $s)->orWhere('email', 'like', $s)->orWhere('phone', 'like', $s))->limit(8)->get();
            $groups[] = ['label' => 'Clients', 'items' => $clients->map(fn ($c) => [
                'title' => $c->company ?: $c->name, 'sub' => $c->company ? $c->name : ($c->email ?: $c->phone),
                'url' => route('clients.show', $c),
            ])->all()];

            $projects = Project::with('client')->where('name', 'like', $s)->limit(8)->get();
            $groups[] = ['label' => 'Projects', 'items' => $projects->map(fn ($p) => [
                'title' => $p->name, 'sub' => ($p->client->company ?? $p->client->name ?? '').' · '.$p->status,
                'url' => route('projects.show', $p),
            ])->all()];

            $tasks = Task::with('project')->where('title', 'like', $s)->limit(8)->get();
            $groups[] = ['label' => 'Tasks', 'items' => $tasks->map(fn ($t) => [
                'title' => $t->title, 'sub' => ($t->project->name ?? 'No project').' · '.$t->status,
                'url' => route('tasks.edit', $t),
            ])->all()];

            $leads = Lead::where(fn ($w) => $w->where('name', 'like', $s)->orWhere('company', 'like', $s)->orWhere('phone', 'like', $s))->limit(8)->get();
            $groups[] = ['label' => 'Leads', 'items' => $leads->map(fn ($l) => [
                'title' => $l->company ?: $l->name, 'sub' => ($l->phone ?: '').' · '.$l->stage,
                'url' => route('leads.edit', $l),
            ])->all()];

            $invoices = Invoice::with('client')->visibleTo($u)->where(fn ($w) => $w->where('number', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)))
                ->limit(8)->get();
            $groups[] = ['label' => 'Invoices', 'items' => $invoices->map(fn ($i) => [
                'title' => $i->number, 'sub' => ($i->client->company ?? $i->client->name ?? '').' · '.$i->status,
                'url' => route('invoices.show', $i),
            ])->all()];

            $proposals = Proposal::with('client')->visibleTo($u)
                ->where(fn ($w) => $w->where('number', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)))
                ->limit(8)->get();
            $groups[] = ['label' => 'Proposals', 'items' => $proposals->map(fn ($p) => [
                'title' => $p->number, 'sub' => ($p->client->company ?? $p->client->name ?? '').' · '.$p->status,
                'url' => route('proposals.show', $p),
            ])->all()];

            $payments = Payment::with(['invoice', 'client'])
                ->where(fn ($w) => $w->where('number', 'like', $s)->orWhere('reference', 'like', $s)->orWhereHas('client', fn ($c) => $c->where('name', 'like', $s)->orWhere('company', 'like', $s)))
                ->limit(8)->get();
            $groups[] = ['label' => 'Payments', 'items' => $payments->map(fn ($p) => [
                'title' => $p->number ?? money($p->amount), 'sub' => ($p->client->name ?? '').' · '.$p->method,
                'url' => $p->id ? route('payments.show', $p) : route('payments.index'),
            ])->all()];

            $expenses = Expense::where(fn ($w) => $w->where('title', 'like', $s)->orWhere('vendor', 'like', $s)->orWhere('number', 'like', $s))->limit(8)->get();
            $groups[] = ['label' => 'Expenses', 'items' => $expenses->map(fn ($e) => [
                'title' => ($e->number ? $e->number.' · ' : '').$e->title, 'sub' => money($e->amount).' · '.$e->category,
                'url' => route('expenses.show', $e),
            ])->all()];

            $docs = Document::where('name', 'like', $s)->limit(8)->get();
            $groups[] = ['label' => 'Documents', 'items' => $docs->map(fn ($d) => [
                'title' => $d->name, 'sub' => fmt_size($d->size),
                'url' => route('documents.download', $d),
            ])->all()];

            $products = Product::where(fn ($w) => $w->where('name', 'like', $s)->orWhere('sku', 'like', $s)->orWhere('hsn_code', 'like', $s))->limit(8)->get();
            $groups[] = ['label' => 'Products', 'items' => $products->map(fn ($p) => [
                'title' => $p->name, 'sub' => money($p->rate).($p->sku ? ' · '.$p->sku : ''),
                'url' => route('products.edit', $p),
            ])->all()];

            if ($u && $u->isAdmin()) {
                $users = User::where(fn ($w) => $w->where('name', 'like', $s)->orWhere('email', 'like', $s))->limit(8)->get();
                $groups[] = ['label' => 'Users', 'items' => $users->map(fn ($x) => [
                    'title' => $x->name, 'sub' => $x->email.' · '.$x->role,
                    'url' => route('settings.users.edit', $x),
                ])->all()];
            }

            // Switched-off modules leak no data through search either.
            $modFor = ['Clients' => 'Clients', 'Projects' => 'Projects', 'Tasks' => 'Tasks', 'Leads' => 'Leads', 'Invoices' => 'Invoices', 'Proposals' => 'Proposals', 'Payments' => 'Payments', 'Expenses' => 'Expenses', 'Documents' => 'Documents', 'Products' => 'Products'];
            $groups = array_values(array_filter($groups, fn ($g) => ! isset($modFor[$g['label']]) || module_enabled($modFor[$g['label']])));
            // Permission-denied groups leak no data through search either.
            $permFor = ['Clients' => 'sales', 'Projects' => 'work', 'Tasks' => 'work', 'Leads' => 'sales', 'Invoices' => 'billing', 'Proposals' => 'sales', 'Payments' => 'billing', 'Expenses' => 'billing', 'Documents' => 'documents', 'Products' => 'sales'];
            $groups = array_values(array_filter($groups, fn ($g) => ! isset($permFor[$g['label']]) || user_can($permFor[$g['label']], 'view')));
            $groups = array_values(array_filter($groups, fn ($g) => count($g['items'])));
        }
        if ($r->boolean('live') || $r->wantsJson()) {
            return response()->json(['q' => $q, 'groups' => $groups, 'total' => array_sum(array_map(fn ($g) => count($g['items']), $groups))]);
        }

        return view('search', compact('q', 'groups'));
    }
}
