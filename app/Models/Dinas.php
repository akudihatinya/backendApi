<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dinas extends Model
{
    use HasFactory;

    protected $table = 'dinas';

    protected $fillable = [
        'kode',
        'nama',
        'alamat',
    ];

    /**
     * Get all puskesmas under this dinas
     */
    public function puskesmas()
    {
        return $this->hasMany(Puskesmas::class);
    }

    /**
     * Get all admin users (dinas staff) associated with this dinas
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all sasaran tahunan for this dinas
     */
    public function sasaranTahunan()
    {
        return $this->hasMany(SasaranTahunan::class);
    }
}