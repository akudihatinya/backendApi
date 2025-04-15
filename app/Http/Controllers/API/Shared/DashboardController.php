<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Display dashboard statistics for individual puskesmas
     */
    public function puskesmasIndex(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $puskesmasId = Auth::user()->puskesmas_id;
        
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'Puskesmas tidak ditemukan.',
            ], 404);
        }
        
        $puskesmas = Puskesmas::find($puskesmasId);
        
        // Get statistics from StatisticsController
        $statisticsController = app(StatisticsController::class);
        $statisticsRequest = new Request(['year' => $year]);
        $statistics = $statisticsController->dashboardStatistics($statisticsRequest)->getData();
        
        // Process monthly data for chart
        $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        $shortMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 
                       'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        
        // Format data for chart
        $htData = [];
        $dmData = [];
        
        for ($i = 1; $i <= 12; $i++) {
            $htData[] = $statistics->ht->monthly_data->{$i}->total ?? 0;
            $dmData[] = $statistics->dm->monthly_data->{$i}->total ?? 0;
        }
        
        // Recent patient counts
        $currentMonth = Carbon::now()->month;
        $currentMonthName = $months[$currentMonth - 1];
        
        // Add additional stats
        $currentYear = Carbon::now()->year;
        $totalHtPatients = Patient::where('puskesmas_id', $puskesmasId)
                               ->where('has_ht', true)
                               ->count();
        
        $totalDmPatients = Patient::where('puskesmas_id', $puskesmasId)
                               ->where('has_dm', true)
                               ->count();
        
        $recentHtExams = HtExamination::where('puskesmas_id', $puskesmasId)
                              ->whereYear('examination_date', $currentYear)
                              ->whereMonth('examination_date', $currentMonth)
                              ->count();
        
        $recentDmExams = DmExamination::where('puskesmas_id', $puskesmasId)
                              ->whereYear('examination_date', $currentYear)
                              ->whereMonth('examination_date', $currentMonth)
                              ->count();
                              
        // Calculate controlled percentages
        $htControlledPercentage = $statistics->ht->total_patients > 0 
            ? round(($statistics->ht->controlled_patients / $statistics->ht->total_patients) * 100, 2) 
            : 0;
            
        $dmControlledPercentage = $statistics->dm->total_patients > 0 
            ? round(($statistics->dm->controlled_patients / $statistics->dm->total_patients) * 100, 2) 
            : 0;
        
        return response()->json([
            'year' => $year,
            'puskesmas' => $puskesmas->name,
            'current_month' => $currentMonthName,
            'ht' => [
                'target' => $statistics->ht->target,
                'total_patients' => $statistics->ht->total_patients,
                'achievement_percentage' => $statistics->ht->achievement_percentage,
                'controlled_patients' => $statistics->ht->controlled_patients,
                'controlled_percentage' => $htControlledPercentage,
                'registered_patients' => $totalHtPatients,
                'current_month_exams' => $recentHtExams
            ],
            'dm' => [
                'target' => $statistics->dm->target,
                'total_patients' => $statistics->dm->total_patients,
                'achievement_percentage' => $statistics->dm->achievement_percentage,
                'controlled_patients' => $statistics->dm->controlled_patients,
                'controlled_percentage' => $dmControlledPercentage,
                'registered_patients' => $totalDmPatients,
                'current_month_exams' => $recentDmExams
            ],
            'chart_data' => [
                'labels' => $shortMonths,
                'datasets' => [
                    [
                        'label' => 'Hipertensi (HT)',
                        'data' => $htData,
                        'borderColor' => '#3490dc',
                        'backgroundColor' => 'rgba(52, 144, 220, 0.1)',
                        'borderWidth' => 2,
                        'tension' => 0.4
                    ],
                    [
                        'label' => 'Diabetes Mellitus (DM)',
                        'data' => $dmData,
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
                    'target' => $statistics->ht->target,
                    'total_patients' => $statistics->ht->total_patients,
                    'achievement_percentage' => $statistics->ht->achievement_percentage,
                    'controlled_patients' => $statistics->ht->controlled_patients,
                    'controlled_percentage' => $htControlledPercentage
                ],
                'dm' => [
                    'target' => $statistics->dm->target,
                    'total_patients' => $statistics->dm->total_patients,
                    'achievement_percentage' => $statistics->dm->achievement_percentage,
                    'controlled_patients' => $statistics->dm->controlled_patients,
                    'controlled_percentage' => $dmControlledPercentage
                ],
                'monthly_data' => [
                    'ht' => array_map(function($month) use ($statistics) {
                        return [
                            'month' => $month,
                            'total' => $statistics->ht->monthly_data->{$month}->total ?? 0
                        ];
                    }, range(1, 12)),
                    'dm' => array_map(function($month) use ($statistics) {
                        return [
                            'month' => $month,
                            'total' => $statistics->dm->monthly_data->{$month}->total ?? 0
                        ];
                    }, range(1, 12))
                ]
            ]
        ]);
    }
    
    /**
     * Display aggregated dashboard statistics for Dinas (admin)
     * Shows summary of all puskesmas
     */
    public function dinasIndex(Request $request)
    {
        // Verify user is admin
        if (!Auth::user()->is_admin) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }
        
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null; // Null for yearly view, 1-12 for monthly view
        
        // Get all puskesmas
        $allPuskesmas = Puskesmas::all();
        $puskesmasIds = $allPuskesmas->pluck('id')->toArray();
        
        // Get statistics from StatisticsController for each puskesmas
        $statisticsController = app(StatisticsController::class);
        
        // Prepare data containers
        $totalHtTarget = 0;
        $totalDmTarget = 0;
        $totalHtPatients = 0;
        $totalDmPatients = 0;
        $totalHtControlled = 0;
        $totalDmControlled = 0;
        
        // Monthly data containers
        $htMonthlyData = array_fill(1, 12, 0);
        $dmMonthlyData = array_fill(1, 12, 0);
        
        // Puskesmas ranked data
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
            
            // Get individual puskesmas statistics
            $statsRequest = new Request([
                'year' => $year,
                'month' => $month,
                'puskesmas_id' => $puskesmas->id
            ]);
            
            // Using the index method with specific filters instead of direct method access
            $response = $statisticsController->index($statsRequest)->getData();
            $puskesmasData = $response->data[0] ?? null;
            
            if ($puskesmasData) {
                $htData = $puskesmasData->ht;
                $dmData = $puskesmasData->dm;
                
                // Add to totals
                $totalHtPatients += $htData->total_patients;
                $totalDmPatients += $dmData->total_patients;
                $totalHtControlled += $htData->controlled_patients;
                $totalDmControlled += $dmData->controlled_patients;
                
                // Aggregate monthly data
                for ($i = 1; $i <= 12; $i++) {
                    $htMonthlyData[$i] += $htData->monthly_data->{$i}->total ?? 0;
                    $dmMonthlyData[$i] += $dmData->monthly_data->{$i}->total ?? 0;
                }
                
                // Store puskesmas statistics for ranking
                $puskesmasStats[] = [
                    'id' => $puskesmas->id,
                    'name' => $puskesmas->name,
                    'ht' => [
                        'target' => $htTargetCount,
                        'total_patients' => $htData->total_patients,
                        'achievement_percentage' => $htData->achievement_percentage,
                        'controlled_patients' => $htData->controlled_patients,
                        'controlled_percentage' => $htData->total_patients > 0 
                            ? round(($htData->controlled_patients / $htData->total_patients) * 100, 2)
                            : 0,
                    ],
                    'dm' => [
                        'target' => $dmTargetCount,
                        'total_patients' => $dmData->total_patients,
                        'achievement_percentage' => $dmData->achievement_percentage,
                        'controlled_patients' => $dmData->controlled_patients,
                        'controlled_percentage' => $dmData->total_patients > 0 
                            ? round(($dmData->controlled_patients / $dmData->total_patients) * 100, 2)
                            : 0,
                    ],
                    'combined_achievement' => $htData->achievement_percentage + $dmData->achievement_percentage
                ];
            }
        }
        
        // Sort puskesmas by combined achievement percentage
        usort($puskesmasStats, function ($a, $b) {
            return $b['combined_achievement'] <=> $a['combined_achievement'];
        });
        
        // Add ranking
        foreach ($puskesmasStats as $index => $stat) {
            $puskesmasStats[$index]['ranking'] = $index + 1;
        }
        
        // Calculate overall achievement and controlled percentages
        $htAchievementPercentage = $totalHtTarget > 0 
            ? round(($totalHtPatients / $totalHtTarget) * 100, 2)
            : 0;
            
        $dmAchievementPercentage = $totalDmTarget > 0 
            ? round(($totalDmPatients / $totalDmTarget) * 100, 2)
            : 0;
            
        $htControlledPercentage = $totalHtPatients > 0 
            ? round(($totalHtControlled / $totalHtPatients) * 100, 2)
            : 0;
            
        $dmControlledPercentage = $totalDmPatients > 0 
            ? round(($totalDmControlled / $totalDmPatients) * 100, 2)
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
        
        // Generate response with print-friendly data format
        return response()->json([
            'year' => $year,
            'month' => $month,
            'month_name' => $month ? $this->getMonthName($month) : null,
            'total_puskesmas' => count($allPuskesmas),
            'summary' => [
                'ht' => [
                    'target' => $totalHtTarget,
                    'total_patients' => $totalHtPatients,
                    'achievement_percentage' => $htAchievementPercentage,
                    'controlled_patients' => $totalHtControlled,
                    'controlled_percentage' => $htControlledPercentage,
                ],
                'dm' => [
                    'target' => $totalDmTarget,
                    'total_patients' => $totalDmPatients,
                    'achievement_percentage' => $dmAchievementPercentage,
                    'controlled_patients' => $totalDmControlled,
                    'controlled_percentage' => $dmControlledPercentage,
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
                        'controlled_patients' => $totalHtControlled,
                        'controlled_percentage' => $htControlledPercentage,
                    ],
                    'dm' => [
                        'target' => $totalDmTarget,
                        'total_patients' => $totalDmPatients,
                        'achievement_percentage' => $dmAchievementPercentage,
                        'controlled_patients' => $totalDmControlled,
                        'controlled_percentage' => $dmControlledPercentage,
                    ]
                ],
                'puskesmas_data' => $puskesmasStats,
                'monthly_data' => [
                    'months' => $shortMonths,
                    'ht' => array_values($htMonthlyData),
                    'dm' => array_values($dmMonthlyData)
                ]
            ]
        ]);
    }
    
    /**
     * Helper function to get month name
     */
    protected function getMonthName($month)
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