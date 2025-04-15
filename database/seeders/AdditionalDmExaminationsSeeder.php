<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\DmExamination;
use Illuminate\Support\Carbon;
use Faker\Factory as Faker;

class AdditionalDmExaminationsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Ambil semua pasien dari puskesmas ID 1
        $patients = Patient::where('puskesmas_id', 1)->get();

        if ($patients->isEmpty()) {
            $this->command->error("Tidak ada pasien dari Puskesmas ID 1.");
            return;
        }

        $count = 0;

        foreach ($patients as $patient) {
            // Pilih tanggal pemeriksaan sama untuk semua hasil DM
            $examinationDate = Carbon::create(2025, rand(1, 12), rand(1, 28));

            // Jenis pemeriksaan DM
            $types = ['gdp', 'gd2jpp', 'gdsp', 'hba1c'];

            foreach ($types as $type) {
                $result = match ($type) {
                    'hba1c' => $faker->randomFloat(1, 5.0, 10.0),
                    'gdp' => $faker->numberBetween(70, 200),
                    'gd2jpp' => $faker->numberBetween(90, 300),
                    'gdsp' => $faker->numberBetween(90, 250),
                    default => $faker->numberBetween(70, 200),
                };

                DmExamination::create([
                    'patient_id' => $patient->id,
                    'puskesmas_id' => 1,
                    'examination_date' => $examinationDate,
                    'examination_type' => $type,
                    'result' => $result,
                    'year' => $examinationDate->year,
                    'month' => $examinationDate->month,
                    'is_archived' => false,
                ]);

                $count++;
            }
        }

        $this->command->info("Berhasil menambahkan {$count} pemeriksaan DM tambahan untuk pasien dari Puskesmas ID 1.");
    }
}
