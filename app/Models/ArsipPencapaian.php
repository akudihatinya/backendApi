<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArsipPencapaian extends Model
{
    use HasFactory;
    
    protected $table = 'arsip_pencapaian';
    
    protected $fillable = [
        'tahun_program_id',
        'puskesmas_id',
        'jenis_program_id',
        'data_json',
    ];
    
    protected $casts = [
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
    
    public function jenisProgram()
    {
        return $this->belongsTo(RefJenisProgram::class, 'jenis_program_id');
    }
}