<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PemeriksaanParam extends Model
{
    use HasFactory;
    
    protected $table = 'pemeriksaan_param';
    
    protected $fillable = [
        'pemeriksaan_id',
        'jenis_program_id',
        'nama_parameter',
        'nilai',
    ];
    
    protected $casts = [
        'nilai' => 'decimal:2',
    ];
    
    public function pemeriksaan()
    {
        return $this->belongsTo(Pemeriksaan::class);
    }
    
    public function jenisProgram()
    {
        return $this->belongsTo(RefJenisProgram::class, 'jenis_program_id');
    }
    
    /**
     * Cek apakah nilai parameter normal
     */
    public function isNormal()
    {
        if ($this->jenisProgram->kode === 'HT') {
            if ($this->nama_parameter === 'sistole') {
                return $this->nilai >= 120 && $this->nilai <= 139;
            } elseif ($this->nama_parameter === 'diastole') {
                return $this->nilai >= 80 && $this->nilai <= 89;
            }
        } elseif ($this->jenisProgram->kode === 'DM') {
            if ($this->nama_parameter === 'gdp') {
                return $this->nilai < 126;
            } elseif ($this->nama_parameter === 'gd2pp') {
                return $this->nilai < 200;
            } elseif ($this->nama_parameter === 'hba1c') {
                return $this->nilai < 7;
            }
        }
        
        return false;
    }
}
