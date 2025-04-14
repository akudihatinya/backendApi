<?php

namespace App\Observers;

use App\Models\DmExamination;
use Carbon\Carbon;

class DmExaminationObserver
{
    public function created(DmExamination $examination)
    {
        // Get examination date and extract the year
        $date = Carbon::parse($examination->examination_date);
        $year = $date->year;
        
        // Add year to patient's dm_years array
        $patient = $examination->patient;
        $patient->addDmYear($year);
        $patient->save();
    }
    
    public function deleted(DmExamination $examination)
    {
        // Check if patient has any other examinations in this year
        $date = Carbon::parse($examination->examination_date);
        $year = $date->year;
        
        $patient = $examination->patient;
        $otherExamsInYear = $patient->dmExaminations()
            ->whereYear('examination_date', $year)
            ->where('id', '!=', $examination->id)
            ->exists();
        
        // If no other examinations exist for this year, remove the year
        if (!$otherExamsInYear) {
            $patient->removeDmYear($year);
            $patient->save();
        }
    }
}