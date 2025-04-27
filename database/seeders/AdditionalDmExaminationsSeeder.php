<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Patient;
use App\Models\DmExamination;
use App\Services\StatisticsCacheService;
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
            // Pastikan pasien memiliki DM
            $dmYears = $this->safeGetYears($patient->dm_years);
            
            if (!in_array(2025, $dmYears)) {
                $patient->addDmYear(2025);
                $patient->save();
            }

            // Create examination pattern
            $this->createExaminationPattern($patient, 2025);
            $count++;
        }

        $this->command->info("Berhasil menambahkan pemeriksaan DM untuk {$count} pasien dari Puskesmas ID 1.");
        
        // Rebuild cache setelah semua data selesai
        $this->command->info('Building statistics cache...');
        $cacheService = app(StatisticsCacheService::class);
        $cacheService->rebuildAllCache();
        $this->command->info('Statistics cache built successfully.');
    }
    
    private function createExaminationPattern(Patient $patient, int $year): void
    {
        $faker = Faker::create('id_ID');
        
        // Decide if patient will be standard or non-standard
        $isStandard = rand(1, 100) <= 70; // 70% chance to be standard
        
        // Determine first visit month
        $firstMonth = rand(1, 8); // Start between January and August
        
        if ($isStandard) {
            // Standard patient: visits every month from first visit to December
            for ($month = $firstMonth; $month <= 12; $month++) {
                $this->createDmExamination($patient, $year, $month, $faker);
            }
        } else {
            // Non-standard patient: skips some months
            for ($month = $firstMonth; $month <= 12; $month++) {
                // 70% chance to visit in each month
                if (rand(1, 100) <= 70) {
                    $this->createDmExamination($patient, $year, $month, $faker);
                }
            }
        }
    }
    
    private function createDmExamination(Patient $patient, int $year, int $month, $faker): void
    {
        $day = rand(1, min(28, Carbon::createFromDate($year, $month, 1)->daysInMonth));
        $date = Carbon::createFromDate($year, $month, $day);
        
        // Create multiple DM examination types for the same date
        $examTypes = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
        
        // Randomly select 1-4 examination types
        $selectedTypes = array_rand(array_flip($examTypes), rand(1, 4));
        if (!is_array($selectedTypes)) {
            $selectedTypes = [$selectedTypes];
        }
        
        foreach ($selectedTypes as $examType) {
            $result = match($examType) {
                'hba1c' => $faker->randomFloat(1, 5.0, 10.0),
                'gdp' => $faker->numberBetween(70, 200),
                'gd2jpp' => $faker->numberBetween(90, 300),
                'gdsp' => $faker->numberBetween(90, 250),
                default => $faker->numberBetween(70, 200),
            };
            
            DmExamination::create([
                'patient_id' => $patient->id,
                'puskesmas_id' => $patient->puskesmas_id,
                'examination_date' => $date,
                'examination_type' => $examType,
                'result' => $result,
                'year' => $year,
                'month' => $month,
                'is_archived' => false,
            ]);
        }
    }
    
    /**
     * Safely get years array from various possible formats
     */
    private function safeGetYears($years)
    {
        if (is_null($years)) {
            return [];
        }
        
        if (is_array($years)) {
            return $years;
        }
        
        if (is_string($years)) {
            $decoded = json_decode($years, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        return [];
    }
}