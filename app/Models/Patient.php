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
        'medical_record_number', // Tambahkan field ini
        'name',
        'address',
        'gender',
        'birth_date',
        'age',
        'ht_years',
        'dm_years',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'ht_years' => 'array',
        'dm_years' => 'array',
    ];

    // Default empty arrays for years
    protected $attributes = [
        'ht_years' => '[]',
        'dm_years' => '[]',
    ];

    // Dynamic getter for has_ht based on ht_years
    public function getHasHtAttribute()
    {
        return !empty($this->ht_years);
    }

    // Dynamic getter for has_dm based on dm_years
    public function getHasDmAttribute()
    {
        return !empty($this->dm_years);
    }

    // Check if patient has HT in a specific year
    public function hasHtInYear($year)
    {
        return in_array($year, $this->ht_years ?? []);
    }

    // Check if patient has DM in a specific year
    public function hasDmInYear($year)
    {
        return in_array($year, $this->dm_years ?? []);
    }

    // Add a year to ht_years array if it doesn't exist
    public function addHtYear($year)
    {
        $years = $this->ht_years ?? [];
        if (!in_array($year, $years)) {
            $years[] = $year;
            $this->ht_years = $years;
        }
        return $this;
    }

    // Add a year to dm_years array if it doesn't exist
    public function addDmYear($year)
    {
        $years = $this->dm_years ?? [];
        if (!in_array($year, $years)) {
            $years[] = $year;
            $this->dm_years = $years;
        }
        return $this;
    }

    // Remove a year from ht_years array
    public function removeHtYear($year)
    {
        $years = $this->ht_years ?? [];
        $this->ht_years = array_values(array_filter($years, function($y) use ($year) {
            return $y != $year;
        }));
        return $this;
    }

    // Remove a year from dm_years array
    public function removeDmYear($year)
    {
        $years = $this->dm_years ?? [];
        $this->dm_years = array_values(array_filter($years, function($y) use ($year) {
            return $y != $year;
        }));
        return $this;
    }

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