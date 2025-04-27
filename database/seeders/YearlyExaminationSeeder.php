<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Services\StatisticsCacheService;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Carbon;

class YearlyExaminationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        
        // Tahun yang akan dibuat data pemeriksaan
        $years = [2024, 2025, 2026];
        
        foreach ($years as $year) {
            $this->command->info("Membuat data pemeriksaan untuk tahun {$year}...");
            
            // Buat data pemeriksaan HT
            $this->createHtExaminations($faker, $year);
            
            // Buat data pemeriksaan DM
            $this->createDmExaminations($faker, $year);
        }
        
        // Rebuild cache setelah semua data selesai
        $this->command->info('Building statistics cache...');
        $cacheService = app(StatisticsCacheService::class);
        $cacheService->rebuildAllCache();
        $this->command->info('Statistics cache built successfully.');
    }
    
    /**
     * Buat data pemeriksaan HT untuk tahun tertentu
     */
    private function createHtExaminations($faker, $year)
    {
        // Ambil semua pasien secara acak untuk ditambahkan pemeriksaan
        $allPatients = Patient::inRandomOrder()->limit(200)->get();
        
        if ($allPatients->isEmpty()) {
            $this->command->info("Tidak ada pasien yang tersedia untuk pemeriksaan HT di tahun {$year}.");
            return;
        }
        
        // Filter pasien yang belum memiliki pemeriksaan di tahun ini
        $patientsToAdd = $allPatients->filter(function($patient) use ($year) {
            // Ambil array tahun dengan aman
            $htYears = $this->safeGetYears($patient->ht_years);
            return !in_array($year, $htYears);
        })->take(100);
        
        if ($patientsToAdd->isEmpty()) {
            $this->command->info("Tidak ada pasien yang perlu ditambahkan untuk pemeriksaan HT di tahun {$year}.");
            return;
        }
        
        $count = 0;
        
        foreach ($patientsToAdd as $patient) {
            // 70% kemungkinan pasien akan memiliki pemeriksaan HT di tahun ini
            if ($faker->boolean(70)) {
                // Tambahkan tahun ke array ht_years pasien
                $patient->addHtYear($year);
                $patient->save();
                
                // Decide if patient will be standard or non-standard
                $isStandard = rand(1, 100) <= 70; // 70% chance to be standard
                
                // Determine first visit month
                $firstMonth = rand(1, 8); // Start between January and August
                
                if ($isStandard) {
                    // Standard patient: visits every month from first visit to December
                    for ($month = $firstMonth; $month <= 12; $month++) {
                        $this->createHtExamination($patient, $year, $month);
                        $count++;
                    }
                } else {
                    // Non-standard patient: skips some months
                    for ($month = $firstMonth; $month <= 12; $month++) {
                        // 70% chance to visit in each month
                        if (rand(1, 100) <= 70) {
                            $this->createHtExamination($patient, $year, $month);
                            $count++;
                        }
                    }
                }
            }
        }
        
        $this->command->info("Berhasil membuat {$count} data pemeriksaan HT untuk tahun {$year}.");
    }
    
    /**
     * Buat data pemeriksaan DM untuk tahun tertentu
     */
    private function createDmExaminations($faker, $year)
    {
        // Ambil semua pasien secara acak untuk ditambahkan pemeriksaan
        $allPatients = Patient::inRandomOrder()->limit(200)->get();
        
        if ($allPatients->isEmpty()) {
            $this->command->info("Tidak ada pasien yang tersedia untuk pemeriksaan DM di tahun {$year}.");
            return;
        }
        
        // Filter pasien yang belum memiliki pemeriksaan di tahun ini
        $patientsToAdd = $allPatients->filter(function($patient) use ($year) {
            // Ambil array tahun dengan aman
            $dmYears = $this->safeGetYears($patient->dm_years);
            return !in_array($year, $dmYears);
        })->take(100);
        
        if ($patientsToAdd->isEmpty()) {
            $this->command->info("Tidak ada pasien yang perlu ditambahkan untuk pemeriksaan DM di tahun {$year}.");
            return;
        }
        
        $count = 0;
        
        foreach ($patientsToAdd as $patient) {
            // 70% kemungkinan pasien akan memiliki pemeriksaan DM di tahun ini
            if ($faker->boolean(70)) {
                // Tambahkan tahun ke array dm_years pasien
                $patient->addDmYear($year);
                $patient->save();
                
                // Decide if patient will be standard or non-standard
                $isStandard = rand(1, 100) <= 70; // 70% chance to be standard
                
                // Determine first visit month
                $firstMonth = rand(1, 8); // Start between January and August
                
                if ($isStandard) {
                    // Standard patient: visits every month from first visit to December
                    for ($month = $firstMonth; $month <= 12; $month++) {
                        $this->createDmExamination($patient, $year, $month);
                        $count++;
                    }
                } else {
                    // Non-standard patient: skips some months
                    for ($month = $firstMonth; $month <= 12; $month++) {
                        // 70% chance to visit in each month
                        if (rand(1, 100) <= 70) {
                            $this->createDmExamination($patient, $year, $month);
                            $count++;
                        }
                    }
                }
            }
        }
        
        $this->command->info("Berhasil membuat {$count} data pemeriksaan DM untuk tahun {$year}.");
    }
    
    /**
     * Create HT examination for a specific month
     */
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
    
    /**
     * Create DM examination for a specific month
     */
    private function createDmExamination(Patient $patient, int $year, int $month): void
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
                'hba1c' => rand(50, 100) / 10, // 5.0 - 10.0
                'gdp' => rand(80, 200),
                'gd2jpp' => rand(100, 250),
                'gdsp' => rand(100, 200),
                default => rand(80, 200),
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
        // If it's null, return empty array
        if (is_null($years)) {
            return [];
        }
        
        // If it's already an array, return it
        if (is_array($years)) {
            return $years;
        }
        
        // If it's a string, try to decode it
        if (is_string($years)) {
            $decoded = json_decode($years, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        
        // Default fallback
        return [];
    }
}