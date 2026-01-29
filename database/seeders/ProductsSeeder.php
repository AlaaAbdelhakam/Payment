<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Basic Subscription',
                'price' => 10.00,
                'is_active' => true,
            ],
            [
                'name' => 'Pro Subscription',
                'price' => 25.00,
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise Subscription',
                'price' => 99.00,
                'is_active' => true,
            ],
            [
                'name' => 'One-Time Setup Fee',
                'price' => 50.00,
                'is_active' => true,
            ],
            [
                'name' => 'Archived / Disabled Product',
                'price' => 5.00,
                'is_active' => false,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['name' => $product['name']],
                $product
            );
        }
    }
}