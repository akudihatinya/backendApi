<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PuskesmasSeeder extends Seeder
{
    public function run(): void
    {
        $puskesmasNames = [
            'Puskesmas 1', 'Puskesmas 2', 'Puskesmas 3', 'Puskesmas 4', 'Puskesmas 5',
            'Puskesmas 6', 'Puskesmas 7', 'Puskesmas 8', 'Puskesmas 9', 'Puskesmas 10',
            'Puskesmas 11', 'Puskesmas 12', 'Puskesmas 13', 'Puskesmas 14', 'Puskesmas 15',
            'Puskesmas 16', 'Puskesmas 17', 'Puskesmas 18', 'Puskesmas 19', 'Puskesmas 20',
            'Puskesmas 21', 'Puskesmas 22', 'Puskesmas 23', 'Puskesmas 24', 'Puskesmas 25',
        ];

        foreach ($puskesmasNames as $index => $name) {
            $user = User::create([
                'username' => strtolower(str_replace(' ', '', $name)),
                'password' => Hash::make('password'),
                'name' => $name,
                'role' => 'puskesmas',
            ]);

            Puskesmas::create([
                'user_id' => $user->id,
                'name' => $name,
            ]);
        }
    }
}