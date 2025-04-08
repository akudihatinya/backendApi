<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaporanDetail extends Model
{
    use HasFactory;
    
    protected $table = 'laporan_detail';
    
    protected $fillable = [
        'laporan_id',
        'jenis_program_id',
        'jenis_kelamin_id',
        'status_id',
        'jumlah',
    ];
    
    protected $casts = [
        'jumlah' => 'integer',
    ];
    
    public function laporanBulanan()
    {
        return $this->belongsTo(LaporanBulanan::class, 'laporan_id');
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