<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'username' => 'admin',
            'password' => Hash::make('password'),
            'name' => 'Admin Dinas Kesehatan',
            'role' => 'admin',
        ]);
    }
}