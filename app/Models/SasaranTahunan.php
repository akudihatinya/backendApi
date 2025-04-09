<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SasaranTahunan extends Model
{
    use HasFactory;
    
    protected $table = 'sasaran_tahunan';
    
    protected $fillable = [
        'tahun_program_id',
        'dinas_id',
        'nama',
        'keterangan',
        'status_id',
    ];
    
    public function tahunProgram()
    {
        return $this->belongsTo(TahunProgram::class);
    }
    
    public function dinas()
    {
        return $this->belongsTo(Dinas::class);
    }
    
    public function status()
    {
        return $this->belongsTo(RefStatus::class, 'status_id');
    }
    
    public function sasaranPuskesmas()
    {
        return $this->hasMany(SasaranPuskesmas::class);
    }
}