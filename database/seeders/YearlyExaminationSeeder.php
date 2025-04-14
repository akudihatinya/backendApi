<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\HtExamination;
use App\Models\DmExamination;
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
    }
    
    /**
     * Buat data pemeriksaan HT untuk tahun tertentu
     */
    private function createHtExaminations($faker, $year)
    {
        // Ambil semua pasien secara acak untuk ditambahkan pemeriksaan
        $allPatients = Patient::inRandomOrder()->limit(100)->get();
        
        if ($allPatients->isEmpty()) {
            $this->command->info("Tidak ada pasien yang tersedia untuk pemeriksaan HT di tahun {$year}.");
            return;
        }
        
        // Filter pasien yang belum memiliki pemeriksaan di tahun ini
        $patientsToAdd = $allPatients->filter(function($patient) use ($year) {
            // Ambil array tahun dengan aman
            $htYears = $this->safeGetYears($patient->ht_years);
            return !in_array($year, $htYears);
        })->take(50);
        
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
                
                // Buat 1-4 pemeriksaan untuk tahun ini
                $numExaminations = rand(1, 4);
                
                for ($i = 0; $i < $numExaminations; $i++) {
                    $examinationDate = Carbon::createFromDate($year, rand(1, 12), rand(1, 28));
                    
                    HtExamination::create([
                        'patient_id' => $patient->id,
                        'puskesmas_id' => $patient->puskesmas_id,
                        'examination_date' => $examinationDate,
                        'systolic' => rand(110, 180),
                        'diastolic' => rand(70, 110),
                        'year' => $examinationDate->year,
                        'month' => $examinationDate->month,
                        'is_archived' => false,
                    ]);
                    
                    $count++;
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
        $allPatients = Patient::inRandomOrder()->limit(100)->get();
        
        if ($allPatients->isEmpty()) {
            $this->command->info("Tidak ada pasien yang tersedia untuk pemeriksaan DM di tahun {$year}.");
            return;
        }
        
        // Filter pasien yang belum memiliki pemeriksaan di tahun ini
        $patientsToAdd = $allPatients->filter(function($patient) use ($year) {
            // Ambil array tahun dengan aman
            $dmYears = $this->safeGetYears($patient->dm_years);
            return !in_array($year, $dmYears);
        })->take(50);
        
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
                
                // Buat 1-4 pemeriksaan untuk tahun ini
                $numExaminations = rand(1, 4);
                
                for ($i = 0; $i < $numExaminations; $i++) {
                    $examinationDate = Carbon::createFromDate($year, rand(1, 12), rand(1, 28));
                    $examinationType = $faker->randomElement(['gdp', 'gd2jpp', 'gdsp', 'hba1c']);
                    
                    // Generate result berdasarkan tipe pemeriksaan
                    $result = match($examinationType) {
                        'hba1c' => $faker->randomFloat(1, 5.0, 10.0),
                        'gdp' => $faker->numberBetween(70, 200),
                        'gd2jpp' => $faker->numberBetween(90, 300),
                        'gdsp' => $faker->numberBetween(90, 250),
                        default => $faker->numberBetween(70, 200),
                    };
                    
                    DmExamination::create([
                        'patient_id' => $patient->id,
                        'puskesmas_id' => $patient->puskesmas_id,
                        'examination_date' => $examinationDate,
                        'examination_type' => $examinationType,
                        'result' => $result,
                        'year' => $examinationDate->year,
                        'month' => $examinationDate->month,
                        'is_archived' => false,
                    ]);
                    
                    $count++;
                }
            }
        }
        
        $this->command->info("Berhasil membuat {$count} data pemeriksaan DM untuk tahun {$year}.");
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