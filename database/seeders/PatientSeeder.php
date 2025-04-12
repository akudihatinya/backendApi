<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Puskesmas;
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
                
                Patient::create([
                    'puskesmas_id' => $puskesmas->id,
                    'nik' => $hasNik ? $this->generateNIK($faker) : null,
                    'bpjs_number' => $hasBpjs ? $this->generateBPJS($faker) : null,
                    'name' => $gender === 'male' ? $faker->firstNameMale . ' ' . $faker->lastName : $faker->firstNameFemale . ' ' . $faker->lastName,
                    'address' => $faker->boolean(90) ? $faker->address : null, // 90% memiliki alamat
                    'gender' => $gender,
                    'birth_date' => $faker->boolean(95) ? $birthDate : null, // 95% memiliki tanggal lahir
                    'age' => $faker->boolean(80) ? $age : null, // 80% memiliki umur
                    'has_ht' => $hasHt,
                    'has_dm' => $hasDm,
                    'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                    'updated_at' => now(),
                ]);
                
                $count++;
            }
        }
        
        $this->command->info("Berhasil membuat {$count} data pasien.");
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