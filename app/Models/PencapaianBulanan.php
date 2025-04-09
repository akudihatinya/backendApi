<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PencapaianBulanan extends Model
{
    use HasFactory;
    
    protected $table = 'pencapaian_bulanan';
    
    protected $fillable = [
        'tahun_program_id',
        'puskesmas_id',
        'jenis_program_id',
        'bulan',
        'parameter',
        'nilai',
    ];
    
    protected $casts = [
        'bulan' => 'integer',
        'nilai' => 'integer',
    ];
    
    public function tahunProgram()
    {
        return $this->belongsTo(TahunProgram::class);
    }
    
    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }
    
    public function jenisProgram()
    {
        return $this->belongsTo(RefJenisProgram::class, 'jenis_program_id');
    }
    
    /**
     * Get presentase pencapaian terhadap target
     */
    public function getPersentaseAttribute()
    {
        $sasaran = SasaranPuskesmas::where('puskesmas_id', $this->puskesmas_id)
            ->whereHas('sasaranTahunan', function ($query) {
                $query->where('tahun_program_id', $this->tahun_program_id);
            })
            ->where('jenis_program_id', $this->jenis_program_id)
            ->where('parameter', $this->parameter)
            ->first();
            
        if (!$sasaran || $sasaran->nilai == 0) {
            return 0;
        }
        
        return round(($this->nilai / $sasaran->nilai) * 100, 2);
    }
    
    /**
     * Get nama bulan dalam Bahasa Indonesia
     */
    public function getNamaBulanAttribute()
    {
        $namaBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
        
        return $namaBulan[$this->bulan] ?? '';
    }
}