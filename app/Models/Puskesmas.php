<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puskesmas extends Model
{
    use HasFactory;

    protected $table = 'puskesmas';

    protected $fillable = [
        'dinas_id',
        'kode',
        'nama',
        'alamat',
    ];

    /**
     * Get the dinas that this puskesmas belongs to
     */
    public function dinas()
    {
        return $this->belongsTo(Dinas::class);
    }

    /**
     * Get all users associated with this puskesmas (non-admin users)
     */
    public function users()
    {
        return $this->hasMany(User::class, 'nama_puskesmas', 'nama');
    }

    /**
     * Get all patients associated with this puskesmas
     */
    public function pasien()
    {
        return $this->hasMany(Pasien::class);
    }

    /**
     * Get all sasaran puskesmas records
     */
    public function sasaranPuskesmas()
    {
        return $this->hasMany(SasaranPuskesmas::class);
    }

    /**
     * Get all monthly reports
     */
    public function laporanBulanan()
    {
        return $this->hasMany(LaporanBulanan::class);
    }
}