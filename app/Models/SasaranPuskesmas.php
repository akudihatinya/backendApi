<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SasaranPuskesmas extends Model
{
    use HasFactory;
    
    protected $table = 'sasaran_puskesmas';
    
    protected $fillable = [
        'sasaran_tahunan_id',
        'puskesmas_id',
        'jenis_program_id',
        'parameter',
        'nilai',
    ];
    
    protected $casts = [
        'nilai' => 'integer',
    ];
    
    public function sasaranTahunan()
    {
        return $this->belongsTo(SasaranTahunan::class);
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