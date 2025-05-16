<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\PuskesmasDashboardResource;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\MonthlyStatisticsCache;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardPuskesmasController extends Controller
{
    /**
     * Display dashboard statistics for individual puskesmas
     */
    public function index(Request $request): JsonResponse
    {
        $year = $request->year ?? Carbon::now()->year;
        $puskesmasId = Auth::user()->puskesmas_id;
        
        // Validate puskesmas ID exists
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'User tidak terkait dengan puskesmas manapun.',
            ], 404);
        }
        
        $puskesmas = Puskesmas::find($puskesmasId);
        
        // Get statistics from cache
        $htData = $this->getHtStatisticsFromCache($puskesmasId, $year);
        $dmData = $this->getDmStatisticsFromCache($puskesmasId, $year);
        
        // Get yearly targets
        $htTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->first();
            
        $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->first();
        
        $htTargetCount = $htTarget ? $htTarget->target_count : 0;
        $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;
        
        // Get total registered patients
        $totalHtPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('ht_years', $year)
            ->count();
            
        $totalDmPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('dm_years', $year)
            ->count();
        
        // Calculate achievements
        $htAchievementPercentage = $htTargetCount > 0
            ? round(($htData['standard_patients'] / $htTargetCount) * 100, 2)
            : 0;
            
        $dmAchievementPercentage = $dmTargetCount > 0
            ? round(($dmData['standard_patients'] / $dmTargetCount) * 100, 2)
            : 0;
        
        // Get current month statistics
        $currentMonth = Carbon::now()->month;
        $currentMonthName = $this->getMonthName($currentMonth);
        
        $currentHtStats = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->where('month', $currentMonth)
            ->first();
            
        $currentDmStats = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->where('month', $currentMonth)
            ->first();
        
        // Prepare monthly chart data
        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        $shortMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 
                       'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        
        $htChartData = [];
        $dmChartData = [];
        
        foreach ($htData['monthly_data'] as $month => $data) {
            $htChartData[] = $data['total'];
        }
        
        foreach ($dmData['monthly_data'] as $month => $data) {
            $dmChartData[] = $data['total'];
        }
        
        $dashboardData = [
            'year' => $year,
            'puskesmas' => $puskesmas->name,
            'current_month' => $currentMonthName,
            'ht' => [
                'target' => $htTargetCount,
                'total_patients' => $htData['total_patients'],
                'achievement_percentage' => $htAchievementPercentage,
                'standard_patients' => $htData['standard_patients'],
                'registered_patients' => $totalHtPatients,
                'current_month_exams' => $currentHtStats ? $currentHtStats->total_count : 0
            ],
            'dm' => [
                'target' => $dmTargetCount,
                'total_patients' => $dmData['total_patients'],
                'achievement_percentage' => $dmAchievementPercentage,
                'standard_patients' => $dmData['standard_patients'],
                'registered_patients' => $totalDmPatients,
                'current_month_exams' => $currentDmStats ? $currentDmStats->total_count : 0
            ],
            'chart_data' => [
                'labels' => $shortMonths,
                'datasets' => [
                    [
                        'label' => 'Hipertensi (HT)',
                        'data' => $htChartData,
                        'borderColor' => '#3490dc',
                        'backgroundColor' => 'rgba(52, 144, 220, 0.1)',
                        'borderWidth' => 2,
                        'tension' => 0.4
                    ],
                    [
                        'label' => 'Diabetes Mellitus (DM)',
                        'data' => $dmChartData,
                        'borderColor' => '#f6993f',
                        'backgroundColor' => 'rgba(246, 153, 63, 0.1)',
                        'borderWidth' => 2,
                        'tension' => 0.4
                    ]
                ]
            ],
            'print_data' => [
                'puskesmas' => $puskesmas->name,
                'year' => $year,
                'current_month' => $currentMonthName,
                'date_generated' => Carbon::now()->format('d F Y H:i:s'),
                'ht' => [
                    'target' => $htTargetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htAchievementPercentage,
                    'standard_patients' => $htData['standard_patients'],
                ],
                'dm' => [
                    'target' => $dmTargetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmAchievementPercentage,
                    'standard_patients' => $dmData['standard_patients'],
                ],
                'monthly_data' => [
                    'months' => $shortMonths,
                    'ht' => $htChartData,
                    'dm' => $dmChartData
                ]
            ]
        ];
        
        return response()->json(new PuskesmasDashboardResource($dashboardData));
    }
    
    /**
     * Get HT statistics from cache
     */
    private function getHtStatisticsFromCache($puskesmasId, $year): array
    {
        // Get monthly caches for the whole year
        $yearData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->get()
            ->keyBy('month');
        
        $monthlyData = [];
        $totalPatients = 0;
        $totalStandard = 0;
        
        for ($m = 1; $m <= 12; $m++) {
            $data = $yearData->get($m);
            $monthlyData[$m] = [
                'male' => $data ? $data->male_count : 0,
                'female' => $data ? $data->female_count : 0,
                'total' => $data ? $data->total_count : 0,
                'standard' => $data ? $data->standard_count : 0,
                'non_standard' => $data ? $data->non_standard_count : 0,
            ];
            
            if ($data) {
                $totalPatients += $data->total_count;
                $totalStandard += $data->standard_count;
            }
        }
        
        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $totalStandard,
            'monthly_data' => $monthlyData
        ];
    }
    
    /**
     * Get DM statistics from cache
     */
    private function getDmStatisticsFromCache($puskesmasId, $year): array
    {
        // Get monthly caches for the whole year
        $yearData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->get()
            ->keyBy('month');
        
        $monthlyData = [];
        $totalPatients = 0;
        $totalStandard = 0;
        
        for ($m = 1; $m <= 12; $m++) {
            $data = $yearData->get($m);
            $monthlyData[$m] = [
                'male' => $data ? $data->male_count : 0,
                'female' => $data ? $data->female_count : 0,
                'total' => $data ? $data->total_count : 0,
                'standard' => $data ? $data->standard_count : 0,
                'non_standard' => $data ? $data->non_standard_count : 0,
            ];
            
            if ($data) {
                $totalPatients += $data->total_count;
                $totalStandard += $data->standard_count;
            }
        }
        
        return [
            'total_patients' => $totalPatients,
            'standard_patients' => $totalStandard,
            'monthly_data' => $monthlyData
        ];
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