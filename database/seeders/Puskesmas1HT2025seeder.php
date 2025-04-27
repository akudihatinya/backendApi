<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\HtExamination;
use App\Services\StatisticsCacheService;
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
            $this->command->error('Puskesmas dengan nama "Puskesmas 1" tidak ditemukan!');
            return;
        }

        $this->command->info('Menambahkan pasien HT di Puskesmas 1 untuk tahun 2025...');

        $count = 0;
        $year = 2025; // Tahun pemeriksaan yang spesifik

        for ($i = 0; $i < 30; $i++) {
            $gender = $faker->randomElement(['male', 'female']);
            $birthDate = $faker->dateTimeBetween('-70 years', '-20 years');
            $age = Carbon::parse($birthDate)->age;

            $patient = Patient::create([
                'puskesmas_id' => $puskesmas->id,
                'nik' => $faker->boolean(80) ? $this->generateNIK($faker) : null,
                'bpjs_number' => $faker->boolean(70) ? $this->generateBPJS($faker) : null,
                'medical_record_number' => 'RM-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'name' => $gender === 'male' ? $faker->firstNameMale . ' ' . $faker->lastName : $faker->firstNameFemale . ' ' . $faker->lastName,
                'address' => $faker->address,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'age' => $age,
                'ht_years' => [$year], // Hanya tahun 2025
                'dm_years' => [], // Tidak ada DM
                'created_at' => $faker->dateTimeBetween("$year-01-01", "$year-12-31"),
                'updated_at' => now(),
            ]);

            // Create examination pattern
            $this->createExaminationPattern($patient, $year);
            $count++;
        }

        $this->command->info("Berhasil menambahkan {$count} pasien HT untuk Puskesmas 1 di tahun 2025.");
        
        // Rebuild cache setelah semua data selesai
        $this->command->info('Building statistics cache...');
        $cacheService = app(StatisticsCacheService::class);
        $cacheService->rebuildAllCache();
        $this->command->info('Statistics cache built successfully.');
    }

    private function createExaminationPattern(Patient $patient, int $year): void
    {
        // Decide if patient will be standard or non-standard
        $isStandard = rand(1, 100) <= 70; // 70% chance to be standard
        
        // Determine first visit month
        $firstMonth = rand(1, 8); // Start between January and August
        
        if ($isStandard) {
            // Standard patient: visits every month from first visit to December
            for ($month = $firstMonth; $month <= 12; $month++) {
                $this->createHtExamination($patient, $year, $month);
            }
        } else {
            // Non-standard patient: skips some months
            for ($month = $firstMonth; $month <= 12; $month++) {
                // 70% chance to visit in each month
                if (rand(1, 100) <= 70) {
                    $this->createHtExamination($patient, $year, $month);
                }
            }
        }
    }

    private function createHtExamination(Patient $patient, int $year, int $month): void
    {
        $day = rand(1, min(28, Carbon::createFromDate($year, $month, 1)->daysInMonth));
        $date = Carbon::createFromDate($year, $month, $day);
        
        HtExamination::create([
            'patient_id' => $patient->id,
            'puskesmas_id' => $patient->puskesmas_id,
            'examination_date' => $date,
            'systolic' => rand(110, 170),
            'diastolic' => rand(70, 100),
            'year' => $year,
            'month' => $month,
            'is_archived' => false,
        ]);
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