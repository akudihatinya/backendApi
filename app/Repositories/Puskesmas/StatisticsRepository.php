<?php

namespace App\Repositories\Puskesmas;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\MonthlyStatisticsCache;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsRepository
{
    /**
     * Get puskesmas statistics for a specific period
     */
    public function getPuskesmasStatistics(int $puskesmasId, int $year, int $month = null, string $diseaseType = 'all')
    {
        $result = [
            'puskesmas_id' => $puskesmasId,
            'puskesmas_name' => Puskesmas::find($puskesmasId)->name,
            'year' => $year,
            'month' => $month,
        ];
        
        // Get HT statistics if requested
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $result['ht'] = $this->getHtStatistics($puskesmasId, $year, $month);
        }
        
        // Get DM statistics if requested
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $result['dm'] = $this->getDmStatistics($puskesmasId, $year, $month);
        }
        
        return $result;
    }
    
    /**
     * Get HT statistics for a specific period
     */
    public function getHtStatistics(int $puskesmasId, int $year, int $month = null)
    {
        // Get yearly target
        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->first();
            
        $targetCount = $target ? $target->target_count : 0;
        
        // If month is specified, get monthly statistics
        if ($month) {
            $cache = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->where('month', $month)
                ->first();
                
            // If cache exists, use it
            if ($cache) {
                return [
                    'target' => $targetCount,
                    'total_patients' => $cache->total_count,
                    'standard_patients' => $cache->standard_count,
                    'non_standard_patients' => $cache->non_standard_count,
                    'male_patients' => $cache->male_count,
                    'female_patients' => $cache->female_count,
                    'achievement_percentage' => $targetCount > 0 
                        ? round(($cache->standard_count / $targetCount) * 100, 2) 
                        : 0,
                    'standard_percentage' => $cache->standard_percentage ?? 0,
                ];
            }
        }
        
        // Get yearly statistics from cache
        $caches = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year);
            
        if ($month) {
            $caches->where('month', $month);
        }
        
        $caches = $caches->get();
        
        // Calculate totals
        $totalMale = $caches->sum('male_count');
        $totalFemale = $caches->sum('female_count');
        $totalCount = $caches->sum('total_count');
        $standardCount = $caches->sum('standard_count');
        $nonStandardCount = $caches->sum('non_standard_count');
        
        // Calculate percentages
        $achievementPercentage = $targetCount > 0 
            ? round(($standardCount / $targetCount) * 100, 2) 
            : 0;
            
        $standardPercentage = $totalCount > 0 
            ? round(($standardCount / $totalCount) * 100, 2) 
            : 0;
            
        // Prepare monthly data
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthCache = $caches->where('month', $m)->first();
            
            $monthlyData[$m] = [
                'male' => $monthCache ? $monthCache->male_count : 0,
                'female' => $monthCache ? $monthCache->female_count : 0,
                'total' => $monthCache ? $monthCache->total_count : 0,
                'standard' => $monthCache ? $monthCache->standard_count : 0,
                'non_standard' => $monthCache ? $monthCache->non_standard_count : 0,
                'percentage' => $targetCount > 0 && $monthCache
                    ? round(($monthCache->standard_count / ($targetCount / 12)) * 100, 2)
                    : 0,
            ];
        }
        
        return [
            'target' => $targetCount,
            'total_patients' => $totalCount,
            'standard_patients' => $standardCount,
            'non_standard_patients' => $nonStandardCount,
            'male_patients' => $totalMale,
            'female_patients' => $totalFemale,
            'achievement_percentage' => $achievementPercentage,
            'standard_percentage' => $standardPercentage,
            'monthly_data' => $monthlyData,
        ];
    }
    
    /**
     * Get DM statistics for a specific period
     */
    public function getDmStatistics(int $puskesmasId, int $year, int $month = null)
    {
        // Implementation is similar to getHtStatistics but for DM
        // For brevity, not duplicating the code here
        
        // Sample structure:
        return [
            'target' => 0,
            'total_patients' => 0,
            'standard_patients' => 0,
            'non_standard_patients' => 0,
            'male_patients' => 0,
            'female_patients' => 0,
            'achievement_percentage' => 0,
            'standard_percentage' => 0,
            'monthly_data' => [],
        ];
    }
    
    /**
     * Get district-wide statistics for all puskesmas
     */
    public function getDistrictStatistics(int $year, int $month = null, string $diseaseType = 'all')
    {
        $puskesmasIds = Puskesmas::pluck('id')->toArray();
        $result = [];
        
        foreach ($puskesmasIds as $puskesmasId) {
            $result[] = $this->getPuskesmasStatistics($puskesmasId, $year, $month, $diseaseType);
        }
        
        // Sort by achievement percentage
        if ($diseaseType === 'ht') {
            usort($result, function ($a, $b) {
                return ($b['ht']['achievement_percentage'] ?? 0) <=> ($a['ht']['achievement_percentage'] ?? 0);
            });
        } elseif ($diseaseType === 'dm') {
            usort($result, function ($a, $b) {
                return ($b['dm']['achievement_percentage'] ?? 0) <=> ($a['dm']['achievement_percentage'] ?? 0);
            });
        } else {
            usort($result, function ($a, $b) {
                $aTotal = ($a['ht']['achievement_percentage'] ?? 0) + ($a['dm']['achievement_percentage'] ?? 0);
                $bTotal = ($b['ht']['achievement_percentage'] ?? 0) + ($b['dm']['achievement_percentage'] ?? 0);
                return $bTotal <=> $aTotal;
            });
        }
        
        // Add ranking
        foreach ($result as $index => $stat) {
            $result[$index]['ranking'] = $index + 1;
        }
        
        return $result;
    }
    
    /**
     * Get patient monitoring data for a specific month
     */
    public function getMonitoringData(int $puskesmasId, int $year, int $month, string $diseaseType = 'all')
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $daysInMonth = $endDate->day;
        
        $result = [];
        
        // Get HT patient data if requested
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htPatients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereJsonContains('ht_years', $year)
                ->orderBy('name')
                ->get();
                
            foreach ($htPatients as $patient) {
                $examinations = HtExamination::where('patient_id', $patient->id)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->get()
                    ->pluck('examination_date')
                    ->map(function ($date) {
                        return Carbon::parse($date)->day;
                    })
                    ->toArray();
                    
                $attendance = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $attendance[$day] = in_array($day, $examinations);
                }
                
                $result[] = [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'medical_record_number' => $patient->medical_record_number,
                    'gender' => $patient->gender,
                    'age' => $patient->age,
                    'disease_type' => 'ht',
                    'attendance' => $attendance,
                    'visit_count' => count($examinations)
                ];
            }
        }
        
        // Get DM patient data if requested
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            // Implementation is similar to HT but for DM patients
            // For brevity, not duplicating the code here
        }
        
        return $result;
    }
}