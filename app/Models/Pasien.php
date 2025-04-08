<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pasien extends Model
{
    use HasFactory;
    
    protected $table = 'pasien';
    
    protected $fillable = [
        'puskesmas_id',
        'nik',
        'no_bpjs',
        'nama',
        'jenis_kelamin_id',
        'tgl_lahir',
        'alamat',
    ];
    
    protected $casts = [
        'tgl_lahir' => 'date',
    ];
    
    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }
    
    public function jenisKelamin()
    {
        return $this->belongsTo(RefJenisKelamin::class, 'jenis_kelamin_id');
    }
    
    public function pemeriksaan()
    {
        return $this->hasMany(Pemeriksaan::class);
    }
    
    /**
     * Get pemeriksaan HT terakhir
     */
    public function getLastHTCheckup($tahunProgramId = null)
    {
        $query = $this->pemeriksaan()
            ->whereHas('pemeriksaanStatus', function ($q) {
                $q->whereHas('jenisProgram', function ($jq) {
                    $jq->where('kode', 'HT');
                });
            })
            ->latest('tgl_periksa');
            
        if ($tahunProgramId) {
            $query->where('tahun_program_id', $tahunProgramId);
        }
        
        return $query->first();
    }
    
    /**
     * Get pemeriksaan DM terakhir
     */
    public function getLastDMCheckup($tahunProgramId = null)
    {
        $query = $this->pemeriksaan()
            ->whereHas('pemeriksaanStatus', function ($q) {
                $q->whereHas('jenisProgram', function ($jq) {
                    $jq->where('kode', 'DM');
                });
            })
            ->latest('tgl_periksa');
            
        if ($tahunProgramId) {
            $query->where('tahun_program_id', $tahunProgramId);
        }
        
        return $query->first();
    }
    
    /**
     * Cek status terkendali HT
     */
    public function isHTTerkendali($tahunProgramId)
    {
        // Minimal 3 kali pemeriksaan dengan hasil normal
        $normalCheckups = $this->pemeriksaan()
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'sistole')
                   ->whereBetween('nilai', [120, 139]);
            })
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'diastole')
                   ->whereBetween('nilai', [80, 89]);
            })
            ->count();
            
        return $normalCheckups >= 3;
    }
    
    /**
     * Cek status terkendali DM
     */
    public function isDMTerkendali($tahunProgramId)
    {
        // Minimal satu kali HbA1C < 7%
        $hba1cNormal = $this->pemeriksaan()
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'hba1c')
                   ->where('nilai', '<', 7);
            })
            ->exists();
            
        if ($hba1cNormal) {
            return true;
        }
        
        // Atau minimal 3 kali GD Puasa normal
        $gdpNormal = $this->pemeriksaan()
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'gdp')
                   ->where('nilai', '<', 126);
            })
            ->count();
            
        if ($gdpNormal >= 3) {
            return true;
        }
        
        // Atau minimal 3 kali GD 2 jam PP normal
        $gd2ppNormal = $this->pemeriksaan()
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanParam', function ($q) {
                $q->where('nama_parameter', 'gd2pp')
                   ->where('nilai', '<', 200);
            })
            ->count();
            
        return $gd2ppNormal >= 3;
    }
    
    /**
     * Cek apakah pasien rutin
     */
    public function isRutin($tahunProgramId, $jenisProgram, $bulan)
    {
        $tahunProgram = TahunProgram::find($tahunProgramId);
        
        if (!$tahunProgram) {
            return false;
        }
        
        // Bulan pertama pemeriksaan
        $firstCheckup = $this->pemeriksaan()
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanStatus', function ($q) use ($jenisProgram) {
                $q->whereHas('jenisProgram', function ($jq) use ($jenisProgram) {
                    $jq->where('kode', $jenisProgram);
                });
            })
            ->orderBy('tgl_periksa')
            ->first();
            
        if (!$firstCheckup) {
            return false;
        }
        
        $firstMonth = (int) $firstCheckup->tgl_periksa->format('m');
        
        // Jika bulan saat ini adalah bulan pertama pemeriksaan
        if ($bulan === $firstMonth) {
            return true;
        }
        
        // Hitung berapa bulan yang seharusnya sudah diperiksa
        $monthsSinceFirst = ($bulan - $firstMonth);
        if ($monthsSinceFirst < 0) {
            $monthsSinceFirst += 12;
        }
        
        // Hitung berapa bulan yang sudah melakukan pemeriksaan
        $monthsChecked = $this->pemeriksaan()
            ->where('tahun_program_id', $tahunProgramId)
            ->whereHas('pemeriksaanStatus', function ($q) use ($jenisProgram) {
                $q->whereHas('jenisProgram', function ($jq) use ($jenisProgram) {
                    $jq->where('kode', $jenisProgram);
                });
            })
            ->whereRaw("MONTH(tgl_periksa) BETWEEN ? AND ?", [$firstMonth, $bulan])
            ->select(\Illuminate\Support\Facades\DB::raw("DISTINCT MONTH(tgl_periksa) as bulan"))
            ->count();
            
        // Rutin jika setiap bulan melakukan pemeriksaan
        return $monthsChecked >= $monthsSinceFirst + 1;
    }
}       