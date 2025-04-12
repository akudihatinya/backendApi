<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        
        $statistics = [];
        
        $puskesmas = Puskesmas::all();
        
        foreach ($puskesmas as $puskesmas) {
            $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->first();
                
            $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                ->where('disease_type', 'dm')
                ->where('year', $year)
                ->first();
            
            $htData = $this->getHtStatistics($puskesmas->id, $year);
            $dmData = $this->getDmStatistics($puskesmas->id, $year);
            
            $statistics[] = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
                'ht' => [
                    'target' => $htTarget ? $htTarget->target_count : 0,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htTarget && $htTarget->target_count > 0
                        ? round(($htData['total_patients'] / $htTarget->target_count) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'controlled_patients' => $htData['controlled_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ],
                'dm' => [
                    'target' => $dmTarget ? $dmTarget->target_count : 0,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmTarget && $dmTarget->target_count > 0
                        ? round(($dmData['total_patients'] / $dmTarget->target_count) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'controlled_patients' => $dmData['controlled_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ],
            ];
        }
        
        // Sort by achievement percentage (HT + DM) for ranking
        usort($statistics, function ($a, $b) {
            $aTotal = $a['ht']['achievement_percentage'] + $a['dm']['achievement_percentage'];
            $bTotal = $b['ht']['achievement_percentage'] + $b['dm']['achievement_percentage'];
            return $bTotal <=> $aTotal;
        });
        
        // Add ranking
        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }
        
        return response()->json([
            'year' => $year,
            'statistics' => $statistics,
        ]);
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
        
        // Group examinations by month
        $examinationsByMonth = $examinations->groupBy(function ($item) {
            return Carbon::parse($item->examination_date)->month;
        });
        
        // Prepare monthly data structure (1-12)
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
            ];
        }
        
        // Fill monthly data
        foreach ($examinationsByMonth as $month => $monthExaminations) {
            $patientIdsByMonth = $monthExaminations->pluck('patient_id')->unique();
            
            $maleCount = 0;
            $femaleCount = 0;
            
            foreach ($patientIdsByMonth as $patientId) {
                $patient = $htPatients->firstWhere('id', $patientId);
                if ($patient->gender === 'male') {
                    $maleCount++;
                } elseif ($patient->gender === 'female') {
                    $femaleCount++;
                }
            }
            
            $monthlyData[$month] = [
                'male' => $maleCount,
                'female' => $femaleCount,
                'total' => $patientIdsByMonth->count(),
            ];
        }
        
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
            'total_patients' => $htPatients->count(),
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
            'monthly_data' => $monthlyData,
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
        
        // Group examinations by month
        $examinationsByMonth = $examinations->groupBy(function ($item) {
            return Carbon::parse($item->examination_date)->month;
        });
        
        // Prepare monthly data structure (1-12)
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = [
                'male' => 0,
                'female' => 0,
                'total' => 0,
            ];
        }
        
        // Fill monthly data
        foreach ($examinationsByMonth as $month => $monthExaminations) {
            $patientIdsByMonth = $monthExaminations->pluck('patient_id')->unique();
            
            $maleCount = 0;
            $femaleCount = 0;
            
            foreach ($patientIdsByMonth as $patientId) {
                $patient = $dmPatients->firstWhere('id', $patientId);
                if ($patient->gender === 'male') {
                    $maleCount++;
                } elseif ($patient->gender === 'female') {
                    $femaleCount++;
                }
            }
            
            $monthlyData[$month] = [
                'male' => $maleCount,
                'female' => $femaleCount,
                'total' => $patientIdsByMonth->count(),
            ];
        }
        
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
            'total_patients' => $dmPatients->count(),
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
            'monthly_data' => $monthlyData,
        ];
    }
    
    public function exportStatistics(Request $request)
    {
        // Implementation for export functionality
        // This would connect to a PDF or Excel generating service
        
        return response()->json([
            'message' => 'Export functionality would be implemented here',
        ]);
    }
}