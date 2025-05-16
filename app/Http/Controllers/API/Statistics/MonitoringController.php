<?php

namespace App\Http\Controllers\API\Statistic;

use App\Http\Controllers\Controller;
use App\Http\Resources\Statistics\MonitoringResource;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    /**
     * Get patient monitoring data for a specific month
     */
    public function index(Request $request): JsonResponse
    {
        // Validate request parameters
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'type' => 'nullable|in:all,ht,dm',
            'puskesmas_id' => 'nullable|integer|exists:puskesmas,id',
        ]);

        $year = $request->year;
        $month = $request->month;
        $diseaseType = $request->type ?? 'all';
        
        // Determine puskesmas ID (admin can specify, puskesmas users are limited to their own)
        $puskesmasId = Auth::user()->isAdmin() 
            ? $request->puskesmas_id ?? Auth::user()->puskesmas_id
            : Auth::user()->puskesmas_id;
            
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'Puskesmas ID harus ditentukan.',
            ], 400);
        }
        
        $puskesmas = Puskesmas::find($puskesmasId);
        if (!$puskesmas) {
            return response()->json([
                'message' => 'Puskesmas tidak ditemukan.',
            ], 404);
        }
        
        // Get monitoring data
        $monitoringData = $this->getMonitoringData($puskesmasId, $year, $month, $diseaseType);
        
        // Prepare response data
        $responseData = [
            'puskesmas_id' => $puskesmasId,
            'puskesmas_name' => $puskesmas->name,
            'year' => $year,
            'month' => $month,
            'month_name' => $this->getMonthName($month),
            'days_in_month' => Carbon::createFromDate($year, $month, 1)->daysInMonth,
            'disease_type' => $diseaseType,
            'patients' => $monitoringData,
            'visit_summary' => [
                'total_patients' => count($monitoringData),
                'total_visits' => array_sum(array_map(function($patient) {
                    return $patient['visit_count'];
                }, $monitoringData)),
            ],
        ];
        
        return response()->json(new MonitoringResource($responseData));
    }
    
    /**
     * Get patient monitoring data
     */
    protected function getMonitoringData($puskesmasId, $year, $month, $diseaseType): array
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
                // Get HT examinations for this patient in the specified month
                $examinations = HtExamination::where('patient_id', $patient->id)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->get()
                    ->pluck('examination_date')
                    ->map(function($date) {
                        return Carbon::parse($date)->day;
                    })
                    ->toArray();
                    
                // Create attendance array for each day in month
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
            $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereJsonContains('dm_years', $year)
                ->orderBy('name')
                ->get();
                
            foreach ($dmPatients as $patient) {
                // Get DM examinations for this patient in the specified month
                $examinations = DmExamination::where('patient_id', $patient->id)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->get()
                    ->pluck('examination_date')
                    ->map(function($date) {
                        return Carbon::parse($date)->day;
                    })
                    ->toArray();
                    
                // Create attendance array for each day in month
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
                    'disease_type' => 'dm',
                    'attendance' => $attendance,
                    'visit_count' => count($examinations)
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Helper function to get month name in Indonesian
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember'
        ];
        
        return $months[$month] ?? '';
    }
}