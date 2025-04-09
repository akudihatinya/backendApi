<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'nama_puskesmas',
        'isadmin',
        'dinas_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'isadmin' => 'boolean',
    ];

    /**
     * Get the Dinas that the user belongs to if they are an admin
     */
    public function dinas()
    {
        return $this->belongsTo(Dinas::class);
    }

    /**
     * Get the associated Puskesmas for this user
     * This is used for non-admin users
     */
    public function puskesmas()
    {
        return $this->hasOne(Puskesmas::class, 'nama', 'nama_puskesmas');
    }

    /**
     * Check if the user is an admin
     */
    public function isDinas()
    {
        return $this->isadmin === true;
    }
}