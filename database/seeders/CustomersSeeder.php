<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomersSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name' => 'Alaa Abd El-Hakam',
                'email' => 'alaa@example.com',
                'mobile' => '201234567890',
            ],
            [
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed@example.com',
                'mobile' => '201111111111',
            ],
            [
                'name' => 'Sara Mohamed',
                'email' => 'sara@example.com',
                'mobile' => '201222222222',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['email' => $customer['email']],
                $customer
            );
        }
    }
}