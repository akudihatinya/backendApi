<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Carbon;

class Puskesmas1HT2025Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Ambil puskesmas pertama (puskesmas1)
        $puskesmas = Puskesmas::where('name', 'Puskesmas 1')->first();

        if (!$puskesmas) {
            $this->command->error('Puskesmas dengan nama "puskesmas1" tidak ditemukan!');
            return;
        }

        $this->command->info('Menambahkan pasien HT di puskesmas1 untuk tahun 2025...');

        $count = 0;

        for ($i = 0; $i < 20; $i++) {
            $gender = $faker->randomElement(['male', 'female']);
            $birthDate = $faker->dateTimeBetween('-70 years', '-20 years');
            $age = Carbon::parse($birthDate)->age;

            Patient::create([
                'puskesmas_id' => $puskesmas->id,
                'nik' => $faker->boolean(80) ? $this->generateNIK($faker) : null,
                'bpjs_number' => $faker->boolean(70) ? $this->generateBPJS($faker) : null,
                'name' => $gender === 'male' ? $faker->firstNameMale . ' ' . $faker->lastName : $faker->firstNameFemale . ' ' . $faker->lastName,
                'address' => $faker->address,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'age' => $age,
                'has_ht' => true,
                'has_dm' => false,
                'created_at' => $faker->dateTimeBetween('2025-01-01', '2025-12-31'),
                'updated_at' => now(),
            ]);

            $count++;
        }

        $this->command->info("Berhasil menambahkan {$count} pasien HT untuk puskesmas1 di tahun 2025.");
    }

    private function generateNIK($faker): string
    {
        $provinceCode = str_pad(rand(11, 94), 2, '0', STR_PAD_LEFT);
        $cityCode = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        $districtCode = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);

        $day = rand(1, 28);
        $month = rand(1, 12);
        $year = rand(40, 99); // 1940-1999

        $sequence = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        return $provinceCode . $cityCode . $districtCode .
            str_pad($day, 2, '0', STR_PAD_LEFT) .
            str_pad($month, 2, '0', STR_PAD_LEFT) .
            $year . $sequence;
    }

    private function generateBPJS($faker): string
    {
        $prefix = rand(0, 9);
        $number = str_pad(rand(1, 999999999999), 12, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }
}
