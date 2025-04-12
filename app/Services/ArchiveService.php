<?php

namespace App\Services;

use App\Models\DmExamination;
use App\Models\HtExamination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ArchiveService
{
    /**
     * Perform automatic archiving of examinations at the start of a new year
     */
    public function archiveExaminations()
    {
        $lastYear = Carbon::now()->subYear()->year;
        
        // Archive HT examinations
        HtExamination::where('year', $lastYear)
            ->where('is_archived', false)
            ->update(['is_archived' => true]);
        
        // Archive DM examinations
        DmExamination::where('year', $lastYear)
            ->where('is_archived', false)
            ->update(['is_archived' => true]);
        
        return [
            'archived_ht' => HtExamination::where('year', $lastYear)->where('is_archived', true)->count(),
            'archived_dm' => DmExamination::where('year', $lastYear)->where('is_archived', true)->count(),
        ];
    }
}