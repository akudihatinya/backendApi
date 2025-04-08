<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefJenisKelamin extends Model
{
    use HasFactory;
    
    protected $table = 'ref_jenis_kelamin';
    
    protected $fillable = [
        'kode',
        'nama',
    ];
    
    public function pasien()
    {
        return $this->hasMany(Pasien::class, 'jenis_kelamin_id');
    }
    
    public function laporanDetail()
    {
        return $this->hasMany(LaporanDetail::class, 'jenis_kelamin_id');
    }
    
    public function rekapDetail()
    {
        return $this->hasMany(RekapDetail::class, 'jenis_kelamin_id');
    }
}