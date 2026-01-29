<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RootUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'root@system.com'],
            [
                'name' => 'Root',
                'password' => Hash::make('123456'),
            ]
        );
    }
}