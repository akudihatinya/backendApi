<?php

namespace App\Services\System;

use App\Models\DmExamination;
use App\Models\HtExamination;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveService
{
    /**
     * Archive examinations from previous year
     */
    public function archiveExaminations(): array
    {
        $currentYear = Carbon::now()->year;
        $prevYear = $currentYear - 1;
        
        try {
            DB::beginTransaction();
            
            // Archive HT examinations
            $htCount = HtExamination::where('year', $prevYear)
                ->where('is_archived', false)
                ->update(['is_archived' => true]);
                
            // Archive DM examinations
            $dmCount = DmExamination::where('year', $prevYear)
                ->where('is_archived', false)
                ->update(['is_archived' => true]);
                
            DB::commit();
            
            Log::info("Archived examinations for year $prevYear: $htCount HT, $dmCount DM");
            
            return [
                'status' => 'success',
                'archived_ht' => $htCount,
                'archived_dm' => $dmCount,
                'year' => $prevYear,
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error archiving examinations: " . $e->getMessage(), [
                'year' => $prevYear,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'archived_ht' => 0,
                'archived_dm' => 0,
                'year' => $prevYear,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Unarchive examinations for a specific year
     */
    public function unarchiveExaminations(int $year): array
    {
        try {
            DB::beginTransaction();
            
            // Unarchive HT examinations
            $htCount = HtExamination::where('year', $year)
                ->where('is_archived', true)
                ->update(['is_archived' => false]);
                
            // Unarchive DM examinations
            $dmCount = DmExamination::where('year', $year)
                ->where('is_archived', true)
                ->update(['is_archived' => false]);
                
            DB::commit();
            
            Log::info("Unarchived examinations for year $year: $htCount HT, $dmCount DM");
            
            return [
                'status' => 'success',
                'unarchived_ht' => $htCount,
                'unarchived_dm' => $dmCount,
                'year' => $year,
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error unarchiving examinations: " . $e->getMessage(), [
                'year' => $year,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'unarchived_ht' => 0,
                'unarchived_dm' => 0,
                'year' => $year,
                'error' => $e->getMessage(),
            ];
        }
    }
}