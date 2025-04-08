<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArsipSasaran extends Model
{
    use HasFactory;
    
    protected $table = 'arsip_sasaran';
    
    protected $fillable = [
        'tahun_program_id',
        'dinas_id',
        'sasaran_id_original',
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
    
    public function dinas()
    {
        return $this->belongsTo(Dinas::class);
    }
}
