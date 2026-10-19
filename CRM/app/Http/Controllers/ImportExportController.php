<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clients\Client;
use Modules\Leads\Lead;
use Modules\Products\Product;
use Modules\Settings\SettingStore;

/**
 * CSV import/export across the main lists. Export mirrors each list's own
 * columns; import currently covers clients, leads and products (the
 * onboarding workloads) and is written so more types are a one-line addition.
 */
class ImportExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:import')->except(['feed']);
    }
    /** Exportable lists and the columns they ship. */
    public static function exportTypes(): array
    {
        return [
            'clients', 'leads', 'invoices', 'payments', 'expenses', 'tasks', 'projects', 'proposals', 'products',
        ];
    }

    /** Types that can be bulk-imported from CSV. */
    public static function importTypes(): array
    {
        return ['clients', 'leads', 'products'];
    }

    public function export(Request $r, string $type)
    {
        abort_unless(in_array($type, self::exportTypes(), true), 404);
        // The client directory is sensitive — admins only (matches Reports export).
        if ($type === 'clients' && ! auth()->user()->isAdmin()) {
            abort(403, 'Administrators only.');
        }
        $module = app(\Modules\Licensing\LicenseManager::class)->moduleForRoute($type);
        if ($module && ! module_enabled($module)) {
            abort(404);
        }
        $rows = $this->rowsFor($type);
        $file = $type.'-export-'.now()->format('Y-m-d').'.csv';

        return csv_response($file, $rows, $this->headersFor($type));
    }

    /** Feed types safe for automation (client PII stays out — use the app for that). */
    public static function feedTypes(): array
    {
        return ['invoices', 'payments', 'expenses', 'proposals', 'tasks', 'projects', 'products', 'leads'];
    }

    /**
     * Token-gated live CSV for Google Sheets (=IMPORTDATA) and Zoho.
     * No login — the 40-char token IS the password. Regenerate it any time.
     */
    public function feed(string $token, string $type)
    {
        $good = (string) setting('sync.feed_token', '');
        if ($good === '' || ! hash_equals($good, $token)) {
            abort(404);
        }
        abort_unless(in_array($type, self::feedTypes(), true), 404);
        $rows = $this->rowsFor($type);

        return csv_response($type.'-live-'.now()->format('Y-m-d').'.csv', $rows, $this->headersFor($type));
    }

    /** Rotate the sync token (old feed URLs die immediately). */
    public function rotateFeed(SettingStore $s)
    {
        $token = Str::random(40);
        $s->set('sync.feed_token', $token, 'sync');

        return back()->with('ok', 'Fresh feed token issued — update your Sheets/Zoho links.');
    }

    public function importForm()
    {
        $types = array_values(array_filter(self::importTypes(), function ($t) {
            $mod = app(\Modules\Licensing\LicenseManager::class)->moduleForRoute($t);

            return ! $mod || module_enabled($mod);
        }));

        return view('import', ['types' => $types]);
    }

    public function importStore(Request $r)
    {
        $data = $r->validate([
            'type' => 'required|in:'.implode(',', self::importTypes()),
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);
        $rows = csv_rows_from_file($data['file']->getRealPath());
        if (! $rows) {
            return back()->withErrors(['file' => 'Could not read the CSV — make sure it has a header row.']);
        }
        $module = app(\Modules\Licensing\LicenseManager::class)->moduleForRoute($data['type']);
        if ($module && ! module_enabled($module)) {
            abort(404);
        }
        $result = match ($data['type']) {
            'clients' => $this->importClients($rows),
            'leads' => $this->importLeads($rows),
            default => $this->importProducts($rows),
        };

        return back()->with('ok', $result['message'])->with('import_result', $result);
    }

    private function rowsFor(string $type)
    {
        return match ($type) {
            'clients' => DB::table('clients')->orderBy('name')->get([
                'name', 'company', 'email', 'phone', 'address', 'gstin', 'state', 'status',
            ]),
            'leads' => DB::table('leads')->orderByDesc('id')->get([
                'name', 'company', 'email', 'phone', 'city', 'state', 'source', 'stage', 'value',
            ]),
            'invoices' => DB::table('invoices')->join('clients', 'clients.id', '=', 'invoices.client_id')->orderByDesc('invoices.id')->get([
                'invoices.number', 'clients.name as client', 'invoices.issue_date', 'invoices.due_date', 'invoices.status', 'invoices.total', 'invoices.paid_amount', 'invoices.gst_rate',
            ]),
            'payments' => DB::table('payments')->join('clients', 'clients.id', '=', 'payments.client_id')->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')->orderByDesc('payments.id')->get([
                'payments.number', 'clients.name as client', 'invoices.number as invoice', 'payments.method', 'payments.reference', 'payments.amount', 'payments.paid_on',
            ]),
            'expenses' => DB::table('expenses')->orderByDesc('id')->get([
                'number', 'title', 'category', 'vendor', 'method', 'amount', 'spent_on', 'status',
            ]),
            'tasks' => DB::table('tasks')->orderByDesc('id')->get([
                'title', 'status', 'priority', 'due_date',
            ]),
            'projects' => DB::table('projects')->join('clients', 'clients.id', '=', 'projects.client_id')->orderByDesc('projects.id')->get([
                'projects.name', 'clients.name as client', 'projects.status', 'projects.budget', 'projects.start_date', 'projects.due_date',
            ]),
            'proposals' => DB::table('proposals')->join('clients', 'clients.id', '=', 'proposals.client_id')->orderByDesc('proposals.id')->get([
                'proposals.number', 'clients.name as client', 'proposals.status', 'proposals.total', 'proposals.created_at',
            ]),
            'products' => DB::table('products')->orderBy('name')->get([
                'name', 'sku', 'description', 'rate', 'tax_rate', 'hsn_code', 'unit', 'status',
            ]),
        };
    }

    /** Header row per export type, so even an empty export opens with columns. */
    private function headersFor(string $type): array
    {
        return match ($type) {
            'clients' => ['name', 'company', 'email', 'phone', 'address', 'gstin', 'state', 'status'],
            'leads' => ['name', 'company', 'email', 'phone', 'city', 'state', 'source', 'stage', 'value'],
            'invoices' => ['number', 'client', 'issue_date', 'due_date', 'status', 'total', 'paid_amount', 'gst_rate'],
            'payments' => ['number', 'client', 'invoice', 'method', 'reference', 'amount', 'paid_on'],
            'expenses' => ['number', 'title', 'category', 'vendor', 'method', 'amount', 'spent_on'],
            'tasks' => ['title', 'status', 'priority', 'due_date'],
            'projects' => ['name', 'client', 'status', 'budget', 'start_date', 'due_date'],
            'proposals' => ['number', 'client', 'status', 'total', 'created_at'],
            'products' => ['name', 'sku', 'description', 'rate', 'tax_rate', 'hsn_code', 'unit', 'status'],
            default => [],
        };
    }

    private function importClients(array $rows): array
    {
        $created = $skipped = 0;
        $firstError = null;
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $firstError ??= 'A row had no name.';

                continue;
            }
            $email = trim((string) ($row['email'] ?? ''));
            if ($email && Client::where('email', $email)->exists()) {
                $skipped++;
                $firstError ??= "{$email} already exists.";

                continue;
            }
            Client::create([
                'name' => $name,
                'company' => $this->str($row, 'company'),
                'email' => $email,
                'phone' => $this->str($row, 'phone'),
                'address' => $this->str($row, 'address'),
                'gstin' => $this->str($row, 'gstin'),
                'state' => $this->str($row, 'state'),
                'status' => in_array(strtolower($this->str($row, 'status')), ['inactive'], true) ? 'inactive' : 'active',
            ]);
            $created++;
        }

        return $this->result('clients', $created, $skipped, count($rows), $firstError);
    }

    private function importLeads(array $rows): array
    {
        $created = $skipped = 0;
        $firstError = null;
        $stages = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $firstError ??= 'A row had no name.';

                continue;
            }
            $stage = strtolower($this->str($row, 'stage'));
            Lead::create([
                'name' => $name,
                'company' => $this->str($row, 'company'),
                'email' => $this->str($row, 'email'),
                'phone' => $this->str($row, 'phone'),
                'city' => $this->str($row, 'city'),
                'state' => $this->str($row, 'state'),
                'source' => $this->str($row, 'source') ?: 'import',
                'stage' => in_array($stage, $stages, true) ? $stage : 'new',
                'value' => (float) ($row['value'] ?? 0),
            ]);
            $created++;
        }

        return $this->result('leads', $created, $skipped, count($rows), $firstError);
    }

    private function importProducts(array $rows): array
    {
        $created = $skipped = 0;
        $firstError = null;
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $firstError ??= 'A row had no name.';

                continue;
            }
            $sku = trim((string) ($row['sku'] ?? ''));
            $dup = $sku !== ''
                ? Product::where('sku', $sku)->exists()
                : Product::where('name', $name)->exists();
            if ($dup) {
                $skipped++;
                $firstError ??= "{$name} already exists.";

                continue;
            }
            $tax = trim((string) ($row['tax_rate'] ?? ''));
            Product::create([
                'name' => $name,
                'sku' => $sku ?: null,
                'description' => $this->str($row, 'description'),
                'rate' => (float) ($row['rate'] ?? 0),
                'tax_rate' => $tax === '' ? null : (float) $tax,
                'hsn_code' => $this->str($row, 'hsn_code'),
                'unit' => $this->str($row, 'unit') ?: 'NOS',
                'status' => strtolower($this->str($row, 'status')) === 'archived' ? 'archived' : 'active',
            ]);
            $created++;
        }

        return $this->result('products', $created, $skipped, count($rows), $firstError);
    }

    private function str(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    private function result(string $type, int $created, int $skipped, int $total, ?string $firstError): array
    {
        $msg = "Imported {$created} {$type}";
        if ($skipped) {
            $msg .= ", skipped {$skipped}";
        }
        $msg .= " of {$total} rows.";

        return ['message' => $msg, 'created' => $created, 'skipped' => $skipped, 'total' => $total, 'first_error' => $firstError, 'type' => $type];
    }
}
