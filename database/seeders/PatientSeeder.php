<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\HtExamination;
use App\Models\DmExamination;
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
            // Buat 5-15 pasien per puskesmas
            $numPatients = rand(5, 15);
            
            for ($i = 0; $i < $numPatients; $i++) {
                $gender = $faker->randomElement(['male', 'female']);
                $birthDate = $faker->dateTimeBetween('-80 years', '-20 years');
                $age = Carbon::parse($birthDate)->age;
                
                // Tentukan secara random apakah pasien memiliki HT, DM, atau keduanya
                // Sekitar 40% memiliki HT saja, 30% memiliki DM saja, 30% memiliki keduanya
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
                    // Pilih beberapa tahun acak untuk pemeriksaan HT - FIX
                    $numYears = rand(1, count($years));
                    
                    // Pilih tahun-tahun acak
                    $selectedYears = $years;
                    shuffle($selectedYears);
                    $htYears = array_slice($selectedYears, 0, $numYears);
                }
                
                if ($hasDm) {
                    // Pilih beberapa tahun acak untuk pemeriksaan DM - FIX
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
                    // Buat 1-4 pemeriksaan per tahun
                    $numExaminations = rand(1, 4);
                    
                    for ($j = 0; $j < $numExaminations; $j++) {
                        $examinationDate = Carbon::createFromDate($year, rand(1, 12), rand(1, 28));
                        
                        HtExamination::create([
                            'patient_id' => $patient->id,
                            'puskesmas_id' => $puskesmas->id,
                            'examination_date' => $examinationDate,
                            'systolic' => rand(110, 180),
                            'diastolic' => rand(70, 110),
                            'year' => $examinationDate->year,
                            'month' => $examinationDate->month,
                            'is_archived' => false,
                        ]);
                    }
                }
                
                // Buat pemeriksaan untuk setiap tahun DM
                foreach ($dmYears as $year) {
                    // Buat 1-4 pemeriksaan per tahun
                    $numExaminations = rand(1, 4);
                    
                    for ($j = 0; $j < $numExaminations; $j++) {
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
                            'puskesmas_id' => $puskesmas->id,
                            'examination_date' => $examinationDate,
                            'examination_type' => $examinationType,
                            'result' => $result,
                            'year' => $examinationDate->year,
                            'month' => $examinationDate->month,
                            'is_archived' => false,
                        ]);
                    }
                }
                
                $count++;
            }
        }
        
        $this->command->info("Berhasil membuat {$count} data pasien dengan pemeriksaan HT/DM untuk tahun 2024-2026.");
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