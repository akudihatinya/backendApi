<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Services\StatisticsCacheService;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Carbon;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Menggunakan locale Indonesia
        
        // Ambil semua puskesmas
        $puskesmas = Puskesmas::all();
        
        if ($puskesmas->isEmpty()) {
            $this->command->error('Tidak ada puskesmas! Jalankan PuskesmasSeeder terlebih dahulu.');
            return;
        }
        
        // Tahun pemeriksaan
        $years = [2024, 2025, 2026];
        
        // Buat 200 pasien yang didistribusikan ke semua puskesmas
        $this->command->info('Membuat data pasien...');
        $count = 0;
        
        foreach ($puskesmas as $puskesmas) {
            // Buat 20-40 pasien per puskesmas
            $numPatients = rand(20, 40);
            
            for ($i = 0; $i < $numPatients; $i++) {
                $gender = $faker->randomElement(['male', 'female']);
                $birthDate = $faker->dateTimeBetween('-80 years', '-20 years');
                $age = Carbon::parse($birthDate)->age;
                
                // Tentukan secara random apakah pasien memiliki HT, DM, atau keduanya
                $hasHt = $faker->boolean(70); // 70% kemungkinan memiliki HT
                $hasDm = $faker->boolean(60); // 60% kemungkinan memiliki DM
                
                // Pastikan setidaknya satu kondisi yang dimiliki
                if (!$hasHt && !$hasDm) {
                    $random = rand(1, 2);
                    if ($random === 1) {
                        $hasHt = true;
                    } else {
                        $hasDm = true;
                    }
                }
                
                // Tidak semua pasien memiliki NIK atau BPJS
                $hasNik = $faker->boolean(80); // 80% kemungkinan memiliki NIK
                $hasBpjs = $faker->boolean(70); // 70% kemungkinan memiliki BPJS
                
                // Buat array tahun pemeriksaan untuk HT dan DM
                $htYears = [];
                $dmYears = [];
                
                if ($hasHt) {
                    // Pilih beberapa tahun acak untuk pemeriksaan HT
                    $numYears = rand(1, count($years));
                    
                    // Pilih tahun-tahun acak
                    $selectedYears = $years;
                    shuffle($selectedYears);
                    $htYears = array_slice($selectedYears, 0, $numYears);
                }
                
                if ($hasDm) {
                    // Pilih beberapa tahun acak untuk pemeriksaan DM
                    $numYears = rand(1, count($years));
                    
                    // Pilih tahun-tahun acak
                    $selectedYears = $years;
                    shuffle($selectedYears);
                    $dmYears = array_slice($selectedYears, 0, $numYears);
                }
                
                $patient = Patient::create([
                    'puskesmas_id' => $puskesmas->id,
                    'nik' => $hasNik ? $this->generateNIK($faker) : null,
                    'bpjs_number' => $hasBpjs ? $this->generateBPJS($faker) : null,
                    'medical_record_number' => 'RM-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'name' => $gender === 'male' ? $faker->firstNameMale . ' ' . $faker->lastName : $faker->firstNameFemale . ' ' . $faker->lastName,
                    'address' => $faker->boolean(90) ? $faker->address : null, // 90% memiliki alamat
                    'gender' => $gender,
                    'birth_date' => $faker->boolean(95) ? $birthDate : null, // 95% memiliki tanggal lahir
                    'age' => $faker->boolean(80) ? $age : null, // 80% memiliki umur
                    'ht_years' => $htYears,
                    'dm_years' => $dmYears,
                    'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                    'updated_at' => now(),
                ]);
                
                // Buat pemeriksaan untuk setiap tahun HT
                foreach ($htYears as $year) {
                    $this->createHtExaminationPattern($patient, $year);
                }
                
                // Buat pemeriksaan untuk setiap tahun DM
                foreach ($dmYears as $year) {
                    $this->createDmExaminationPattern($patient, $year);
                }
                
                $count++;
            }
        }
        
        $this->command->info("Berhasil membuat {$count} data pasien dengan pemeriksaan HT/DM untuk tahun 2024-2026.");
        
        // Rebuild cache setelah semua data selesai
        $this->command->info('Building statistics cache...');
        $cacheService = app(StatisticsCacheService::class);
        $cacheService->rebuildAllCache();
        $this->command->info('Statistics cache built successfully.');
    }
    
    /**
     * Create HT examination pattern for standard and non-standard patients
     */
    private function createHtExaminationPattern(Patient $patient, int $year): void
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
    
    /**
     * Create DM examination pattern for standard and non-standard patients
     */
    private function createDmExaminationPattern(Patient $patient, int $year): void
    {
        // Decide if patient will be standard or non-standard
        $isStandard = rand(1, 100) <= 70; // 70% chance to be standard
        
        // Determine first visit month
        $firstMonth = rand(1, 8); // Start between January and August
        
        if ($isStandard) {
            // Standard patient: visits every month from first visit to December
            for ($month = $firstMonth; $month <= 12; $month++) {
                $this->createDmExamination($patient, $year, $month);
            }
        } else {
            // Non-standard patient: skips some months
            for ($month = $firstMonth; $month <= 12; $month++) {
                // 70% chance to visit in each month
                if (rand(1, 100) <= 70) {
                    $this->createDmExamination($patient, $year, $month);
                }
            }
        }
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
     * Generate random NIK (16 digits).
     */
    private function generateNIK($faker): string
    {
        // Format NIK: PPRRSSDDMMYYXXXX
        // PP = Kode Provinsi (2 digit)
        // RR = Kode Kabupaten/Kota (2 digit)
        // SS = Kode Kecamatan (2 digit)
        // DDMMYY = Tanggal Lahir (dengan pengecualian untuk wanita, DD+40)
        // XXXX = Nomor Urut (4 digit)
        
        $provinceCode = str_pad(rand(11, 94), 2, '0', STR_PAD_LEFT); // Kode provinsi Indonesia
        $cityCode = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        $districtCode = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        
        $day = rand(1, 28);
        $month = rand(1, 12);
        $year = rand(40, 99); // Tahun 1940-1999
        
        $sequence = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $provinceCode . $cityCode . $districtCode . 
               str_pad($day, 2, '0', STR_PAD_LEFT) . 
               str_pad($month, 2, '0', STR_PAD_LEFT) . 
               $year . $sequence;
    }
    
    /**
     * Generate random BPJS number (13 digits).
     */
    private function generateBPJS($faker): string
    {
        // BPJS number is typically 13 digits
        $prefix = rand(0, 9);
        $number = str_pad(rand(1, 999999999999), 12, '0', STR_PAD_LEFT);
        
        return $prefix . $number;
    }
}