<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\AdminDashboardResource;
use App\Models\MonthlyStatisticsCache;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardAdminController extends Controller
{
    /**
     * Display dashboard statistics for Dinas Kesehatan (admin)
     */
    public function index(Request $request): JsonResponse
    {
        // Verify user is admin
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }
        
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null; // Null for yearly view
        
        // Get all puskesmas
        $allPuskesmas = Puskesmas::all();
        $puskesmasIds = $allPuskesmas->pluck('id')->toArray();
        
        // Initialize counters
        $totalHtTarget = 0;
        $totalDmTarget = 0;
        $totalHtPatients = 0;
        $totalDmPatients = 0;
        $totalHtStandard = 0;
        $totalDmStandard = 0;
        
        // Monthly data containers
        $htMonthlyData = array_fill(1, 12, 0);
        $dmMonthlyData = array_fill(1, 12, 0);
        
        // Puskesmas statistics container
        $puskesmasStats = [];
        
        // Get yearly targets for all puskesmas
        $htTargets = YearlyTarget::where('year', $year)
            ->where('disease_type', 'ht')
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get()
            ->keyBy('puskesmas_id');
                        
        $dmTargets = YearlyTarget::where('year', $year)
            ->where('disease_type', 'dm')
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->get()
            ->keyBy('puskesmas_id');
        
        // For each puskesmas, gather statistics data
        foreach ($allPuskesmas as $puskesmas) {
            $htTarget = $htTargets->get($puskesmas->id);
            $dmTarget = $dmTargets->get($puskesmas->id);
            
            $htTargetCount = $htTarget ? $htTarget->target_count : 0;
            $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;
            
            // Add to total targets
            $totalHtTarget += $htTargetCount;
            $totalDmTarget += $dmTargetCount;
            
            // Get statistics from cache
            $htData = $this->getHtStatisticsFromCache($puskesmas->id, $year, $month);
            $dmData = $this->getDmStatisticsFromCache($puskesmas->id, $year, $month);
            
            // Add to totals
            $totalHtPatients += $htData['total_patients'];
            $totalDmPatients += $dmData['total_patients'];
            $totalHtStandard += $htData['standard_patients'];
            $totalDmStandard += $dmData['standard_patients'];
            
            // Aggregate monthly data
            foreach ($htData['monthly_data'] as $m => $data) {
                $htMonthlyData[$m] += $data['total'];
            }
            
            foreach ($dmData['monthly_data'] as $m => $data) {
                $dmMonthlyData[$m] += $data['total'];
            }
            
            // Store puskesmas statistics for ranking
            $htAchievement = $htTargetCount > 0 
                ? round(($htData['standard_patients'] / $htTargetCount) * 100, 2)
                : 0;
                
            $dmAchievement = $dmTargetCount > 0 
                ? round(($dmData['standard_patients'] / $dmTargetCount) * 100, 2)
                : 0;
            
            $puskesmasStats[] = [
                'id' => $puskesmas->id,
                'name' => $puskesmas->name,
                'ht' => [
                    'target' => $htTargetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htAchievement,
                    'standard_patients' => $htData['standard_patients'],
                ],
                'dm' => [
                    'target' => $dmTargetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmAchievement,
                    'standard_patients' => $dmData['standard_patients'],
                ],
                'combined_achievement' => $htAchievement + $dmAchievement
            ];
        }
        
        // Sort puskesmas by combined achievement percentage
        usort($puskesmasStats, function ($a, $b) {
            return $b['combined_achievement'] <=> $a['combined_achievement'];
        });
        
        // Add ranking
        foreach ($puskesmasStats as $index => $stat) {
            $puskesmasStats[$index]['ranking'] = $index + 1;
        }
        
        // Calculate overall achievement percentages
        $htAchievementPercentage = $totalHtTarget > 0 
            ? round(($totalHtStandard / $totalHtTarget) * 100, 2)
            : 0;
            
        $dmAchievementPercentage = $totalDmTarget > 0 
            ? round(($totalDmStandard / $totalDmTarget) * 100, 2)
            : 0;
        
        // Prepare monthly labels
        $shortMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 
                      'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        
        // Prepare chart data
        $chartData = [
            'labels' => $shortMonths,
            'datasets' => [
                [
                    'label' => 'Hipertensi (HT)',
                    'data' => array_values($htMonthlyData),
                    'borderColor' => '#3490dc',
                    'backgroundColor' => 'rgba(52, 144, 220, 0.1)',
                    'borderWidth' => 2,
                    'tension' => 0.4
                ],
                [
                    'label' => 'Diabetes Mellitus (DM)',
                    'data' => array_values($dmMonthlyData),
                    'borderColor' => '#f6993f',
                    'backgroundColor' => 'rgba(246, 153, 63, 0.1)',
                    'borderWidth' => 2,
                    'tension' => 0.4
                ]
            ]
        ];
        
        // Top and bottom 5 puskesmas by achievement
        $topPuskesmas = array_slice($puskesmasStats, 0, 5);
        $bottomPuskesmas = array_slice($puskesmasStats, -5);
        
        // Generate response data
        $dashboardData = [
            'year' => $year,
            'month' => $month,
            'month_name' => $month ? $this->getMonthName($month) : null,
            'total_puskesmas' => count($allPuskesmas),
            'summary' => [
                'ht' => [
                    'target' => $totalHtTarget,
                    'total_patients' => $totalHtPatients,
                    'achievement_percentage' => $htAchievementPercentage,
                    'standard_patients' => $totalHtStandard,
                ],
                'dm' => [
                    'target' => $totalDmTarget,
                    'total_patients' => $totalDmPatients,
                    'achievement_percentage' => $dmAchievementPercentage,
                    'standard_patients' => $totalDmStandard,
                ]
            ],
            'chart_data' => $chartData,
            'rankings' => [
                'top_puskesmas' => $topPuskesmas,
                'bottom_puskesmas' => $bottomPuskesmas
            ],
            'all_puskesmas' => $puskesmasStats,
            'print_data' => [
                'title' => 'Rekap Statistik Puskesmas',
                'subtitle' => $month 
                    ? "Bulan " . $this->getMonthName($month) . " Tahun " . $year
                    : "Tahun " . $year,
                'date_generated' => Carbon::now()->format('d F Y H:i:s'),
                'total_puskesmas' => count($allPuskesmas),
                'summary' => [
                    'ht' => [
                        'target' => $totalHtTarget,
                        'total_patients' => $totalHtPatients,
                        'achievement_percentage' => $htAchievementPercentage,
                        'standard_patients' => $totalHtStandard,
                    ],
                    'dm' => [
                        'target' => $totalDmTarget,
                        'total_patients' => $totalDmPatients,
                        'achievement_percentage' => $dmAchievementPercentage,
                        'standard_patients' => $totalDmStandard,
                    ]
                ],
                'puskesmas_data' => $puskesmasStats,
                'monthly_data' => [
                    'months' => $shortMonths,
                    'ht' => array_values($htMonthlyData),
                    'dm' => array_values($dmMonthlyData)
                ]
            ]
        ];
        
        return response()->json(new AdminDashboardResource($dashboardData));
    }
    
    /**
     * Get HT statistics from cache
     * @param int $puskesmasId
     * @param int $year
     * @param int|null $month
     * @return array
     */
    private function getHtStatisticsFromCache($puskesmasId, $year, $month = null): array
    {
        if ($month !== null) {
            // Get specific month data
            $monthData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->where('month', $month)
                ->first();
                
            return [
                'total_patients' => $monthData ? $monthData->total_count : 0,
                'standard_patients' => $monthData ? $monthData->standard_count : 0,
                'monthly_data' => [
                    $month => [
                        'male' => $monthData ? $monthData->male_count : 0,
                        'female' => $monthData ? $monthData->female_count : 0,
                        'total' => $monthData ? $monthData->total_count : 0,
                        'standard' => $monthData ? $monthData->standard_count : 0,
                        'non_standard' => $monthData ? $monthData->non_standard_count : 0,
                    ]
                ]
            ];
        }
        
        // Get full year data
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
     * @param int $puskesmasId
     * @param int $year
     * @param int|null $month
     * @return array
     */
    private function getDmStatisticsFromCache($puskesmasId, $year, $month = null): array
    {
        if ($month !== null) {
            // Get specific month data
            $monthData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
                ->where('disease_type', 'dm')
                ->where('year', $year)
                ->where('month', $month)
                ->first();
                
            return [
                'total_patients' => $monthData ? $monthData->total_count : 0,
                'standard_patients' => $monthData ? $monthData->standard_count : 0,
                'monthly_data' => [
                    $month => [
                        'male' => $monthData ? $monthData->male_count : 0,
                        'female' => $monthData ? $monthData->female_count : 0,
                        'total' => $monthData ? $monthData->total_count : 0,
                        'standard' => $monthData ? $monthData->standard_count : 0,
                        'non_standard' => $monthData ? $monthData->non_standard_count : 0,
                    ]
                ]
            ];
        }
        
        // Get full year data
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