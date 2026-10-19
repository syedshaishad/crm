<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Leads\Lead;

class LeadsDummySeeder extends Seeder
{
    public function run(): void
    {
        $L = fn (array $a) => Lead::create($a);
        $L(['name' => 'Ravi Kumar', 'company' => 'Ravi Textiles', 'phone' => '9848011111', 'email' => 'ravi@ravitextiles.test', 'city' => 'Hyderabad', 'state' => 'Telangana', 'source' => 'indiamart', 'stage' => 'new', 'value' => 45000, 'follow_up_on' => now()->addDays(2)->toDateString(), 'assigned_to' => 2, 'notes' => 'Wants billing software for 2 outlets.']);
        $L(['name' => 'Sneha Patel', 'company' => 'Sneha Boutique', 'phone' => '9898012345', 'email' => 'sneha@boutique.test', 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'source' => 'justdial', 'stage' => 'contacted', 'value' => 18000, 'follow_up_on' => now()->subDays(1)->toDateString(), 'assigned_to' => 2]);
        $L(['name' => 'Vikram Singh', 'phone' => '9811011223', 'city' => 'Jaipur', 'state' => 'Rajasthan', 'source' => 'google', 'stage' => 'qualified', 'value' => 60000, 'follow_up_on' => now()->addDays(5)->toDateString(), 'assigned_to' => 3, 'notes' => 'Hot — asked for quotation.']);
        $L(['name' => 'Anita Desai', 'company' => 'Desai Foods', 'phone' => '9769012345', 'email' => 'anita@desaifoods.test', 'city' => 'Pune', 'state' => 'Maharashtra', 'source' => 'referral', 'stage' => 'proposal', 'value' => 95000, 'follow_up_on' => now()->toDateString(), 'assigned_to' => 2]);
        $L(['name' => 'Mohammed Faiz', 'company' => 'Faiz Electronics', 'phone' => '9004012345', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'source' => 'walkin', 'stage' => 'won', 'value' => 32000, 'assigned_to' => 2]);
        $L(['name' => 'Deepak Yadav', 'phone' => '9123012345', 'source' => 'coldcall', 'stage' => 'lost', 'value' => 12000, 'notes' => 'Budget too low, revisit next quarter.']);
        $L(['name' => 'Pooja Reddy', 'company' => 'PR Events', 'phone' => '9849012345', 'email' => 'pooja@prevents.test', 'city' => 'Hyderabad', 'state' => 'Telangana', 'source' => 'instagram', 'stage' => 'new', 'value' => 25000, 'follow_up_on' => now()->addDays(1)->toDateString()]);
        $L(['name' => 'Arjun Menon', 'company' => 'Menon Fisheries', 'phone' => '9847012345', 'city' => 'Kochi', 'state' => 'Kerala', 'source' => 'whatsapp', 'stage' => 'contacted', 'value' => 40000, 'assigned_to' => 3]);
        $this->command->info('Seeded '.Lead::count().' leads.');
    }
}
