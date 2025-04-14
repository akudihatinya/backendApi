<?php

namespace Database\Seeders;

use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Illuminate\Database\Seeder;

class YearlyTargetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Membuat data target tahunan untuk puskesmas...');
        
        // Tahun yang akan dibuat target
        $years = [2024, 2025, 2026];
        
        // Jenis penyakit untuk target
        $diseaseTypes = ['ht', 'dm'];
        
        // Ambil semua puskesmas
        $puskesmas = Puskesmas::all();
        
        if ($puskesmas->isEmpty()) {
            $this->command->error('Tidak ada puskesmas! Jalankan PuskesmasSeeder terlebih dahulu.');
            return;
        }
        
        $count = 0;
        
        foreach ($puskesmas as $puskesmas) {
            foreach ($years as $year) {
                foreach ($diseaseTypes as $diseaseType) {
                    // Tentukan target berdasarkan puskesmas dan jenis penyakit
                    // Target antara 50-200 pasien
                    $targetCount = rand(50, 200);
                    
                    YearlyTarget::create([
                        'puskesmas_id' => $puskesmas->id,
                        'disease_type' => $diseaseType,
                        'year' => $year,
                        'target_count' => $targetCount,
                    ]);
                    
                    $count++;
                }
            }
        }
        
        $this->command->info("Berhasil membuat {$count} data target tahunan.");
    }
}