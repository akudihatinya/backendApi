<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pemeriksaan extends Model
{
    use HasFactory;
    
    protected $table = 'pemeriksaan';
    
    protected $fillable = [
        'tahun_program_id',
        'pasien_id',
        'petugas_id',
        'tgl_periksa',
        'keterangan',
    ];
    
    protected $casts = [
        'tgl_periksa' => 'date',
    ];
    
    public function tahunProgram()
    {
        return $this->belongsTo(TahunProgram::class);
    }
    
    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }
    
    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
    
    public function pemeriksaanParam()
    {
        return $this->hasMany(PemeriksaanParam::class, 'pemeriksaan_id');
    }
    
    public function pemeriksaanStatus()
    {
        return $this->hasMany(PemeriksaanStatus::class, 'pemeriksaan_id');
    }
}
