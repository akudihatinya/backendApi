<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanBulanan extends Model
{
    use HasFactory;
    
    protected $table = 'laporan_bulanan';
    
    protected $fillable = [
        'tahun_program_id',
        'puskesmas_id',
        'bulan',
        'status_id',
        'petugas_id',
        'submitted_at',
        'approved_at',
    ];
    
    protected $casts = [
        'bulan' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];
    
    public function tahunProgram()
    {
        return $this->belongsTo(TahunProgram::class);
    }
    
    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }
    
    public function status()
    {
        return $this->belongsTo(RefStatus::class, 'status_id');
    }
    
    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
    
    public function laporanDetail()
    {
        return $this->hasMany(LaporanDetail::class, 'laporan_id');
    }
    
    public function isSubmitted()
    {
        return !is_null($this->submitted_at);
    }
    
    public function isApproved()
    {
        return !is_null($this->approved_at);
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