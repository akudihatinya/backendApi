<?php

namespace App\Traits;

use App\Models\Pemeriksaan;
use App\Models\PemeriksaanParam;
use App\Models\RefJenisProgram;
use Illuminate\Support\Facades\DB;

trait AnalisisHTDM
{
    /**
     * Analisis status HT (Hipertensi)
     * Terkendali jika:
     * - Minimal 3 kali pemeriksaan dengan hasil normal (120-139/80-89 mmHg)
     */
    public function analisaHT($pasienId, $tahunProgramId, $bulan = null)
    {
        // Dapatkan jenis program HT
        $htProgram = RefJenisProgram::where('kode', 'HT')->first();
        
        if (!$htProgram) {
            return [
                'terkendali' => false,
                'rutin' => false,
                'total_pemeriksaan' => 0,
                'pemeriksaan_normal' => 0,
            ];
        }
        
        $query = Pemeriksaan::where('pasien_id', $pasienId)
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanStatus', function ($q) use ($htProgram) {
                $q->where('jenis_program_id', $htProgram->id);
            });
            
        if ($bulan) {
            $query->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
        }
        
        // Total pemeriksaan
        $totalPemeriksaan = (clone $query)->count();
        
        // Pemeriksaan dengan hasil normal
        $pemeriksaanNormal = (clone $query)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'sistole')
                   ->whereBetween('nilai', [120, 139]);
            })
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'diastole')
                   ->whereBetween('nilai', [80, 89]);
            })
            ->count();
            
        // Minimal 3 kali pemeriksaan normal untuk dianggap terkendali
        $terkendali = $pemeriksaanNormal >= 3;
        
        // Analisis rutin (hadir setiap bulan sejak pertama kali periksa)
        $rutin = $this->analisisRutin($pasienId, $tahunProgramId, $htProgram->id, $bulan);
        
        return [
            'terkendali' => $terkendali,
            'rutin' => $rutin,
            'total_pemeriksaan' => $totalPemeriksaan,
            'pemeriksaan_normal' => $pemeriksaanNormal,
        ];
    }
    
    /**
     * Analisis status DM (Diabetes Mellitus)
     * Terkendali jika:
     * - Setidaknya satu kali dalam setahun melakukan pemeriksaan HB1AC dengan skor dibawah 7%
     * - Atau setidaknya melakukan 3 kali pemeriksaan gula darah puasa dengan hasil dibawah 126 mg/dl
     * - Atau gula darah 2jpp dibawah 200 mg/dl
     */
    public function analisaDM($pasienId, $tahunProgramId, $bulan = null)
    {
        // Dapatkan jenis program DM
        $dmProgram = RefJenisProgram::where('kode', 'DM')->first();
        
        if (!$dmProgram) {
            return [
                'terkendali' => false,
                'rutin' => false,
                'total_pemeriksaan' => 0,
                'hba1c_normal' => 0,
                'gdp_normal' => 0,
                'gd2pp_normal' => 0,
            ];
        }
        
        $query = Pemeriksaan::where('pasien_id', $pasienId)
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanStatus', function ($q) use ($dmProgram) {
                $q->where('jenis_program_id', $dmProgram->id);
            });
            
        if ($bulan) {
            $query->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
        }
        
        // Total pemeriksaan
        $totalPemeriksaan = (clone $query)->count();
        
        // Pemeriksaan HbA1C normal (dibawah 7%)
        $hba1cNormal = (clone $query)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'hba1c')
                   ->where('nilai', '<', 7);
            })
            ->count();
            
        // Pemeriksaan GDP normal (dibawah 126 mg/dl)
        $gdpNormal = (clone $query)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'gdp')
                   ->where('nilai', '<', 126);
            })
            ->count();
            
        // Pemeriksaan GD2PP normal (dibawah 200 mg/dl)
        $gd2ppNormal = (clone $query)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'gd2pp')
                   ->where('nilai', '<', 200);
            })
            ->count();
            
        // Terkendali jika:
        // - Minimal 1x HbA1C < 7% ATAU
        // - Minimal 3x GDP < 126 mg/dl ATAU
        // - Minimal 3x GD2PP < 200 mg/dl
        $terkendali = $hba1cNormal > 0 || $gdpNormal >= 3 || $gd2ppNormal >= 3;
        
        // Analisis rutin (hadir setiap bulan sejak pertama kali periksa)
        $rutin = $this->analisisRutin($pasienId, $tahunProgramId, $dmProgram->id, $bulan);
        
        return [
            'terkendali' => $terkendali,
            'rutin' => $rutin,
            'total_pemeriksaan' => $totalPemeriksaan,
            'hba1c_normal' => $hba1cNormal,
            'gdp_normal' => $gdpNormal,
            'gd2pp_normal' => $gd2ppNormal,
        ];
    }
    
    /**
     * Analisis apakah pasien rutin (hadir setiap bulan sejak pertama kali periksa)
     */
    protected function analisisRutin($pasienId, $tahunProgramId, $jenisProgramId, $bulanSekarang = null)
    {
        // Jika bulan tidak ditentukan, gunakan bulan sekarang
        if (!$bulanSekarang) {
            $bulanSekarang = date('m');
        }
        
        // Bulan pertama pemeriksaan
        $firstCheckup = Pemeriksaan::where('pasien_id', $pasienId)
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanStatus', function ($q) use ($jenisProgramId) {
                $q->where('jenis_program_id', $jenisProgramId);
            })
            ->orderBy('tgl_periksa')
            ->first();
            
        if (!$firstCheckup) {
            return false;
        }
        
        $firstMonth = (int) $firstCheckup->tgl_periksa->format('m');
        
        // Jika bulan saat ini adalah bulan pertama pemeriksaan
        if ($bulanSekarang == $firstMonth) {
            return true;
        }
        
        // Hitung berapa bulan yang seharusnya sudah diperiksa
        $monthsSinceFirst = ($bulanSekarang - $firstMonth);
        if ($monthsSinceFirst < 0) {
            $monthsSinceFirst += 12;
        }
        
        // Hitung berapa bulan yang sudah melakukan pemeriksaan
        $monthsChecked = Pemeriksaan::where('pasien_id', $pasienId)
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanStatus', function ($q) use ($jenisProgramId) {
                $q->where('jenis_program_id', $jenisProgramId);
            })
            ->whereRaw("MONTH(tgl_periksa) BETWEEN ? AND ?", [$firstMonth, $bulanSekarang])
            ->selectRaw("DISTINCT MONTH(tgl_periksa) as bulan")
            ->count();
            
        // Rutin jika setiap bulan melakukan pemeriksaan
        return $monthsChecked >= $monthsSinceFirst + 1;
    }
}