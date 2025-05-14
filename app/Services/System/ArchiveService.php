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
        $lastYear = Carbon::now()->subYear()->year;
        
        try {
            DB::beginTransaction();
            
            // Archive HT examinations
            $archivedHt = HtExamination::where('year', $lastYear)
                ->where('is_archived', false)
                ->update(['is_archived' => true]);
            
            // Archive DM examinations
            $archivedDm = DmExamination::where('year', $lastYear)
                ->where('is_archived', false)
                ->update(['is_archived' => true]);
            
            DB::commit();
            
            Log::info("Archived examinations: HT: {$archivedHt}, DM: {$archivedDm}");
            
            return [
                'archived_ht' => $archivedHt,
                'archived_dm' => $archivedDm,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error archiving examinations: ' . $e->getMessage());
            
            return [
                'archived_ht' => 0,
                'archived_dm' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Archive examinations for a specific year
     */
    public function archiveExaminationsByYear(int $year): array
    {
        if ($year >= Carbon::now()->year) {
            return [
                'archived_ht' => 0,
                'archived_dm' => 0,
                'error' => 'Cannot archive current or future year examinations.',
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Archive HT examinations
            $archivedHt = HtExamination::where('year', $year)
                ->where('is_archived', false)
                ->update(['is_archived' => true]);
            
            // Archive DM examinations
            $archivedDm = DmExamination::where('year', $year)
                ->where('is_archived', false)
                ->update(['is_archived' => true]);
            
            DB::commit();
            
            Log::info("Archived examinations for year {$year}: HT: {$archivedHt}, DM: {$archivedDm}");
            
            return [
                'archived_ht' => $archivedHt,
                'archived_dm' => $archivedDm,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error archiving examinations for year {$year}: " . $e->getMessage());
            
            return [
                'archived_ht' => 0,
                'archived_dm' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Unarchive examinations for a specific year
     */
    public function unarchiveExaminationsByYear(int $year): array
    {
        try {
            DB::beginTransaction();
            
            // Unarchive HT examinations
            $unarchivedHt = HtExamination::where('year', $year)
                ->where('is_archived', true)
                ->update(['is_archived' => false]);
            
            // Unarchive DM examinations
            $unarchivedDm = DmExamination::where('year', $year)
                ->where('is_archived', true)
                ->update(['is_archived' => false]);
            
            DB::commit();
            
            Log::info("Unarchived examinations for year {$year}: HT: {$unarchivedHt}, DM: {$unarchivedDm}");
            
            return [
                'unarchived_ht' => $unarchivedHt,
                'unarchived_dm' => $unarchivedDm,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error unarchiving examinations for year {$year}: " . $e->getMessage());
            
            return [
                'unarchived_ht' => 0,
                'unarchived_dm' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}