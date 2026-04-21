<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Client::query()->updateOrCreate(
            ['code' => 'jneibb'],
            [
                'name' => 'JNE IBB',
                'email' => 'billing-jneibb@example.com',
                'is_active' => true,
                'currency' => 'IDR',
                'default_due_days' => 14,
                'plan_name' => 'Enterprise',
                'usage_xlsx_path' => '/var/www/api.skyware.systems/storage/app/client_usage/jneibb_usage.xlsx',
            ]
        );

        Client::query()->updateOrCreate(
            ['code' => 'sample1'],
            [
                'name' => 'Sample Client 1',
                'email' => 'billing-sample1@example.com',
                'is_active' => true,
                'currency' => 'IDR',
                'default_due_days' => 14,
                'plan_name' => 'Standard',
            ]
        );
    }
}
