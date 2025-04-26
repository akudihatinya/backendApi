<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\User;
use App\Models\YearlyTarget;
use App\Models\HtExamination;
use App\Models\DmExamination;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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
            
            // Buat pemeriksaan hanya untuk 60% pasien agar persentase sekitar 60%
            // Sisanya tidak memiliki pemeriksaan sehingga tidak masuk hitungan
            if (rand(1, 100) <= 60) {
                // Determine how many months this patient will have examinations
                $visitCount = rand(1, 12);
                $months = array_rand(range(1, 12), $visitCount);
                if (!is_array($months)) {
                    $months = [$months + 1]; // Convert to array if only one month
                } else {
                    $months = array_map(function($m) { return $m + 1; }, $months);
                }
                
                // Create examinations for each month
                foreach ($months as $month) {
                    $this->createHtExaminationForMonth($patient, $year, $month);
                }
            }
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
            
            // Buat pemeriksaan hanya untuk 60% pasien
            if (rand(1, 100) <= 60) {
                // Determine how many months this patient will have examinations
                $visitCount = rand(1, 12);
                $months = array_rand(range(1, 12), $visitCount);
                if (!is_array($months)) {
                    $months = [$months + 1]; // Convert to array if only one month
                } else {
                    $months = array_map(function($m) { return $m + 1; }, $months);
                }
                
                // Create examinations for each month
                foreach ($months as $month) {
                    $this->createDmExaminationForMonth($patient, $year, $month);
                }
            }
        }
    }
    
    private function createHtExaminationForMonth(Patient $patient, int $year, int $month): void
    {
        // Random day in the month, avoiding days that don't exist in some months
        $day = rand(1, min(28, Carbon::createFromDate($year, $month, 1)->daysInMonth));
        $date = Carbon::createFromDate($year, $month, $day);
        
        // Create HT examination with normal values (since we're not focusing on controlled vs uncontrolled)
        HtExamination::create([
            'patient_id' => $patient->id,
            'puskesmas_id' => $patient->puskesmas_id,
            'examination_date' => $date,
            'systolic' => rand(90, 160),
            'diastolic' => rand(60, 100),
            'year' => $year,
            'month' => $month,
            'is_archived' => false,
        ]);
    }
    
    private function createDmExaminationForMonth(Patient $patient, int $year, int $month): void
    {
        // Random day in the month, avoiding days that don't exist in some months
        $day = rand(1, min(28, Carbon::createFromDate($year, $month, 1)->daysInMonth));
        $date = Carbon::createFromDate($year, $month, $day);
        
        // Pilih tipe pemeriksaan acak
        $examTypes = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
        $examType = $examTypes[array_rand($examTypes)];
        
        // Tentukan hasil berdasarkan tipe
        $result = 0;
        switch ($examType) {
            case 'hba1c':
                $result = rand(50, 100) / 10; // 5.0 - 10.0
                break;
            case 'gdp':
                $result = rand(80, 200); // 80-200 mg/dl
                break;
            case 'gd2jpp':
                $result = rand(100, 250); // 100-250 mg/dl
                break;
            case 'gdsp':
                $result = rand(100, 200); // 100-200 mg/dl
                break;
        }
        
        // Create DM examination
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