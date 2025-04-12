<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puskesmas extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    public function htExaminations()
    {
        return $this->hasMany(HtExamination::class);
    }

    public function dmExaminations()
    {
        return $this->hasMany(DmExamination::class);
    }

    public function yearlyTargets()
    {
        return $this->hasMany(YearlyTarget::class);
    }
}
