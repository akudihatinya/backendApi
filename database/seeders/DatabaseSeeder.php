<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,             // Buat user admin
            PuskesmasSeeder::class,        // Buat semua puskesmas dan user puskesmas
            PatientSeeder::class,          // Buat semua pasien dengan data pemeriksaan dasar
            Puskesmas1HT2025Seeder::class, // Buat pasien khusus HT untuk Puskesmas 1 tahun 2025
            YearlyExaminationSeeder::class, // Tambahkan pemeriksaan per tahun 2024, 2025, 2026
            YearlyTargetSeeder::class      // Buat target tahunan untuk puskesmas
        ]);
    }
}