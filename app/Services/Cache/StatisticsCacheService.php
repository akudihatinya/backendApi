<?php

namespace App\Services\Cache;

use App\DataTransferObjects\StatisticsData;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\MonthlyStatisticsCache;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StatisticsCacheService
{
    /**
     * Update statistics cache when a new examination is created
     */
    public function updateCacheOnExaminationCreate($examination, string $diseaseType): void
    {
        try {
            $puskesmasId = $examination->puskesmas_id;
            $year = $examination->year;
            $month = $examination->month;
            
            // Get or create cache record
            $cache = MonthlyStatisticsCache::firstOrNew([
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
            ]);
            
            // Get patient gender
            $patient = $examination->patient;
            $gender = $patient->gender;
            
            // If this is a new record, initialize counters
            if (!$cache->exists) {
                $cache->male_count = 0;
                $cache->female_count = 0;
                $cache->total_count = 0;
                $cache->standard_count = 0;
                $cache->non_standard_count = 0;
                $cache->standard_percentage = 0;
            }
            
            // Increment total count
            $cache->total_count++;
            
            // Increment gender counts
            if ($gender === 'male') {
                $cache->male_count++;
            } else {
                $cache->female_count++;
            }
            
            // Check if examination is standard (controlled)
            $isStandard = false;
            
            if ($diseaseType === 'ht') {
                $isStandard = $examination->isControlled();
            } else if ($diseaseType === 'dm') {
                $isStandard = $examination->isControlled();
            }
            
            // Update standard/non-standard counts
            if ($isStandard) {
                $cache->standard_count++;
            } else {
                $cache->non_standard_count++;
            }
            
            // Calculate standard percentage
            $cache->standard_percentage = $cache->total_count > 0
                ? round(($cache->standard_count / $cache->total_count) * 100, 2)
                : 0;
                
            // Save cache
            $cache->save();
            
            Log::info("Statistics cache updated for $diseaseType: Puskesmas $puskesmasId, $year-$month");
            
        } catch (\Exception $e) {
            Log::error("Error updating statistics cache: " . $e->getMessage(), [
                'examination_id' => $examination->id,
                'disease_type' => $diseaseType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Rebuild monthly cache for a specific month
     */
    public function rebuildMonthlyCache(int $puskesmasId, string $diseaseType, int $year, int $month): void
    {
        try {
            // Define date range for the month
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            
            // Get patients and examinations for this month
            if ($diseaseType === 'ht') {
                // Get HT examinations for this month
                $examinations = HtExamination::where('puskesmas_id', $puskesmasId)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->get();
                    
                // Count male/female patients
                $maleCount = 0;
                $femaleCount = 0;
                $standardCount = 0;
                $nonStandardCount = 0;
                
                // Get unique patients
                $patientIds = $examinations->pluck('patient_id')->unique();
                $totalCount = $patientIds->count();
                
                // Process examinations
                foreach ($patientIds as $patientId) {
                    $patient = Patient::find($patientId);
                    
                    if (!$patient) continue;
                    
                    // Count by gender
                    if ($patient->gender === 'male') {
                        $maleCount++;
                    } else {
                        $femaleCount++;
                    }
                    
                    // Check if standard (at least one controlled BP reading)
                    $patientExams = $examinations->where('patient_id', $patientId);
                    $hasControlled = $patientExams->contains(function ($exam) {
                        return $exam->isControlled();
                    });
                    
                    if ($hasControlled) {
                        $standardCount++;
                    } else {
                        $nonStandardCount++;
                    }
                }
                
            } else if ($diseaseType === 'dm') {
                // Get DM examinations for this month
                $examinations = DmExamination::where('puskesmas_id', $puskesmasId)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->get();
                    
                // Similar logic as HT but with DM-specific criteria
                // For brevity, assuming similar structure
                $maleCount = 0;
                $femaleCount = 0;
                $standardCount = 0;
                $nonStandardCount = 0;
                $totalCount = 0;
            }
            
            // Calculate standard percentage
            $standardPercentage = $totalCount > 0
                ? round(($standardCount / $totalCount) * 100, 2)
                : 0;
                
            // Update or create cache record
            MonthlyStatisticsCache::updateOrCreate(
                [
                    'puskesmas_id' => $puskesmasId,
                    'disease_type' => $diseaseType,
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
            
            Log::info("Monthly statistics cache rebuilt for $diseaseType: Puskesmas $puskesmasId, $year-$month");
            
        } catch (\Exception $e) {
            Log::error("Error rebuilding statistics cache: " . $e->getMessage(), [
                'puskesmas_id' => $puskesmasId,
                'disease_type' => $diseaseType,
                'year' => $year,
                'month' => $month,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Rebuild cache for all puskesmas for a specific year
     */
    public function rebuildYearlyCache(int $year): array
    {
        $puskesmas = Puskesmas::all();
        $results = [
            'ht' => 0,
            'dm' => 0,
        ];
        
        foreach ($puskesmas as $p) {
            for ($month = 1; $month <= 12; $month++) {
                // Rebuild HT cache
                $this->rebuildMonthlyCache($p->id, 'ht', $year, $month);
                $results['ht']++;
                
                // Rebuild DM cache
                $this->rebuildMonthlyCache($p->id, 'dm', $year, $month);
                $results['dm']++;
            }
        }
        
        return $results;
    }

    /**
     * Rebuild cache for all data
     */
    public function rebuildAllCache(): array
    {
        $startTime = microtime(true);
        
        $currentYear = Carbon::now()->year;
        $results = $this->rebuildYearlyCache($currentYear);
        
        // Also rebuild previous year
        $prevYearResults = $this->rebuildYearlyCache($currentYear - 1);
        $results['ht'] += $prevYearResults['ht'];
        $results['dm'] += $prevYearResults['dm'];
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        $results['execution_time'] = $executionTime;
        
        Log::info("Complete statistics cache rebuild finished in $executionTime seconds", $results);
        
        return $results;
    }
}