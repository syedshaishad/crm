<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Generic demo data only — replace with the customer's own branding via installer/Settings.
        DB::table('settings')->updateOrInsert(['key' => 'app.name'], ['value' => 'Demo CRM', 'group' => 'brand', 'updated_at' => now(), 'created_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'brand.company_name'], ['value' => 'Demo Company', 'group' => 'brand', 'updated_at' => now(), 'created_at' => now()]);
        DB::table('settings')->updateOrInsert(['key' => 'app.version'], ['value' => '1.0.0', 'group' => 'system', 'updated_at' => now(), 'created_at' => now()]);
    }
}
