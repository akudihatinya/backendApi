<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;
        $year = $request->year ?? Carbon::now()->year;
        
        $htTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->first();
            
        $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->first();
        
        $htPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->where('has_ht', true)
            ->count();
            
        $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->where('has_dm', true)
            ->count();
        
        $htAchievementPercentage = $htTarget && $htTarget->target_count > 0
            ? round(($htPatients / $htTarget->target_count) * 100, 2)
            : 0;
            
        $dmAchievementPercentage = $dmTarget && $dmTarget->target_count > 0
            ? round(($dmPatients / $dmTarget->target_count) * 100, 2)
            : 0;
        
        // Get monthly statistics
        $monthlyStats = $this->getMonthlyStats($puskesmasId, $year);
        
        // Get standard and controlled patients
        $htStats = $this->getHtStatistics($puskesmasId, $year);
        $dmStats = $this->getDmStatistics($puskesmasId, $year);
        
        return response()->json([
            'year' => $year,
            'ht' => [
                'target' => $htTarget ? $htTarget->target_count : 0,
                'total_patients' => $htPatients,
                'achievement_percentage' => $htAchievementPercentage,
                'standard_patients' => $htStats['standard_patients'],
                'controlled_patients' => $htStats['controlled_patients'],
            ],
            'dm' => [
                'target' => $dmTarget ? $dmTarget->target_count : 0,
                'total_patients' => $dmPatients,
                'achievement_percentage' => $dmAchievementPercentage,
                'standard_patients' => $dmStats['standard_patients'],
                'controlled_patients' => $dmStats['controlled_patients'],
            ],
            'monthly_stats' => $monthlyStats,
        ]);
    }
    
    private function getMonthlyStats($puskesmasId, $year)
    {
        $monthlyStats = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            
            // Get HT patients for this month
            $htMalePatients = HtExamination::where('puskesmas_id', $puskesmasId)
                ->whereBetween('examination_date', [$startDate, $endDate])
                ->join('patients', 'ht_examinations.patient_id', '=', 'patients.id')
                ->where('patients.gender', 'male')
                ->distinct('ht_examinations.patient_id')
                ->count('ht_examinations.patient_id');
                
            $htFemalePatients = HtExamination::where('puskesmas_id', $puskesmasId)
                ->whereBetween('examination_date', [$startDate, $endDate])
                ->join('patients', 'ht_examinations.patient_id', '=', 'patients.id')
                ->where('patients.gender', 'female')
                ->distinct('ht_examinations.patient_id')
                ->count('ht_examinations.patient_id');
            
            // Get DM patients for this month
            $dmMalePatients = DmExamination::where('puskesmas_id', $puskesmasId)
                ->whereBetween('examination_date', [$startDate, $endDate])
                ->join('patients', 'dm_examinations.patient_id', '=', 'patients.id')
                ->where('patients.gender', 'male')
                ->distinct('dm_examinations.patient_id')
                ->count('dm_examinations.patient_id');
                
            $dmFemalePatients = DmExamination::where('puskesmas_id', $puskesmasId)
                ->whereBetween('examination_date', [$startDate, $endDate])
                ->join('patients', 'dm_examinations.patient_id', '=', 'patients.id')
                ->where('patients.gender', 'female')
                ->distinct('dm_examinations.patient_id')
                ->count('dm_examinations.patient_id');
            
            $monthlyStats[] = [
                'month' => $month,
                'month_name' => Carbon::createFromDate($year, $month, 1)->locale('id')->monthName,
                'ht' => [
                    'male' => $htMalePatients,
                    'female' => $htFemalePatients,
                    'total' => $htMalePatients + $htFemalePatients,
                ],
                'dm' => [
                    'male' => $dmMalePatients,
                    'female' => $dmFemalePatients,
                    'total' => $dmMalePatients + $dmFemalePatients,
                ],
            ];
        }
        
        return $monthlyStats;
    }
    
    private function getHtStatistics($puskesmasId, $year)
    {
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        
        // Get all patients with HT in this puskesmas
        $htPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->where('has_ht', true)
            ->get();
        
        $htPatientIds = $htPatients->pluck('id')->toArray();
        
        // Get examinations for these patients in the specified year
        $examinations = HtExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $htPatientIds)
            ->whereBetween('examination_date', [$startDate, $endDate])
            ->get();
        
        // Group examinations by patient
        $examinationsByPatient = $examinations->groupBy('patient_id');
        
        // Calculate standard patients (those who came regularly since their first visit)
        $standardPatients = 0;
        foreach ($examinationsByPatient as $patientId => $patientExaminations) {
            $firstMonth = Carbon::parse($patientExaminations->min('examination_date'))->month;
            $isStandard = true;
            
            // Check if the patient came every month since their first visit until December
            for ($month = $firstMonth; $month <= 12; $month++) {
                $hasVisitInMonth = $patientExaminations->contains(function ($examination) use ($month) {
                    return Carbon::parse($examination->examination_date)->month === $month;
                });
                
                if (!$hasVisitInMonth) {
                    $isStandard = false;
                    break;
                }
            }
            
            if ($isStandard) {
                $standardPatients++;
            }
        }
        
        // Calculate controlled patients (at least 3 examinations with BP 90-139/60-89)
        $controlledPatients = 0;
        foreach ($examinationsByPatient as $patientId => $patientExaminations) {
            $controlledExams = $patientExaminations->filter(function ($examination) {
                return $examination->systolic >= 90 && $examination->systolic <= 139 &&
                       $examination->diastolic >= 60 && $examination->diastolic <= 89;
            });
            
            if ($controlledExams->count() >= 3) {
                $controlledPatients++;
            }
        }
        
        return [
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
        ];
    }
    
    private function getDmStatistics($puskesmasId, $year)
    {
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        
        // Get all patients with DM in this puskesmas
        $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->where('has_dm', true)
            ->get();
        
        $dmPatientIds = $dmPatients->pluck('id')->toArray();
        
        // Get examinations for these patients in the specified year
        $examinations = DmExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $dmPatientIds)
            ->whereBetween('examination_date', [$startDate, $endDate])
            ->get();
        
        // Group examinations by patient
        $examinationsByPatient = $examinations->groupBy('patient_id');
        
        // Calculate standard patients (those who came regularly since their first visit)
        $standardPatients = 0;
        foreach ($examinationsByPatient as $patientId => $patientExaminations) {
            $firstMonth = Carbon::parse($patientExaminations->min('examination_date'))->month;
            $isStandard = true;
            
            // Check if the patient came every month since their first visit until December
            for ($month = $firstMonth; $month <= 12; $month++) {
                $hasVisitInMonth = $patientExaminations->contains(function ($examination) use ($month) {
                    return Carbon::parse($examination->examination_date)->month === $month;
                });
                
                if (!$hasVisitInMonth) {
                    $isStandard = false;
                    break;
                }
            }
            
            if ($isStandard) {
                $standardPatients++;
            }
        }
        
        // Calculate controlled patients
        $controlledPatients = 0;
        foreach ($examinationsByPatient as $patientId => $patientExaminations) {
            // Check HbA1c criteria (at least 1 HbA1c < 7%)
            $hasControlledHbA1c = $patientExaminations->contains(function ($examination) {
                return $examination->examination_type === 'hba1c' && $examination->result < 7;
            });
            
            // Check GDP criteria (at least 3 GDP < 126 mg/dl)
            $controlledGdpCount = $patientExaminations->filter(function ($examination) {
                return $examination->examination_type === 'gdp' && $examination->result < 126;
            })->count();
            
            // Check GD2JPP criteria (at least 3 GD2JPP < 200 mg/dl)
            $controlledGd2jppCount = $patientExaminations->filter(function ($examination) {
                return $examination->examination_type === 'gd2jpp' && $examination->result < 200;
            })->count();
            
            if ($hasControlledHbA1c || $controlledGdpCount >= 3 || $controlledGd2jppCount >= 3) {
                $controlledPatients++;
            }
        }
        
        return [
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
        ];
    }
}