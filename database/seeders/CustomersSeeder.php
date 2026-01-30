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
                'name' => 'Alaa Abd ElHakam',
                'email' => 'alaa@example.com',
                'mobile' => '51234567',
            ],
            [
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed@example.com',
                'mobile' => '51214567',
            ],
            [
                'name' => 'Sara Mohamed',
                'email' => 'sara@example.com',
                'mobile' => '51284567',
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