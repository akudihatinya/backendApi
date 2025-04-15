<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'puskesmas_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function puskesmas(): BelongsTo
    {
        return $this->belongsTo(Puskesmas::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(UserRefreshToken::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPuskesmas(): bool
    {
        return $this->role === 'puskesmas';
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            $user->tokens()->delete();
            $user->refreshTokens()->delete();
            \Illuminate\Support\Facades\Log::info("User {$user->name} dihapus oleh " . auth()->user()->name);
        });
    }
}
