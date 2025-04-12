<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YearlyTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'puskesmas_id',
        'disease_type',
        'year',
        'target_count',
    ];

    public function puskesmas()
    {
        return $this->belongsTo(Puskesmas::class);
    }
}
