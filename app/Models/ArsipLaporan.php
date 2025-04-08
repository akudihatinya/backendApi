<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArsipLaporan extends Model
{
    use HasFactory;
    
    protected $table = 'arsip_laporan';
    
    protected $fillable = [
        'tahun_program_id',
        'puskesmas_id',
        'laporan_id_original',
        'bulan',
        'status_id',
        'submitted_at',
        'approved_at',
        'data_json',
    ];
    
    protected $casts = [
        'bulan' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'data_json' => 'array',
    ];
    
    // Nonaktifkan created_at dan updated_at, karena hanya ada created_at
    public $timestamps = false;
    
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
}