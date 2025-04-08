<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefJenisProgram extends Model
{
    use HasFactory;
    
    protected $table = 'ref_jenis_program';
    
    protected $fillable = [
        'kode',
        'nama',
        'keterangan',
    ];
    
    public function pemeriksaanStatus()
    {
        return $this->hasMany(PemeriksaanStatus::class, 'jenis_program_id');
    }
    
    public function sasaranPuskesmas()
    {
        return $this->hasMany(SasaranPuskesmas::class, 'jenis_program_id');
    }
    
    public function laporanDetail()
    {
        return $this->hasMany(LaporanDetail::class, 'jenis_program_id');
    }
    
    public function rekapDetail()
    {
        return $this->hasMany(RekapDetail::class, 'jenis_program_id');
    }
    
    public function pencapaianBulanan()
    {
        return $this->hasMany(PencapaianBulanan::class, 'jenis_program_id');
    }
}