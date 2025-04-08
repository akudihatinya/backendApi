<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RekapDetail extends Model
{
    use HasFactory;
    
    protected $table = 'rekap_detail';
    
    protected $fillable = [
        'rekap_id',
        'puskesmas_id',
        'jenis_program_id',
        'jenis_kelamin_id',
        'status_id',
        'jumlah',
    ];
    
    protected $casts = [
        'jumlah' => 'integer',
    ];
    
    public function rekapDinas()
    {
        return $this->belongsTo(RekapDinas::class, 'rekap_id');
    }
    
    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }
    
    public function jenisProgram()
    {
        return $this->belongsTo(RefJenisProgram::class, 'jenis_program_id');
    }
    
    public function jenisKelamin()
    {
        return $this->belongsTo(RefJenisKelamin::class, 'jenis_kelamin_id');
    }
    
    public function status()
    {
        return $this->belongsTo(RefStatus::class, 'status_id');
    }
}