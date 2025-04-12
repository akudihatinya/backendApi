<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'password',
        'name',
        'profile_picture',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function puskesmas()
    {
        return $this->hasOne(Puskesmas::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(UserRefreshToken::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isPuskesmas()
    {
        return $this->role === 'puskesmas';
    }
    
}
