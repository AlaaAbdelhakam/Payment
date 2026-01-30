<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['id' => 2,  'code' => 'visa',  'name' => 'Visa / MasterCard'],
            ['id' => 6,  'code' => 'mada',  'name' => 'Mada'],
            ['id' => 11, 'code' => 'apple', 'name' => 'Apple Pay'],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['id' => $method['id']],
                [
                    'code'       => $method['code'],
                    'name'       => $method['name'],
                    'gateway'    => 'myfatoorah',
                    'is_active'  => true,
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]
            );
        }
    }// the seeder just for visualization for front end card types show
}