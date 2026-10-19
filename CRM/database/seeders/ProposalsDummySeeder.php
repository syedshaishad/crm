<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Clients\Client;
use Modules\Invoices\Invoice;
use Modules\Proposals\Proposal;

class ProposalsDummySeeder extends Seeder
{
    public function run(): void
    {
        $clients = Client::orderBy('id')->get();
        if ($clients->count() < 3) {
            $this->command->error('Need at least 3 clients. Run DummyDataSeeder first.');

            return;
        }
        [$c1, $c2, $c3] = [$clients[0], $clients[1], $clients[2]];
        $admin = User::first()->id ?? 1;

        $make = function (array $head, array $lines) use ($admin) {
            static $n = 0;
            $n++;
            $p = Proposal::create(array_merge([
                'number' => 'PROP-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
                'status' => 'draft', 'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(15)->toDateString(),
                'gst_rate' => 18, 'discount' => 0,
                'sign_token' => Str::random(32),
                'created_by' => $admin, 'assigned_to' => $admin,
            ], $head));
            foreach ($lines as $l) {
                $p->items()->create([
                    'description' => $l[0], 'qty' => $l[1], 'unit' => $l[2] ?? 'NOS',
                    'rate' => $l[3], 'tax_rate' => $l[4] ?? null, 'hsn_code' => $l[5] ?? '9983',
                    'amount' => round($l[1] * $l[3], 2),
                ]);
            }
            $p->recalc();

            return $p->fresh();
        };

        // 1. Draft — user can edit + send
        $make(
            ['client_id' => $c1->id, 'notes' => 'Revamp with new catalogue + payment gateway.', 'terms' => "50% advance to start work.\nBalance on go-live.\nValid for 15 days."],
            [['Homepage + catalogue redesign', 1, 'JOB', 35000, 18], ['Payment gateway integration', 1, 'JOB', 12000, 18], ['1-month support', 1, 'MTH', 5000, 18]]
        );

        // 2. Sent — user can open the signing link and sign
        $make(
            ['client_id' => $c2->id, 'status' => 'sent', 'notes' => 'Two billing counters + barcode printers.', 'terms' => "Hardware billed at actuals.\nTraining included for 4 staff."],
            [['POS billing software (2 outlets)', 2, 'LIC', 22000, 18], ['Barcode printer setup', 2, 'JOB', 3500, 18], ['Staff training', 4, 'HRS', 1200, 18]]
        );

        // 3. Accepted (signed) — user can hit Convert to invoice right away
        $make(
            ['client_id' => $c3->id, 'status' => 'accepted', 'signed_by' => 'Priya Nair', 'signed_at' => now()->subDay(), 'notes' => 'Festive-season campaign creatives.'],
            [['Brand kit + logo refresh', 1, 'JOB', 25000, 18], ['30 social creatives', 30, 'NOS', 800, 18], ['Ad account setup', 1, 'JOB', 6000, 18]]
        );

        // 4. Declined
        $make(
            ['client_id' => $c1->id, 'status' => 'declined', 'notes' => 'Client went with a cheaper vendor.'],
            [['Annual maintenance contract', 12, 'MTH', 4000, 18]]
        );

        // 5. Expired (sent + past validity → shows Expired)
        $make(
            ['client_id' => $c2->id, 'status' => 'sent', 'issue_date' => now()->subDays(30)->toDateString(), 'valid_until' => now()->subDays(15)->toDateString()],
            [['GST filing automation', 1, 'JOB', 18000, 18]]
        );

        // 6. Invoiced — full chain: signed proposal already converted to a draft invoice
        $conv = $make(
            ['client_id' => $c3->id, 'status' => 'accepted', 'signed_by' => 'Kiran Rao', 'signed_at' => now()->subDays(2)],
            [['Inventory audit + cleanup', 1, 'JOB', 15000, 18], ['Monthly reporting setup', 1, 'JOB', 9000, 18]]
        );
        $inv = Invoice::create([
            'number' => 'INV-'.str_pad((string) ((Invoice::withTrashed()->max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
            'client_id' => $conv->client_id, 'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(), 'status' => 'draft',
            'gst_rate' => $conv->gst_rate, 'notes' => 'Converted from proposal '.$conv->number.'.',
            'discount' => $conv->discount, 'created_by' => $admin, 'assigned_to' => $admin,
        ]);
        foreach ($conv->items as $it) {
            $inv->items()->create(['description' => $it->description, 'hsn_code' => $it->hsn_code, 'unit' => $it->unit, 'tax_rate' => $it->tax_rate, 'qty' => $it->qty, 'rate' => $it->rate, 'amount' => $it->amount]);
        }
        $inv->recalc();
        $conv->update(['converted_invoice_id' => $inv->id, 'status' => 'invoiced']);

        $this->command->info('Seeded '.Proposal::count().' proposals + 1 linked invoice ('.$inv->number.').');
    }
}

