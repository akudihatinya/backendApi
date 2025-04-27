<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\User;
use App\Models\YearlyTarget;
use App\Models\HtExamination;
use App\Models\DmExamination;
use App\Models\MonthlyStatisticsCache;
use App\Services\StatisticsCacheService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data - compatible with SQLite
        if (config('database.default') === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
            $this->truncateTables();
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $this->truncateTables();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // 1. Buat admin
        $this->createAdmin();
        
        // 2. Buat puskesmas berdasarkan screenshoot (8 puskesmas total)
        $puskesmasNames = [
            'Puskesmas 1', 'Puskesmas 2', 'Puskesmas 3', 'Puskesmas 4',
            'Puskesmas 6', 'Puskesmas 7', 'Puskesmas 9', 'Puskesmas 10'
        ];
        
        $puskesmasList = [];
        
        foreach ($puskesmasNames as $name) {
            $puskesmas = $this->createPuskesmas($name);
            $puskesmasList[] = $puskesmas;
            
            // 3. Buat target tahunan untuk setiap puskesmas
            $this->createYearlyTargets($puskesmas);
            
            // 4. Buat pasien dan pemeriksaan untuk setiap puskesmas
            $this->createPatientsForPuskesmas($puskesmas);
        }

        // 5. Rebuild cache statistik setelah semua data selesai
        $this->command->info('Building statistics cache...');
        $cacheService = app(StatisticsCacheService::class);
        $cacheService->rebuildAllCache();
        $this->command->info('Statistics cache built successfully.');
    }
    
    private function truncateTables(): void
    {
        // Order matters because of foreign key constraints
        DB::table('monthly_statistics_cache')->truncate();
        DB::table('dm_examinations')->truncate();
        DB::table('ht_examinations')->truncate();
        DB::table('yearly_targets')->truncate();
        DB::table('patients')->truncate();
        DB::table('puskesmas')->truncate();
        DB::table('users')->truncate();
    }
    
    private function createAdmin(): void
    {
        User::create([
            'username' => 'admin',
            'password' => Hash::make('password'),
            'name' => 'Admin Dinas Kesehatan',
            'role' => 'admin',
        ]);
    }
    
    private function createPuskesmas(string $name): Puskesmas
    {
        // Create user
        $user = User::create([
            'username' => strtolower(str_replace(' ', '', $name)),
            'password' => Hash::make('password'),
            'name' => $name,
            'role' => 'puskesmas',
        ]);
        
        // Create puskesmas
        $puskesmas = Puskesmas::create([
            'user_id' => $user->id,
            'name' => $name,
        ]);
        
        // Link user to puskesmas
        $user->update(['puskesmas_id' => $puskesmas->id]);
        
        return $puskesmas;
    }
    
    private function createYearlyTargets(Puskesmas $puskesmas): void
    {
        $currentYear = Carbon::now()->year;
        
        // Create HT target - untuk setiap puskesmas berbeda agar ranking bervariasi
        $htTargetValues = [
            'Puskesmas 4' => 137, // Puskesmas 4 memiliki target 137 (dari screenshot)
            'Puskesmas 6' => 97,  // Puskesmas 6 memiliki target 97 (dari screenshot)
            'default' => rand(100, 300)
        ];
        
        $htTargetValue = $htTargetValues[$puskesmas->name] ?? $htTargetValues['default'];
        
        YearlyTarget::create([
            'puskesmas_id' => $puskesmas->id,
            'disease_type' => 'ht',
            'year' => $currentYear,
            'target_count' => $htTargetValue,
        ]);
        
        // Create DM target
        $dmTargetValues = [
            'Puskesmas 4' => 137, // Menggunakan nilai yang sama untuk konsistensi
            'Puskesmas 6' => 97,
            'default' => rand(100, 300)
        ];
        
        $dmTargetValue = $dmTargetValues[$puskesmas->name] ?? $dmTargetValues['default'];
        
        YearlyTarget::create([
            'puskesmas_id' => $puskesmas->id,
            'disease_type' => 'dm',
            'year' => $currentYear,
            'target_count' => $dmTargetValue,
        ]);
    }
    
    private function createPatientsForPuskesmas(Puskesmas $puskesmas): void
    {
        $currentYear = Carbon::now()->year;
        
        // Sample names
        $maleNames = ['Budi', 'Ahmad', 'Dedi', 'Joko', 'Agus', 'Bambang', 'Eko', 'Firman', 'Hadi', 'Iwan'];
        $femaleNames = ['Ani', 'Bintang', 'Citra', 'Dewi', 'Eka', 'Fitri', 'Gita', 'Hana', 'Indah', 'Juwita'];
        $lastNames = ['Santoso', 'Wijaya', 'Kusuma', 'Hidayat', 'Nugraha', 'Saputra', 'Wibowo', 'Setiawan', 'Sugianto', 'Permana'];
        
        // Jumlah pasien berdasarkan screenshot untuk beberapa puskesmas yang terlihat
        $htCounts = [
            'Puskesmas 4' => 206, // Dari screenshot: LAKI-LAKI 206
            'Puskesmas 6' => 219, // Dari screenshot: LAKI-LAKI 219
            'default' => rand(80, 120)
        ];
        
        $htCount = $htCounts[$puskesmas->name] ?? $htCounts['default'];
        
        // Distribusi gender berdasarkan screenshot
        $htMaleFemaleRatio = [
            'Puskesmas 4' => [206, 277], // Dari screenshot: LAKI-LAKI 206, PEREMPUAN 277
            'Puskesmas 6' => [219, 207], // Dari screenshot: LAKI-LAKI 219, PEREMPUAN 207
            'default' => [round($htCount * 0.45), round($htCount * 0.55)] // Default 45% pria, 55% wanita
        ];
        
        $htMaleFemale = $htMaleFemaleRatio[$puskesmas->name] ?? $htMaleFemaleRatio['default'];
        $htMaleCount = $htMaleFemale[0];
        $htFemaleCount = $htMaleFemale[1];
        
        // Create patients with HT - Male
        $this->createHtPatientsByGender($puskesmas, $htMaleCount, 'male', $maleNames, $lastNames, $currentYear);
        
        // Create patients with HT - Female
        $this->createHtPatientsByGender($puskesmas, $htFemaleCount, 'female', $femaleNames, $lastNames, $currentYear);
        
        // Create patients with DM - menggunakan proporsi yang sama dengan HT
        $dmMaleCount = round($htMaleCount * 0.8); // Sedikit lebih sedikit
        $dmFemaleCount = round($htFemaleCount * 0.8);
        
        // Create patients with DM - Male
        $this->createDmPatientsByGender($puskesmas, $dmMaleCount, 'male', $maleNames, $lastNames, $currentYear);
        
        // Create patients with DM - Female
        $this->createDmPatientsByGender($puskesmas, $dmFemaleCount, 'female', $femaleNames, $lastNames, $currentYear);
    }
    
    private function createHtPatientsByGender(Puskesmas $puskesmas, int $count, string $gender, array $firstNames, array $lastNames, int $year): void
    {
        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            
            // Calculate age and birth date
            $age = rand(30, 70);
            $birthDate = Carbon::now()->subYears($age)->subDays(rand(0, 364));
            
            // Create patient
            $patient = Patient::create([
                'puskesmas_id' => $puskesmas->id,
                'nik' => $this->generateUniqueNIK(),
                'bpjs_number' => $this->generateUniqueBPJS(),
                'medical_record_number' => 'RM-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'name' => $firstName . ' ' . $lastName,
                'address' => 'Jl. ' . $lastNames[array_rand($lastNames)] . ' No. ' . rand(1, 100),
                'gender' => $gender,
                'birth_date' => $birthDate,
                'age' => $age,
                'ht_years' => [$year],
                'dm_years' => [],
            ]);
            
            // Create examination pattern
            $this->createExaminationPattern($patient, $year, 'ht');
        }
    }
    
    private function createDmPatientsByGender(Puskesmas $puskesmas, int $count, string $gender, array $firstNames, array $lastNames, int $year): void
    {
        for ($i = 0; $i < $count; $i++) {
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            
            // Calculate age and birth date
            $age = rand(30, 70);
            $birthDate = Carbon::now()->subYears($age)->subDays(rand(0, 364));
            
            // Create patient
            $patient = Patient::create([
                'puskesmas_id' => $puskesmas->id,
                'nik' => $this->generateUniqueNIK(),
                'bpjs_number' => $this->generateUniqueBPJS(),
                'medical_record_number' => 'RM-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'name' => $firstName . ' ' . $lastName,
                'address' => 'Jl. ' . $lastNames[array_rand($lastNames)] . ' No. ' . rand(1, 100),
                'gender' => $gender,
                'birth_date' => $birthDate,
                'age' => $age,
                'ht_years' => [],
                'dm_years' => [$year],
            ]);
            
            // Create examination pattern
            $this->createExaminationPattern($patient, $year, 'dm');
        }
    }
    
    /**
     * Create examination pattern for standard and non-standard patients
     */
    private function createExaminationPattern(Patient $patient, int $year, string $diseaseType): void
    {
        // Decide if patient will be standard or non-standard
        $isStandard = rand(1, 100) <= 70; // 70% chance to be standard
        
        // Determine first visit month
        $firstMonth = rand(1, 8); // Start between January and August
        
        if ($isStandard) {
            // Standard patient: visits every month from first visit to December
            for ($month = $firstMonth; $month <= 12; $month++) {
                $this->createExamination($patient, $year, $month, $diseaseType);
            }
        } else {
            // Non-standard patient: skips some months
            for ($month = $firstMonth; $month <= 12; $month++) {
                // 70% chance to visit in each month
                if (rand(1, 100) <= 70) {
                    $this->createExamination($patient, $year, $month, $diseaseType);
                }
            }
        }
    }
    
    /**
     * Create examination for a specific month
     */
    private function createExamination(Patient $patient, int $year, int $month, string $diseaseType): void
    {
        $day = rand(1, min(28, Carbon::createFromDate($year, $month, 1)->daysInMonth));
        $date = Carbon::createFromDate($year, $month, $day);
        
        if ($diseaseType === 'ht') {
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
        } else {
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
    }
    
    // Helper to generate unique NIK
    private function generateUniqueNIK(): string
    {
        $nik = (string)rand(1000000000000000, 9999999999999999);
        while (Patient::where('nik', $nik)->exists()) {
            $nik = (string)rand(1000000000000000, 9999999999999999);
        }
        return $nik;
    }
    
    // Helper to generate unique BPJS number
    private function generateUniqueBPJS(): string
    {
        $bpjs = (string)rand(100000000000, 999999999999);
        while (Patient::where('bpjs_number', $bpjs)->exists()) {
            $bpjs = (string)rand(100000000000, 999999999999);
        }
        return $bpjs;
    }
}