<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Clients\Client;
use Modules\Documents\Document;
use Modules\Expenses\Expense;
use Modules\Invoices\Invoice;
use Modules\Payments\Payment;
use Modules\Projects\Project;
use Modules\Settings\SettingStore;
use Modules\Tasks\Task;

/**
 * Wipe all business data (keeps users, settings, install state) and seed
 * dummy rows covering every status, tax split, method and edge case.
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipe();

        $s = app(SettingStore::class);
        $s->set('company.state', 'Telangana', 'company');
        $invPrefix = $s->get('invoice.prefix', 'INV-');
        $payPrefix = $s->get('payments.prefix', 'PAY-');
        $expPrefix = $s->get('expense.prefix', 'EXP-');
        $pad = fn ($n) => str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        $today = now()->toDateString();

        // ---------- clients ----------
        $acme = Client::create(['name' => 'John Doe', 'company' => 'Acme Pvt Ltd', 'email' => 'accounts@acme.test', 'phone' => '9000000001', 'address' => 'Abc, Hyder, Abd, TG - 500001', 'gstin' => '27ABCDE1234F1Z5', 'state' => 'Telangana', 'status' => 'active', 'notes' => 'Key account — net-15 terms.']);
        $bright = Client::create(['name' => 'Priya Nair', 'company' => 'Bright Retail', 'email' => 'priya@bright.test', 'phone' => '9000000002', 'address' => 'Linking Road, Mumbai - 400050', 'gstin' => '27BRGHT5678K9Z2', 'state' => 'Maharashtra', 'status' => 'active']);
        $kiran = Client::create(['name' => 'Kiran Rao', 'email' => 'kiran@test.local', 'phone' => '9000000003', 'address' => 'Jayanagar, Bengaluru - 560041', 'state' => 'Karnataka', 'status' => 'active', 'notes' => 'Individual buyer, no GSTIN.']);
        $delhi = Client::create(['name' => 'Amit Shah', 'company' => 'Capital Traders', 'email' => 'amit@capital.test', 'address' => 'Chandni Chowk, Delhi - 110006', 'gstin' => '07CAPTL9012M3Z8', 'state' => 'Delhi', 'status' => 'active']);
        $old = Client::create(['name' => 'Sleepy Joe', 'company' => 'Old Ventures', 'status' => 'inactive', 'notes' => 'Churned in 2024.']);

        // ---------- projects (one per status) ----------
        $web = Project::create(['client_id' => $acme->id, 'name' => 'Acme Website', 'description' => 'Corporate site rebuild with CMS.', 'status' => 'in_progress', 'start_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->addDays(15)->toDateString(), 'budget' => 50000]);
        $shop = Project::create(['client_id' => $bright->id, 'name' => 'Retail POS Rollout', 'description' => 'POS setup across 3 stores.', 'status' => 'new', 'start_date' => $today, 'due_date' => now()->addDays(60)->toDateString(), 'budget' => 120000]);
        $app = Project::create(['client_id' => $kiran->id, 'name' => 'Portfolio App', 'status' => 'on_hold', 'start_date' => now()->subDays(40)->toDateString(), 'due_date' => now()->addDays(10)->toDateString(), 'budget' => 25000]);
        $done = Project::create(['client_id' => $delhi->id, 'name' => 'GST Filing Setup', 'status' => 'completed', 'start_date' => now()->subDays(90)->toDateString(), 'due_date' => now()->subDays(30)->toDateString(), 'budget' => 15000]);
        $dead = Project::create(['client_id' => $acme->id, 'name' => 'Old Logo Refresh', 'status' => 'cancelled', 'budget' => 8000]);

        // ---------- tasks (every status x priority, due variety) ----------
        $T = fn (array $a) => Task::create($a);
        $T(['project_id' => $web->id, 'assigned_to' => 2, 'title' => 'Design homepage mockup', 'description' => 'Two options for review.', 'status' => 'in_progress', 'priority' => 'high', 'due_date' => now()->addDays(2)->toDateString()]);
        $T(['project_id' => $web->id, 'assigned_to' => 2, 'title' => 'Migrate old blog posts', 'status' => 'todo', 'priority' => 'medium', 'due_date' => now()->addDays(9)->toDateString()]);
        $T(['project_id' => $shop->id, 'assigned_to' => 3, 'title' => 'Order barcode scanners', 'status' => 'todo', 'priority' => 'urgent', 'due_date' => now()->subDays(3)->toDateString()]);
        $T(['project_id' => $shop->id, 'title' => 'Draft store rollout checklist', 'status' => 'review', 'priority' => 'medium', 'due_date' => $today]);
        $T(['project_id' => $done->id, 'assigned_to' => 2, 'title' => 'File GSTR-1 for Capital Traders', 'status' => 'done', 'priority' => 'high', 'due_date' => now()->subDays(31)->toDateString()]);
        $T(['title' => 'Renew office domain', 'status' => 'todo', 'priority' => 'low', 'due_date' => now()->addDays(30)->toDateString()]);
        $T(['client_id' => $kiran->id, 'assigned_to' => 3, 'title' => 'Call Kiran about advance', 'status' => 'in_progress', 'priority' => 'medium', 'due_date' => now()->subDays(1)->toDateString()]);
        $T(['project_id' => $app->id, 'title' => 'Waiting on client assets', 'status' => 'todo', 'priority' => 'low']);

        // ---------- invoices (every status, both tax splits) ----------
        $mk = function ($attrs, array $items, $status) use ($invPrefix, &$pad, &$n) {
            $n = ($n ?? 0) + 1;
            $inv = Invoice::create(array_merge(['number' => $invPrefix.$pad($n), 'status' => 'draft', 'gst_rate' => 18, 'created_by' => 1], $attrs));
            foreach ($items as $it) {
                $inv->items()->create(array_merge(['qty' => 1, 'unit' => 'NOS', 'rate' => 0], $it, ['amount' => round($it['qty'] * $it['rate'], 2)]));
            }
            $inv->recalc();
            if ($status !== 'draft') {
                $inv->update(['status' => $status]);
            }

            return $inv->fresh();
        };

        // 1. draft, intra-state, mixed slabs
        $mk(['client_id' => $acme->id, 'project_id' => $web->id, 'issue_date' => $today, 'due_date' => now()->addDays(15)->toDateString(), 'place_of_supply' => 'Telangana', 'assigned_to' => 2, 'notes' => 'Draft — not sent yet.'], [
            ['description' => 'Website design', 'hsn_code' => '998314', 'qty' => 1, 'rate' => 30000, 'tax_rate' => 18],
            ['description' => 'Stock photos pack', 'qty' => 2, 'rate' => 1500, 'tax_rate' => 12],
        ], 'draft');

        // 2. sent, inter-state (IGST), no payments
        $mk(['client_id' => $bright->id, 'project_id' => $shop->id, 'issue_date' => now()->subDays(5)->toDateString(), 'due_date' => now()->addDays(25)->toDateString(), 'place_of_supply' => 'Maharashtra', 'assigned_to' => 3], [
            ['description' => 'POS hardware setup', 'hsn_code' => '847130', 'qty' => 3, 'rate' => 22000, 'tax_rate' => 18, 'unit' => 'SET'],
        ], 'sent');

        // 3. partial, intra-state, discount + default-rate line
        $inv3 = $mk(['client_id' => $acme->id, 'project_id' => $web->id, 'issue_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->addDays(10)->toDateString(), 'place_of_supply' => 'Telangana', 'discount' => 2000, 'assigned_to' => 2], [
            ['description' => 'Backend development', 'hsn_code' => '998314', 'qty' => 40, 'rate' => 1200, 'unit' => 'HRS', 'tax_rate' => null],
            ['description' => 'SSL certificate', 'qty' => 1, 'rate' => 5000, 'tax_rate' => 18],
        ], 'sent');
        Payment::create(['invoice_id' => $inv3->id, 'client_id' => $acme->id, 'amount' => 10000, 'method' => 'upi', 'reference' => 'UTR123456', 'paid_on' => now()->subDays(9)->toDateString(), 'notes' => 'Token advance.']);

        // 4. paid in full, two methods
        $inv4 = $mk(['client_id' => $delhi->id, 'project_id' => $done->id, 'issue_date' => now()->subDays(45)->toDateString(), 'due_date' => now()->subDays(30)->toDateString(), 'place_of_supply' => 'Delhi', 'assigned_to' => 2], [
            ['description' => 'GST filing setup', 'hsn_code' => '998231', 'qty' => 1, 'rate' => 15000, 'tax_rate' => 18],
        ], 'sent');
        Payment::create(['invoice_id' => $inv4->id, 'client_id' => $delhi->id, 'amount' => 10000, 'method' => 'bank', 'reference' => 'NEFT998877', 'paid_on' => now()->subDays(40)->toDateString()]);
        Payment::create(['invoice_id' => $inv4->id, 'client_id' => $delhi->id, 'amount' => $inv4->fresh()->total - 10000, 'method' => 'cash', 'paid_on' => now()->subDays(32)->toDateString()]);

        // 5. overdue, inter-state, tiny part-payment
        $inv5 = $mk(['client_id' => $bright->id, 'issue_date' => now()->subDays(60)->toDateString(), 'due_date' => now()->subDays(20)->toDateString(), 'place_of_supply' => 'Maharashtra', 'assigned_to' => 3], [
            ['description' => 'Annual maintenance', 'qty' => 1, 'rate' => 24000, 'tax_rate' => 18],
        ], 'sent');
        Payment::create(['invoice_id' => $inv5->id, 'client_id' => $bright->id, 'amount' => 2000, 'method' => 'upi', 'paid_on' => now()->subDays(25)->toDateString()]);
        $inv5->update(['status' => 'overdue']);

        // 6. cancelled (terminal, no payments)
        $mk(['client_id' => $acme->id, 'project_id' => $dead->id, 'issue_date' => now()->subDays(50)->toDateString(), 'place_of_supply' => 'Telangana'], [
            ['description' => 'Logo concepts', 'qty' => 3, 'rate' => 4000, 'tax_rate' => 18],
        ], 'cancelled');

        // 7. reverse charge, inter-state, unregistered buyer
        $mk(['client_id' => $kiran->id, 'issue_date' => now()->subDays(3)->toDateString(), 'due_date' => now()->addDays(12)->toDateString(), 'place_of_supply' => 'Karnataka', 'reverse_charge' => true, 'notes' => 'Reverse charge — tax payable by recipient.'], [
            ['description' => 'Consulting', 'hsn_code' => '998312', 'qty' => 5, 'rate' => 3000, 'unit' => 'HRS', 'tax_rate' => 18],
        ], 'sent');

        // advance payment (no invoice) + razorpay-flavoured one
        Payment::create(['client_id' => $kiran->id, 'amount' => 5000, 'method' => 'upi', 'reference' => 'ADV-KIRAN-1', 'paid_on' => now()->subDays(2)->toDateString(), 'notes' => 'Advance for portfolio app.']);
        $adv = Payment::orderByDesc('id')->first();
        $adv->update(['razorpay_payment_id' => 'pay_DUMMY12345', 'razorpay_order_id' => 'order_DUMMY12345', 'method' => 'razorpay']);

        // backfill receipt numbers (id-based, like the migration)
        foreach (Payment::orderBy('id')->get(['id']) as $p) {
            Payment::where('id', $p->id)->update(['number' => $payPrefix.$pad($p->id)]);
        }

        // ---------- expenses (every category flavour, linked + general) ----------
        $E = fn (array $a) => Expense::create($a);
        $en = 0;
        $mkE = function (array $a) use ($expPrefix, $pad, &$en, $E) {
            $en++;

            return $E(array_merge(['number' => $expPrefix.$pad($en), 'created_by' => 1], $a));
        };
        $mkE(['title' => 'Domain renewal', 'category' => 'hosting', 'amount' => 9000, 'spent_on' => now()->subDays(12)->toDateString(), 'project_id' => $web->id, 'vendor' => 'Namecheap', 'method' => 'card', 'reference' => 'NC-7788']);
        $mkE(['title' => 'Mumbai site visit', 'category' => 'travel', 'amount' => 12500, 'spent_on' => now()->subDays(8)->toDateString(), 'project_id' => $shop->id, 'vendor' => 'IndiGo', 'method' => 'upi', 'notes' => 'Flight + cab.']);
        $mkE(['title' => 'Figma seats (annual)', 'category' => 'software', 'amount' => 22000, 'spent_on' => now()->subDays(30)->toDateString(), 'vendor' => 'Figma', 'method' => 'card']);
        $mkE(['title' => 'Freelancer payout — illustrations', 'category' => 'salary', 'amount' => 18000, 'spent_on' => now()->subDays(5)->toDateString(), 'project_id' => $web->id, 'method' => 'bank', 'reference' => 'NEFT112233']);
        $mkE(['title' => 'Meta ads — Diwali push', 'category' => 'marketing', 'amount' => 15000, 'spent_on' => now()->subDays(2)->toDateString(), 'method' => 'card']);
        $mkE(['title' => 'Printer paper + ink', 'category' => 'office', 'amount' => 2400, 'spent_on' => $today, 'vendor' => 'Local store', 'method' => 'cash']);

        // attach a receipt file to the first expense
        $estim = Expense::orderBy('id')->first();
        Storage::disk('public')->put('receipts/dummy-receipt-1.txt', "DUMMY RECEIPT\nDomain renewal Rs.9000\nRef NC-7788\n");
        $estim->update(['receipt_path' => 'receipts/dummy-receipt-1.txt']);

        // ---------- documents (every folder, every badge type) ----------
        $put = function (string $name, string $content, $linkable = null) {
            $path = 'documents/'.$name;
            Storage::disk('public')->put($path, $content);

            return Document::create([
                'name' => $name, 'path' => $path,
                'mime' => match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
                    'pdf' => 'application/pdf', 'csv' => 'text/csv', 'png' => 'image/png',
                    default => 'text/plain',
                },
                'size' => Storage::disk('public')->size($path),
                'linkable_type' => $linkable ? Project::class : null,
                'linkable_id' => $linkable?->id, 'uploaded_by' => 1,
            ]);
        };
        $put('acme-requirements.pdf', "%PDF-1.4\n%Dummy PDF for seeding.\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF", $web);
        $put('pos-quote.xlsx', "Store,Qty,Price\nAndheri,2,22000\nBandra,1,22000\n", $shop);
        $put('rate-card-2026.csv', "Service,Rate\nDesign,3000\nDevelopment,1200\n", null);
        $put('meeting-notes.txt', "DUMMY NOTES\n- Confirm scope\n- Advance received\n", $web);

        $this->command->info('Dummy data seeded: '.Client::count().' clients, '.Project::count().' projects, '.Task::count().' tasks, '.Invoice::count().' invoices, '.Payment::count().' payments, '.Expense::count().' expenses, '.Document::count().' documents.');
    }

    protected function wipe(): void
    {
        Payment::query()->delete();
        Invoice::query()->delete();
        DB::table('invoice_items')->delete();
        Task::query()->delete();
        Expense::query()->delete();
        Document::query()->delete();
        Project::query()->delete();
        Client::query()->delete();
        foreach (['invoice_items', 'payments', 'invoices', 'tasks', 'expenses', 'documents', 'projects', 'clients'] as $t) {
            try {
                DB::statement("DELETE FROM sqlite_sequence WHERE name='{$t}'");
            } catch (\Throwable $e) {
            }
        }
        foreach (Storage::disk('public')->files('documents') as $f) {
            Storage::disk('public')->delete($f);
        }
        foreach (Storage::disk('public')->files('receipts') as $f) {
            Storage::disk('public')->delete($f);
        }
        foreach (Storage::disk('public')->files('signatures') as $f) {
            Storage::disk('public')->delete($f);
        }
    }
}
