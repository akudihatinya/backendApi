<?php

namespace App\Services;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\MonthlyStatisticsCache;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsCacheService
{
    /**
     * Update cache when new examination is created
     */
    public function updateCacheOnExaminationCreate($examination, string $diseaseType): void
    {
        $patient = Patient::find($examination->patient_id);
        $date = Carbon::parse($examination->examination_date);

        // Cek apakah pasien sudah pernah datang di bulan ini
        $previousExams = $this->getPreviousExaminations(
            $examination->patient_id,
            $examination->puskesmas_id,
            $date->year,
            $date->month,
            $diseaseType,
            $examination->id
        );

        // Jika ini kunjungan pertama di bulan ini
        if ($previousExams->isEmpty()) {
            // Cek apakah pasien standar atau tidak
            $isStandard = $this->checkIfPatientIsStandard(
                $examination->patient_id,
                $date->year,
                $date->month,
                $diseaseType
            );

            // Update cache
            $cache = MonthlyStatisticsCache::firstOrNew([
                'puskesmas_id' => $examination->puskesmas_id,
                'disease_type' => $diseaseType,
                'year' => $date->year,
                'month' => $date->month,
            ]);

            if (!$cache->exists) {
                $cache->fill([
                    'male_count' => 0,
                    'female_count' => 0,
                    'total_count' => 0,
                    'standard_count' => 0,
                    'non_standard_count' => 0,
                    'standard_percentage' => 0.00,
                ]);
            }

            $cache->incrementPatient($patient->gender, $isStandard);
        }
    }

    /**
     * Rebuild all cache from scratch
     */
    public function rebuildAllCache(): void
    {
        // Clear existing cache
        MonthlyStatisticsCache::truncate();

        // Rebuild HT cache
        $this->rebuildCacheForDiseaseType('ht');

        // Rebuild DM cache
        $this->rebuildCacheForDiseaseType('dm');
    }

    /**
     * Rebuild cache for specific disease type
     */
    private function rebuildCacheForDiseaseType(string $diseaseType): void
    {
        // Get all examinations grouped by puskesmas, year, and month
        $examinations = $diseaseType === 'ht' ? HtExamination::all() : DmExamination::all();

        // Group examinations by puskesmas, year, and month
        $groupedExams = $examinations->groupBy([
            'puskesmas_id',
            function ($exam) {
                return Carbon::parse($exam->examination_date)->year;
            },
            function ($exam) {
                return Carbon::parse($exam->examination_date)->month;
            }
        ]);

        foreach ($groupedExams as $puskesmasId => $yearGroups) {
            foreach ($yearGroups as $year => $monthGroups) {
                foreach ($monthGroups as $month => $monthExams) {
                    $this->calculateMonthlyStats($puskesmasId, $diseaseType, $year, $month, $monthExams);
                }
            }
        }
    }

    /**
     * Calculate monthly statistics
     */
    private function calculateMonthlyStats($puskesmasId, $diseaseType, $year, $month, $monthExams): void
    {
        $patientIds = $monthExams->pluck('patient_id')->unique();
        $patients = Patient::whereIn('id', $patientIds)->get()->keyBy('id');

        $maleCount = 0;
        $femaleCount = 0;
        $standardCount = 0;
        $nonStandardCount = 0;

        foreach ($patientIds as $patientId) {
            $patient = $patients->get($patientId);
            if (!$patient) continue;

            // Count by gender
            if ($patient->gender === 'male') {
                $maleCount++;
            } else {
                $femaleCount++;
            }

            // Check if patient is standard
            $isStandard = $this->checkIfPatientIsStandard($patientId, $year, $month, $diseaseType);
            
            if ($isStandard) {
                $standardCount++;
            } else {
                $nonStandardCount++;
            }
        }

        $totalCount = $maleCount + $femaleCount;
        $standardPercentage = $totalCount > 0 ? round(($standardCount / $totalCount) * 100, 2) : 0;

        MonthlyStatisticsCache::updateOrCreateStatistics($puskesmasId, $diseaseType, $year, $month, [
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'total_count' => $totalCount,
            'standard_count' => $standardCount,
            'non_standard_count' => $nonStandardCount,
            'standard_percentage' => $standardPercentage,
        ]);
    }

    /**
     * Check if patient is standard
     */
    private function checkIfPatientIsStandard($patientId, $year, $currentMonth, $diseaseType): bool
    {
        // Get first visit in the year
        $firstVisit = $this->getFirstVisitInYear($patientId, $year, $diseaseType);
        
        if (!$firstVisit) {
            return false;
        }

        $firstMonth = Carbon::parse($firstVisit->examination_date)->month;

        // Patient is standard if they have visits for every month from first visit until current month
        for ($month = $firstMonth; $month <= $currentMonth; $month++) {
            if (!$this->hasVisitInMonth($patientId, $year, $month, $diseaseType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get first visit in year
     */
    private function getFirstVisitInYear($patientId, $year, $diseaseType)
    {
        $query = $diseaseType === 'ht' ? HtExamination::query() : DmExamination::query();
        
        return $query->where('patient_id', $patientId)
            ->whereYear('examination_date', $year)
            ->orderBy('examination_date')
            ->first();
    }

    /**
     * Check if patient has visit in specific month
     */
    private function hasVisitInMonth($patientId, $year, $month, $diseaseType): bool
    {
        $query = $diseaseType === 'ht' ? HtExamination::query() : DmExamination::query();
        
        return $query->where('patient_id', $patientId)
            ->whereYear('examination_date', $year)
            ->whereMonth('examination_date', $month)
            ->exists();
    }

    /**
     * Get previous examinations in the same month
     */
    private function getPreviousExaminations($patientId, $puskesmasId, $year, $month, $diseaseType, $excludeId = null)
    {
        $query = $diseaseType === 'ht' ? HtExamination::query() : DmExamination::query();
        
        return $query->where('patient_id', $patientId)
            ->where('puskesmas_id', $puskesmasId)
            ->whereYear('examination_date', $year)
            ->whereMonth('examination_date', $month)
            ->when($excludeId, function ($q) use ($excludeId) {
                return $q->where('id', '!=', $excludeId);
            })
            ->get();
    }
}