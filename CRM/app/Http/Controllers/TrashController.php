<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Cheques\PdcCheque;
use Modules\Challans\DeliveryRoute;
use Modules\Clients\Client;
use Modules\Documents\Document;
use Modules\Expiry\ExpiryDocument;
use Modules\Billing\Asset;
use Modules\Expenses\Expense;
use Modules\Invoices\Invoice;
use Modules\Leads\Lead;
use Modules\Payments\Payment;
use Modules\Petty\PettyEntry;
use Modules\Products\Product;
use Modules\Projects\Project;
use Modules\Proposals\Proposal;
use Modules\Tasks\Task;

class TrashController extends Controller
{
    public function __construct()
    {
        // Permanent deletion and cross-module restore are destructive —
        // managers and admins only, never plain staff.
        $this->middleware(function ($request, $next) {
            $u = auth()->user();
            if (! $u || (! $u->isAdmin() && ! $u->isManager())) {
                abort(403, 'Managers only.');
            }

            return $next($request);
        });
    }

    public const MAP = [
        'client' => [Client::class, 'Clients'],
        'project' => [Project::class, 'Projects'],
        'task' => [Task::class, 'Tasks'],
        'invoice' => [Invoice::class, 'Invoices'],
        'payment' => [Payment::class, 'Payments'],
        'petty' => [PettyEntry::class, 'Petty cash'],
        'cheque' => [PdcCheque::class, 'Cheques'],
        'route' => [DeliveryRoute::class, 'Delivery routes'],
        'expense' => [Expense::class, 'Expenses'],
        'document' => [Document::class, 'Documents'],
        'expiry' => [ExpiryDocument::class, 'Expiry documents'],
        'asset' => [Asset::class, 'Assets'],
        'lead' => [Lead::class, 'Leads'],
        'proposal' => [Proposal::class, 'Proposals'],
        'product' => [Product::class, 'Products'],
        'user' => [User::class, 'Users'],
    ];

    public static function label($m): string
    {
        return $m->company ?? $m->title ?? $m->number ?? $m->name ?? ('#'.$m->id);
    }

    public function index()
    {
        $groups = [];
        foreach (self::MAP as $type => [$class, $label]) {
            $items = $class::onlyTrashed()->orderByDesc('deleted_at')->limit(50)->get();
            $groups[] = ['type' => $type, 'label' => $label, 'items' => $items];
        }

        return view('trash.index', compact('groups'));
    }

    public function restore(string $type, int $id)
    {
        [$class] = self::MAP[$type] ?? [null];
        abort_unless($class, 404);
        $m = $class::onlyTrashed()->findOrFail($id);
        \DB::transaction(function () use ($m) {
            $m->restore();
            if (method_exists($m, 'items')) {
                $m->items()->withTrashed()->restore();
            }
        });
        activity('restored', $m);

        return back()->with('ok', self::label($m).' restored.');
    }

    public function destroy(string $type, int $id)
    {
        [$class] = self::MAP[$type] ?? [null];
        abort_unless($class, 404);
        $m = $class::onlyTrashed()->findOrFail($id);
        if ($m instanceof Invoice && $m->payments()->exists()) {
            return back()->withErrors(['trash' => 'This invoice has recorded payments — delete them first.']);
        }
        if ($m instanceof Client && ($m->invoices()->exists() || Payment::where('client_id', $m->id)->exists())) {
            return back()->withErrors(['trash' => 'This client has invoices or payments — delete those first.']);
        }
        if ($m instanceof User && $m->id === auth()->id()) {
            return back()->withErrors(['trash' => 'You cannot permanently delete your own account.']);
        }
        $label = self::label($m);
        \DB::transaction(function () use ($m) {
            if (method_exists($m, 'items')) {
                $m->items()->withTrashed()->forceDelete();
            }
            if ($m instanceof Document && $m->path) {
                Storage::disk('public')->delete($m->path);
            }
            if ($m instanceof Expense && $m->receipt_path) {
                Storage::disk('public')->delete($m->receipt_path);
            }
            if ($m instanceof Proposal && $m->signature_path) {
                Storage::disk('public')->delete($m->signature_path);
            }
            if ($m instanceof Client) {
                foreach ($m->proposals()->withTrashed()->get() as $proposal) {
                    $proposal->items()->withTrashed()->forceDelete();
                    if ($proposal->signature_path) Storage::disk('public')->delete($proposal->signature_path);
                    $proposal->forceDelete();
                }
                foreach ($m->projects()->withTrashed()->get() as $project) {
                    Expense::where('project_id', $project->id)->update(['project_id' => null]);
                    $project->forceDelete();
                }
            }
            if ($m instanceof Project) {
                Expense::where('project_id', $m->id)->update(['project_id' => null]);
            }
            $m->forceDelete();
        });
        activity('deleted', null, $label.' permanently deleted');

        return back()->with('ok', $label.' permanently deleted.');
    }
}
