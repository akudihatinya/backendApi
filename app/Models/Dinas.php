<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dinas extends Model
{
    use HasFactory;
    
    protected $table = 'dinas';
    
    protected $fillable = [
        'kode',
        'nama',
        'alamat',
    ];
    
    public function puskesmas()
    {
        return $this->hasMany(Puskesmas::class);
    }
    
    public function sasaranTahunan()
    {
        return $this->hasMany(SasaranTahunan::class);
    }
    
    public function rekapDinas()
    {
        return $this->hasMany(RekapDinas::class);
    }
}