<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RekapDinas extends Model
{
    use HasFactory;
    
    protected $table = 'rekap_dinas';
    
    protected $fillable = [
        'tahun_program_id',
        'dinas_id',
        'bulan',
        'status_id',
    ];
    
    protected $casts = [
        'bulan' => 'integer',
    ];
    
    public function tahunProgram()
    {
        return $this->belongsTo(TahunProgram::class);
    }
    
    public function dinas()
    {
        return $this->belongsTo(Dinas::class);
    }
    
    public function status()
    {
        return $this->belongsTo(RefStatus::class, 'status_id');
    }
    
    public function rekapDetail()
    {
        return $this->hasMany(RekapDetail::class, 'rekap_id');
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
