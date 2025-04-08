<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puskesmas extends Model
{
    use HasFactory;
    
    protected $table = 'puskesmas';
    
    protected $fillable = [
        'dinas_id',
        'kode',
        'nama',
        'alamat',
    ];
    
    public function dinas()
    {
        return $this->belongsTo(Dinas::class);
    }
    
    public function pasien()
    {
        return $this->hasMany(Pasien::class);
    }
    
    public function sasaranPuskesmas()
    {
        return $this->hasMany(SasaranPuskesmas::class);
    }
    
    public function laporanBulanan()
    {
        return $this->hasMany(LaporanBulanan::class);
    }
    
    public function pencapaianBulanan()
    {
        return $this->hasMany(PencapaianBulanan::class);
    }
}