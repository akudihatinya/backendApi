<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TahunProgram extends Model
{
    use HasFactory;
    
    protected $table = 'tahun_program';
    
    protected $fillable = [
        'tahun',
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_active',
        'keterangan',
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];
    
    public function pemeriksaan()
    {
        return $this->hasMany(Pemeriksaan::class);
    }
    
    public function sasaranTahunan()
    {
        return $this->hasMany(SasaranTahunan::class);
    }
    
    public function laporanBulanan()
    {
        return $this->hasMany(LaporanBulanan::class);
    }
    
    public function rekapDinas()
    {
        return $this->hasMany(RekapDinas::class);
    }
    
    public function pencapaianBulanan()
    {
        return $this->hasMany(PencapaianBulanan::class);
    }
}