<?php

namespace App\Services\Cache;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\MonthlyStatisticsCache;
use App\Models\Patient;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatisticsCacheService
{
    /**
     * Update cache when a new examination is created
     * 
     * @param DmExamination|HtExamination $examination
     * @param string $diseaseType 'dm' or 'ht'
     */
    public function updateCacheOnExaminationCreate($examination, string $diseaseType): void
    {
        $date = Carbon::parse($examination->examination_date);
        $year = $date->year;
        $month = $date->month;
        $puskesmasId = $examination->puskesmas_id;
        
        // Get or create cache record
        $cache = MonthlyStatisticsCache::firstOrNew([
            'puskesmas_id' => $puskesmasId,
            'disease_type' => $diseaseType,
            'year' => $year,
            'month' => $month,
        ]);
        
        // If this is a new cache entry, initialize counts
        if (!$cache->exists) {
            $cache->fill([
                'male_count' => 0,
                'female_count' => 0,
                'total_count' => 0,
                'standard_count' => 0,
                'non_standard_count' => 0,
                'standard_percentage' => 0,
            ]);
            $cache->save();
        }
        
        // Check if this is the first examination for this patient in this month
        $isFirstExaminationThisMonth = $this->isFirstExaminationInMonth(
            $examination->patient_id, 
            $puskesmasId, 
            $year, 
            $month, 
            $diseaseType,
            $examination->id
        );
        
        if ($isFirstExaminationThisMonth) {
            // Get patient gender
            $patient = Patient::find($examination->patient_id);
            
            // Is this patient considered standard for this month?
            $isStandard = $this->isPatientStandard($examination->patient_id, $year, $month, $diseaseType);
            
            // Update cache counts
            if ($patient->gender === 'male') {
                $cache->male_count++;
            } else {
                $cache->female_count++;
            }
            
            $cache->total_count++;
            
            if ($isStandard) {
                $cache->standard_count++;
            } else {
                $cache->non_standard_count++;
            }
            
            // Update standard percentage
            $cache->standard_percentage = $cache->total_count > 0 
                ? round(($cache->standard_count / $cache->total_count) * 100, 2) 
                : 0;
                
            $cache->save();
        }
    }
    
    /**
     * Check if this is the first examination for a patient in a specific month
     */
    private function isFirstExaminationInMonth(
        int $patientId, 
        int $puskesmasId, 
        int $year, 
        int $month, 
        string $diseaseType,
        int $excludeId = null
    ): bool {
        if ($diseaseType === 'ht') {
            $query = HtExamination::where('patient_id', $patientId)
                ->where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->where('month', $month);
                
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->count() === 0;
        } else {
            $query = DmExamination::where('patient_id', $patientId)
                ->where('puskesmas_id', $puskesmasId)
                ->where('year', $year)
                ->where('month', $month);
                
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->count() === 0;
        }
    }
    
    /**
     * Check if a patient is considered standard
     * A patient is considered standard if they have examinations for each month
     * since their first examination in the year
     */
    private function isPatientStandard(int $patientId, int $year, int $month, string $diseaseType): bool
    {
        // Get the first month this patient had an examination in this year
        $firstMonthQuery = $diseaseType === 'ht' 
            ? HtExamination::where('patient_id', $patientId)->where('year', $year)
            : DmExamination::where('patient_id', $patientId)->where('year', $year);
            
        $firstMonth = $firstMonthQuery->min('month');
        
        if (!$firstMonth) {
            return false;
        }
        
        // Check if patient has examinations for all months from first month to current month
        for ($m = $firstMonth; $m <= $month; $m++) {
            $hasExamination = $diseaseType === 'ht'
                ? HtExamination::where('patient_id', $patientId)
                    ->where('year', $year)
                    ->where('month', $m)
                    ->exists()
                : DmExamination::where('patient_id', $patientId)
                    ->where('year', $year)
                    ->where('month', $m)
                    ->exists();
                    
            if (!$hasExamination) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Rebuild the entire statistics cache
     */
    public function rebuildAllCache(): bool
    {
        try {
            DB::beginTransaction();
            
            // Clear existing cache
            MonthlyStatisticsCache::truncate();
            
            // Rebuild HT cache
            $this->rebuildCache('ht');
            
            // Rebuild DM cache
            $this->rebuildCache('dm');
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rebuilding cache: ' . $e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Rebuild the cache for a specific disease type
     */
    public function rebuildCache(string $diseaseType, ?int $year = null): bool
    {
        try {
            DB::beginTransaction();
            
            // If year is provided, only rebuild for that year
            if ($year) {
                // Delete existing cache for this disease type and year
                MonthlyStatisticsCache::where('disease_type', $diseaseType)
                    ->where('year', $year)
                    ->delete();
            } else {
                // Delete all cache for this disease type
                MonthlyStatisticsCache::where('disease_type', $diseaseType)->delete();
            }
            
            // Get all examinations grouped by puskesmas, year, and month
            if ($diseaseType === 'ht') {
                $query = DB::table('ht_examinations')
                    ->select('puskesmas_id', 'year', 'month', DB::raw('COUNT(DISTINCT patient_id) as patient_count'))
                    ->groupBy('puskesmas_id', 'year', 'month');
                    
                if ($year) {
                    $query->where('year', $year);
                }
                
                $examinations = $query->get();
                
                foreach ($examinations as $exam) {
                    $this->buildMonthlyStatsHt(
                        $exam->puskesmas_id, 
                        $exam->year, 
                        $exam->month
                    );
                }
            } else {
                $query = DB::table('dm_examinations')
                    ->select('puskesmas_id', 'year', 'month', DB::raw('COUNT(DISTINCT patient_id) as patient_count'))
                    ->groupBy('puskesmas_id', 'year', 'month');
                    
                if ($year) {
                    $query->where('year', $year);
                }
                
                $examinations = $query->get();
                
                foreach ($examinations as $exam) {
                    $this->buildMonthlyStatsDm(
                        $exam->puskesmas_id, 
                        $exam->year, 
                        $exam->month
                    );
                }
            }
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error rebuilding cache for ' . $diseaseType . ': ' . $e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Build monthly statistics for HT
     */
    private function buildMonthlyStatsHt(int $puskesmasId, int $year, int $month): void
    {
        // Get all patients with HT examinations in this month
        $patientIds = HtExamination::where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('month', $month)
            ->distinct('patient_id')
            ->pluck('patient_id');
            
        // Get patient information
        $patients = Patient::whereIn('id', $patientIds)->get();
        
        // Count male and female patients
        $maleCount = $patients->where('gender', 'male')->count();
        $femaleCount = $patients->where('gender', 'female')->count();
        $totalCount = $patientIds->count();
        
        // Count standard and non-standard patients
        $standardCount = 0;
        $nonStandardCount = 0;
        
        foreach ($patientIds as $patientId) {
            $isStandard = $this->isPatientStandard($patientId, $year, $month, 'ht');
            
            if ($isStandard) {
                $standardCount++;
            } else {
                $nonStandardCount++;
            }
        }
        
        // Calculate standard percentage
        $standardPercentage = $totalCount > 0 
            ? round(($standardCount / $totalCount) * 100, 2) 
            : 0;
            
        // Create or update cache record
        MonthlyStatisticsCache::updateOrCreate(
            [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => 'ht',
                'year' => $year,
                'month' => $month,
            ],
            [
                'male_count' => $maleCount,
                'female_count' => $femaleCount,
                'total_count' => $totalCount,
                'standard_count' => $standardCount,
                'non_standard_count' => $nonStandardCount,
                'standard_percentage' => $standardPercentage,
            ]
        );
    }
    
    /**
     * Build monthly statistics for DM
     */
    private function buildMonthlyStatsDm(int $puskesmasId, int $year, int $month): void
    {
        // Get all patients with DM examinations in this month
        $patientIds = DmExamination::where('puskesmas_id', $puskesmasId)
            ->where('year', $year)
            ->where('month', $month)
            ->distinct('patient_id')
            ->pluck('patient_id');
            
        // Get patient information
        $patients = Patient::whereIn('id', $patientIds)->get();
        
        // Count male and female patients
        $maleCount = $patients->where('gender', 'male')->count();
        $femaleCount = $patients->where('gender', 'female')->count();
        $totalCount = $patientIds->count();
        
        // Count standard and non-standard patients
        $standardCount = 0;
        $nonStandardCount = 0;
        
        foreach ($patientIds as $patientId) {
            $isStandard = $this->isPatientStandard($patientId, $year, $month, 'dm');
            
            if ($isStandard) {
                $standardCount++;
            } else {
                $nonStandardCount++;
            }
        }
        
        // Calculate standard percentage
        $standardPercentage = $totalCount > 0 
            ? round(($standardCount / $totalCount) * 100, 2) 
            : 0;
            
        // Create or update cache record
        MonthlyStatisticsCache::updateOrCreate(
            [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => 'dm',
                'year' => $year,
                'month' => $month,
            ],
            [
                'male_count' => $maleCount,
                'female_count' => $femaleCount,
                'total_count' => $totalCount,
                'standard_count' => $standardCount,
                'non_standard_count' => $nonStandardCount,
                'standard_percentage' => $standardPercentage,
            ]
        );
    }
}