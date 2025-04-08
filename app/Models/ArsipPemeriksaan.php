<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArsipPemeriksaan extends Model
{
    use HasFactory;
    
    protected $table = 'arsip_pemeriksaan';
    
    protected $fillable = [
        'tahun_program_id',
        'pasien_id',
        'pemeriksaan_id_original',
        'tgl_periksa',
        'petugas_id',
        'jenis_program_id',
        'status_id',
        'data_json',
    ];
    
    protected $casts = [
        'tgl_periksa' => 'date',
        'data_json' => 'array',
    ];
    
    // Nonaktifkan created_at dan updated_at, karena hanya ada created_at
    public $timestamps = false;
    
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
    
    public function jenisProgram()
    {
        return $this->belongsTo(RefJenisProgram::class, 'jenis_program_id');
    }
    
    public function status()
    {
        return $this->belongsTo(RefStatus::class, 'status_id');
    }
}