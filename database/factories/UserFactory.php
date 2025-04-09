<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => $this->faker->userName(),
            'password' => Hash::make('password'),
            'nama_puskesmas' => $this->faker->company(),
            'isadmin' => false,
            'dinas_id' => null,
            'remember_token' => Str::random(10),
        ];
    }
}