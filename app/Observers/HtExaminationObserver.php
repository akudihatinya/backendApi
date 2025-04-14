<?php

namespace App\Observers;

use App\Models\HtExamination;
use Carbon\Carbon;

class HtExaminationObserver
{
    public function created(HtExamination $examination)
    {
        // Get examination date and extract the year
        $date = Carbon::parse($examination->examination_date);
        $year = $date->year;
        
        // Add year to patient's ht_years array
        $patient = $examination->patient;
        $patient->addHtYear($year);
        $patient->save();
    }
    
    public function deleted(HtExamination $examination)
    {
        // Check if patient has any other examinations in this year
        $date = Carbon::parse($examination->examination_date);
        $year = $date->year;
        
        $patient = $examination->patient;
        $otherExamsInYear = $patient->htExaminations()
            ->whereYear('examination_date', $year)
            ->where('id', '!=', $examination->id)
            ->exists();
        
        // If no other examinations exist for this year, remove the year
        if (!$otherExamsInYear) {
            $patient->removeHtYear($year);
            $patient->save();
        }
    }
}