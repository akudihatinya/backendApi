<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemeriksaanStatus extends Model
{
    use HasFactory;
    
    protected $table = 'pemeriksaan_status';
    
    protected $fillable = [
        'pemeriksaan_id',
        'jenis_program_id',
        'status_id',
    ];
    
    public function pemeriksaan()
    {
        return $this->belongsTo(Pemeriksaan::class);
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