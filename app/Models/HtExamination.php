<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HtExamination extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'puskesmas_id',
        'examination_date',
        'systolic',
        'diastolic',
        'year',
        'month',
        'is_archived',
    ];

    protected $casts = [
        'examination_date' => 'date',
        'is_archived' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }

    public function isControlled()
    {
        return $this->systolic >= 90 && $this->systolic <= 139 && 
               $this->diastolic >= 60 && $this->diastolic <= 89;
    }
}