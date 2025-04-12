<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'puskesmas_id',
        'nik',
        'bpjs_number',
        'name',
        'address',
        'gender',
        'birth_date',
        'age',
        'has_ht',
        'has_dm',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'has_ht' => 'boolean',
        'has_dm' => 'boolean',
    ];

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }

    public function htExaminations()
    {
        return $this->hasMany(HtExamination::class);
    }

    public function dmExaminations()
    {
        return $this->hasMany(DmExamination::class);
    }
}
