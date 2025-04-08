<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefStatus extends Model
{
    use HasFactory;
    
    protected $table = 'ref_status';
    
    protected $fillable = [
        'kode',
        'nama',
        'kategori',
        'keterangan',
    ];
    
    public function pemeriksaanStatus()
    {
        return $this->hasMany(PemeriksaanStatus::class, 'status_id');
    }
    
    public function sasaranTahunan()
    {
        return $this->hasMany(SasaranTahunan::class, 'status_id');
    }
    
    public function laporanBulanan()
    {
        return $this->hasMany(LaporanBulanan::class, 'status_id');
    }
    
    public function laporanDetail()
    {
        return $this->hasMany(LaporanDetail::class, 'status_id');
    }
    
    public function rekapDinas()
    {
        return $this->hasMany(RekapDinas::class, 'status_id');
    }
    
    public function rekapDetail()
    {
        return $this->hasMany(RekapDetail::class, 'status_id');
    }
}