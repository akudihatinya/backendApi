<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Puskesmas extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
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
