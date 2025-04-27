<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use App\Models\MonthlyStatisticsCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StatisticsController extends Controller
{
    /**
     * Display a listing of statistics.
     */
    public function index(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null;
        $diseaseType = $request->type ?? 'all';
        $perPage = $request->per_page ?? 15;

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Validasi bulan jika diisi
        if ($month !== null) {
            $month = intval($month);
            if ($month < 1 || $month > 12) {
                return response()->json([
                    'message' => 'Parameter month tidak valid. Gunakan angka 1-12.',
                ], 400);
            }
        }

        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Jika ada filter nama puskesmas (hanya untuk admin)
        if (Auth::user()->is_admin && $request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Jika user bukan admin, filter data ke puskesmas user
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Log this issue to debug
                Log::warning('Puskesmas user without puskesmas_id: ' . Auth::user()->id);

                // Try to find a puskesmas with matching name as fallback
                $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . Auth::user()->name . '%')->first();

                if ($puskesmasWithSameName) {
                    $puskesmasQuery->where('id', $puskesmasWithSameName->id);

                    // Update the user with the correct puskesmas_id for future requests
                    Auth::user()->update(['puskesmas_id' => $puskesmasWithSameName->id]);

                    Log::info('Updated user ' . Auth::user()->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
                } else {
                    // Kembalikan data kosong dengan pesan
                    return response()->json([
                        'message' => 'User puskesmas tidak terkait dengan puskesmas manapun. Hubungi administrator.',
                        'data' => [],
                        'meta' => [
                            'current_page' => 1,
                            'from' => 0,
                            'last_page' => 1,
                            'per_page' => $perPage,
                            'to' => 0,
                            'total' => 0,
                        ],
                    ], 400);
                }
            }
        }

        $puskesmasAll = $puskesmasQuery->get();

        // If no puskesmas found, return specific error
        if ($puskesmasAll->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang ditemukan.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => 0,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'to' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $statistics = [];

        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Ambil data HT jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $htTargetCount = $htTarget ? $htTarget->target_count : 0;

                $data['ht'] = [
                    'target' => $htTargetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htTargetCount > 0
                        ? round(($htData['standard_patients'] / $htTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'non_standard_patients' => $htData['non_standard_patients'],
                    'male_patients' => $htData['male_patients'],
                    'female_patients' => $htData['female_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Ambil data DM jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;

                $data['dm'] = [
                    'target' => $dmTargetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmTargetCount > 0
                        ? round(($dmData['standard_patients'] / $dmTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'non_standard_patients' => $dmData['non_standard_patients'],
                    'male_patients' => $dmData['male_patients'],
                    'female_patients' => $dmData['female_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $statistics[] = $data;
        }

        // Sort by achievement percentage berdasarkan jenis penyakit
        if ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return $b['ht']['achievement_percentage'] <=> $a['ht']['achievement_percentage'];
            });
        } elseif ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return $b['dm']['achievement_percentage'] <=> $a['dm']['achievement_percentage'];
            });
        } else {
            // Sort by combined achievement percentage (HT + DM) for ranking
            usort($statistics, function ($a, $b) {
                $aTotal = ($a['ht']['achievement_percentage'] ?? 0) + ($a['dm']['achievement_percentage'] ?? 0);
                $bTotal = ($b['ht']['achievement_percentage'] ?? 0) + ($b['dm']['achievement_percentage'] ?? 0);
                return $bTotal <=> $aTotal;
            });
        }

        // Add ranking
        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }

        // Paginate the results
        $page = $request->page ?? 1;
        $offset = ($page - 1) * $perPage;

        $paginatedItems = array_slice($statistics, $offset, $perPage);

        $paginator = new LengthAwarePaginator(
            $paginatedItems,
            count($statistics),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Dashboard statistics API untuk frontend
     */
    public function dashboardStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $type = $request->type ?? 'all'; // Default 'all', bisa juga 'ht' atau 'dm'
        $user = Auth::user();

        // Validasi nilai type
        if (!in_array($type, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Siapkan query untuk mengambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!$user->isAdmin()) {
            $puskesmasQuery->where('id', $user->puskesmas_id);
        }

        $puskesmasAll = $puskesmasQuery->get();

        // Siapkan data untuk dikirim ke frontend
        $data = [];

        foreach ($puskesmasAll as $puskesmas) {
            $puskesmasData = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Tambahkan data HT jika diperlukan
            if ($type === 'all' || $type === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

                $targetCount = $htTarget ? $htTarget->target_count : 0;

                $puskesmasData['ht'] = [
                    'target' => $targetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($htData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Tambahkan data DM jika diperlukan
            if ($type === 'all' || $type === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year);

                $targetCount = $dmTarget ? $dmTarget->target_count : 0;

                $puskesmasData['dm'] = [
                    'target' => $targetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $targetCount > 0
                        ? round(($dmData['standard_patients'] / $targetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $data[] = $puskesmasData;
        }

        // Urutkan data berdasarkan achievement_percentage
        usort($data, function ($a, $b) use ($type) {
            $aValue = $type === 'dm' ?
                ($a['dm']['achievement_percentage'] ?? 0) : ($a['ht']['achievement_percentage'] ?? 0);

            $bValue = $type === 'dm' ?
                ($b['dm']['achievement_percentage'] ?? 0) : ($b['ht']['achievement_percentage'] ?? 0);

            return $bValue <=> $aValue;
        });

        // Tambahkan ranking
        foreach ($data as $index => $item) {
            $data[$index]['ranking'] = $index + 1;
        }

        return response()->json([
            'year' => $year,
            'type' => $type,
            'data' => $data
        ]);
    }

    /**
     * Mendapatkan statistik HT dengan breakdown bulanan dari cache
     */
    private function getHtStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        // Get yearly target
        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->first();

        $yearlyTarget = $target ? $target->target_count : 0;

        if ($month !== null) {
            // Get specific month data from cache
            $monthData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
                ->where('disease_type', 'ht')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if (!$monthData) {
                return [
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

            $monthlyPercentage = $yearlyTarget > 0 ? round(($monthData->total_count / $yearlyTarget) * 100, 2) : 0;

            return [
                'total_patients' => $monthData->total_count,
                'standard_patients' => $monthData->standard_count,
                'non_standard_patients' => $monthData->non_standard_count,
                'male_patients' => $monthData->male_count,
                'female_patients' => $monthData->female_count,
                'achievement_percentage' => $monthlyPercentage,
                'standard_percentage' => $monthData->standard_percentage,
                'monthly_data' => [
                    $month => [
                        'male' => $monthData->male_count,
                        'female' => $monthData->female_count,
                        'total' => $monthData->total_count,
                        'standard' => $monthData->standard_count,
                        'non_standard' => $monthData->non_standard_count,
                        'percentage' => $monthlyPercentage,
                    ]
                ],
            ];
        }

        // Get all months data from cache
        $monthlyData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'ht')
            ->where('year', $year)
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $yearlyData = [];
        $totalPatients = 0;
        $totalStandard = 0;
        $totalNonStandard = 0;
        $totalMale = 0;
        $totalFemale = 0;

        for ($m = 1; $m <= 12; $m++) {
            $data = $monthlyData->get($m);
            
            if ($data) {
                // For yearly totals, we need to count unique patients across months
                // Since we're using cache, we'll sum the monthly totals
                // Note: This might count some patients multiple times if they visit in different months
                $totalMale += $data->male_count;
                $totalFemale += $data->female_count;
                $totalPatients += $data->total_count;
                $totalStandard += $data->standard_count;
                $totalNonStandard += $data->non_standard_count;
                
                $monthlyPercentage = $yearlyTarget > 0 ? round(($data->total_count / $yearlyTarget) * 100, 2) : 0;
                
                $yearlyData[$m] = [
                    'male' => $data->male_count,
                    'female' => $data->female_count,
                    'total' => $data->total_count,
                    'standard' => $data->standard_count,
                    'non_standard' => $data->non_standard_count,
                    'percentage' => $monthlyPercentage,
                ];
            } else {
                $yearlyData[$m] = [
                    'male' => 0,
                    'female' => 0,
                    'total' => 0,
                    'standard' => 0,
                    'non_standard' => 0,
                    'percentage' => 0,
                ];
            }
        }

        // For yearly statistics, we need to get unique patient counts
        // We'll query the database directly for accuracy
        $uniquePatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('htExaminations', function ($query) use ($year) {
                $query->whereYear('examination_date', $year);
            })
            ->select('id', 'gender')
            ->get();

        $uniqueMaleCount = $uniquePatients->where('gender', 'male')->count();
        $uniqueFemaleCount = $uniquePatients->where('gender', 'female')->count();
        $uniqueTotalCount = $uniquePatients->count();

        $yearlyPercentage = $yearlyTarget > 0 ? round(($totalStandard / $yearlyTarget) * 100, 2) : 0;
        $standardPercentage = $uniqueTotalCount > 0 ? round(($totalStandard / $uniqueTotalCount) * 100, 2) : 0;

        return [
            'total_patients' => $uniqueTotalCount,
            'standard_patients' => $totalStandard,
            'non_standard_patients' => $uniqueTotalCount - $totalStandard,
            'male_patients' => $uniqueMaleCount,
            'female_patients' => $uniqueFemaleCount,
            'achievement_percentage' => $yearlyPercentage,
            'standard_percentage' => $standardPercentage,
            'monthly_data' => $yearlyData,
        ];
    }

    /**
     * Mendapatkan statistik DM dengan breakdown bulanan dari cache
     */
    private function getDmStatisticsWithMonthlyBreakdown($puskesmasId, $year, $month = null)
    {
        // Get yearly target
        $target = YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->first();

        $yearlyTarget = $target ? $target->target_count : 0;

        if ($month !== null) {
            // Get specific month data from cache
            $monthData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
                ->where('disease_type', 'dm')
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if (!$monthData) {
                return [
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

            $monthlyPercentage = $yearlyTarget > 0 ? round(($monthData->total_count / $yearlyTarget) * 100, 2) : 0;

            return [
                'total_patients' => $monthData->total_count,
                'standard_patients' => $monthData->standard_count,
                'non_standard_patients' => $monthData->non_standard_count,
                'male_patients' => $monthData->male_count,
                'female_patients' => $monthData->female_count,
                'achievement_percentage' => $monthlyPercentage,
                'standard_percentage' => $monthData->standard_percentage,
                'monthly_data' => [
                    $month => [
                        'male' => $monthData->male_count,
                        'female' => $monthData->female_count,
                        'total' => $monthData->total_count,
                        'standard' => $monthData->standard_count,
                        'non_standard' => $monthData->non_standard_count,
                        'percentage' => $monthlyPercentage,
                    ]
                ],
            ];
        }

        // Get all months data from cache
        $monthlyData = MonthlyStatisticsCache::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', 'dm')
            ->where('year', $year)
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $yearlyData = [];
        $totalPatients = 0;
        $totalStandard = 0;
        $totalNonStandard = 0;
        $totalMale = 0;
        $totalFemale = 0;

        for ($m = 1; $m <= 12; $m++) {
            $data = $monthlyData->get($m);
            
            if ($data) {
                $totalMale += $data->male_count;
                $totalFemale += $data->female_count;
                $totalPatients += $data->total_count;
                $totalStandard += $data->standard_count;
                $totalNonStandard += $data->non_standard_count;
                
                $monthlyPercentage = $yearlyTarget > 0 ? round(($data->total_count / $yearlyTarget) * 100, 2) : 0;
                
                $yearlyData[$m] = [
                    'male' => $data->male_count,
                    'female' => $data->female_count,
                    'total' => $data->total_count,
                    'standard' => $data->standard_count,
                    'non_standard' => $data->non_standard_count,
                    'percentage' => $monthlyPercentage,
                ];
            } else {
                $yearlyData[$m] = [
                    'male' => 0,
                    'female' => 0,
                    'total' => 0,
                    'standard' => 0,
                    'non_standard' => 0,
                    'percentage' => 0,
                ];
            }
        }

        // For yearly statistics, we need to get unique patient counts
        $uniquePatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereHas('dmExaminations', function ($query) use ($year) {
                $query->whereYear('examination_date', $year);
            })
            ->select('id', 'gender')
            ->get();

        $uniqueMaleCount = $uniquePatients->where('gender', 'male')->count();
        $uniqueFemaleCount = $uniquePatients->where('gender', 'female')->count();
        $uniqueTotalCount = $uniquePatients->count();

        $yearlyPercentage = $yearlyTarget > 0 ? round(($totalStandard / $yearlyTarget) * 100, 2) : 0;
        $standardPercentage = $uniqueTotalCount > 0 ? round(($totalStandard / $uniqueTotalCount) * 100, 2) : 0;

        return [
            'total_patients' => $uniqueTotalCount,
            'standard_patients' => $totalStandard,
            'non_standard_patients' => $uniqueTotalCount - $totalStandard,
            'male_patients' => $uniqueMaleCount,
            'female_patients' => $uniqueFemaleCount,
            'achievement_percentage' => $yearlyPercentage,
            'standard_percentage' => $standardPercentage,
            'monthly_data' => $yearlyData,
        ];
    }
    /**
     * Export statistik bulanan atau tahunan ke format PDF atau Excel
     */
    public function exportStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? null; // null = laporan tahunan
        $diseaseType = $request->type ?? 'all'; // Nilai default: 'all', bisa juga 'ht' atau 'dm'

        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        // Validasi bulan jika diisi
        if ($month !== null) {
            $month = intval($month);
            if ($month < 1 || $month > 12) {
                return response()->json([
                    'message' => 'Parameter month tidak valid. Gunakan angka 1-12.',
                ], 400);
            }
        }

        // Format export (pdf atau excel)
        $format = $request->format ?? 'excel';
        if (!in_array($format, ['pdf', 'excel'])) {
            return response()->json([
                'message' => 'Format tidak valid. Gunakan pdf atau excel.',
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Jika ada filter nama puskesmas (hanya untuk admin)
        if (Auth::user()->is_admin && $request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Implementasi logika ekspor:
        // - Admin dapat mencetak rekap atau laporan puskesmas tertentu
        // - User hanya dapat mencetak data miliknya sendiri

        // Jika user bukan admin, HARUS filter data ke puskesmas user
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Jika user bukan admin dan tidak terkait dengan puskesmas, kembalikan error
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk mencetak statistik.',
                ], 403);
            }
        }

        // Cek apakah ini permintaan rekap (hanya untuk admin)
        $isRecap = Auth::user()->is_admin && (!$request->has('puskesmas_id') || $puskesmasQuery->count() > 1);

        $puskesmasAll = $puskesmasQuery->get();

        // Jika tidak ada puskesmas yang ditemukan
        if ($puskesmasAll->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada data puskesmas yang sesuai dengan filter.',
            ], 404);
        }

        $statistics = [];

        foreach ($puskesmasAll as $puskesmas) {
            $data = [
                'puskesmas_id' => $puskesmas->id,
                'puskesmas_name' => $puskesmas->name,
            ];

            // Ambil data HT jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $htTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'ht')
                    ->where('year', $year)
                    ->first();

                $htData = $this->getHtStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $htTargetCount = $htTarget ? $htTarget->target_count : 0;
                if ($month !== null && $htTargetCount > 0) {
                    // Perkiraan target bulanan = target tahunan / 12
                    $htTargetCount = ceil($htTargetCount / 12);
                }

                $data['ht'] = [
                    'target' => $htTargetCount,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htTargetCount > 0
                        ? round(($htData['total_patients'] / $htTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $htData['standard_patients'],
                    'non_standard_patients' => $htData['non_standard_patients'],
                    'male_patients' => $htData['male_patients'],
                    'female_patients' => $htData['female_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Ambil data DM jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->getDmStatisticsWithMonthlyBreakdown($puskesmas->id, $year, $month);

                // Jika filter bulan digunakan, kalkulasi persentase pencapaian berdasarkan target bulanan
                $dmTargetCount = $dmTarget ? $dmTarget->target_count : 0;
                if ($month !== null && $dmTargetCount > 0) {
                    // Perkiraan target bulanan = target tahunan / 12
                    $dmTargetCount = ceil($dmTargetCount / 12);
                }

                $data['dm'] = [
                    'target' => $dmTargetCount,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmTargetCount > 0
                        ? round(($dmData['total_patients'] / $dmTargetCount) * 100, 2)
                        : 0,
                    'standard_patients' => $dmData['standard_patients'],
                    'non_standard_patients' => $dmData['non_standard_patients'],
                    'male_patients' => $dmData['male_patients'],
                    'female_patients' => $dmData['female_patients'],
                    'monthly_data' => $dmData['monthly_data'],
                ];
            }

            $statistics[] = $data;
        }

        // Sort by achievement percentage berdasarkan jenis penyakit
        if ($diseaseType === 'ht') {
            usort($statistics, function ($a, $b) {
                return $b['ht']['achievement_percentage'] <=> $a['ht']['achievement_percentage'];
            });
        } elseif ($diseaseType === 'dm') {
            usort($statistics, function ($a, $b) {
                return $b['dm']['achievement_percentage'] <=> $a['dm']['achievement_percentage'];
            });
        } else {
            // Sort by combined achievement percentage (HT + DM) for ranking
            usort($statistics, function ($a, $b) {
                $aTotal = ($a['ht']['achievement_percentage'] ?? 0) + ($a['dm']['achievement_percentage'] ?? 0);
                $bTotal = ($b['ht']['achievement_percentage'] ?? 0) + ($b['dm']['achievement_percentage'] ?? 0);
                return $bTotal <=> $aTotal;
            });
        }

        // Add ranking
        foreach ($statistics as $index => $stat) {
            $statistics[$index]['ranking'] = $index + 1;
        }

        // Buat nama file
        $filename = "";

        // Tentukan jenis laporan berdasarkan parameter
        if ($month === null) {
            // Laporan tahunan
            $reportType = "laporan_tahunan";
        } else {
            // Laporan bulanan
            $reportType = "laporan_bulanan";
        }

        // Tambahkan prefix "rekap" jika ini adalah rekap (untuk admin)
        if (Auth::user()->is_admin && $isRecap) {
            $filename .= "rekap_";
        }

        $filename .= $reportType . "_";

        if ($diseaseType !== 'all') {
            $filename .= $diseaseType . "_";
        }

        $filename .= $year;

        if ($month !== null) {
            $filename .= "_" . str_pad($month, 2, '0', STR_PAD_LEFT);
        }

        // Jika user bukan admin ATAU admin yang mencetak laporan spesifik puskesmas,
        // tambahkan nama puskesmas pada filename
        if (!Auth::user()->is_admin) {
            $puskesmasName = Puskesmas::find(Auth::user()->puskesmas_id)->name ?? '';
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmasName));
        } elseif (Auth::user()->is_admin && !$isRecap) {
            // Admin mencetak laporan untuk satu puskesmas spesifik
            $puskesmasName = $puskesmasAll->first()->name ?? '';
            $filename .= "_" . str_replace(' ', '_', strtolower($puskesmasName));
        }

        // Proses export sesuai format
        if ($format === 'pdf') {
            return $this->exportToPdf($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
        } else {
            return $this->exportToExcel($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType);
        }
    }

    /**
     * Endpoint khusus untuk export data HT
     */
    public function exportHtStatistics(Request $request)
    {
        $request->merge(['type' => 'ht']);
        return $this->exportStatistics($request);
    }

    /**
     * Endpoint khusus untuk export data DM
     */
    public function exportDmStatistics(Request $request)
    {
        $request->merge(['type' => 'dm']);
        return $this->exportStatistics($request);
    }

    /**
     * Endpoint untuk export laporan pemantauan pasien (attendance)
     */
    public function exportMonitoringReport(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $month = $request->month ?? Carbon::now()->month;
        $diseaseType = $request->type ?? 'all';
        $format = $request->format ?? 'excel';

        // Validasi parameter
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
        }

        if (!in_array($format, ['pdf', 'excel'])) {
            return response()->json([
                'message' => 'Format tidak valid. Gunakan pdf atau excel.',
            ], 400);
        }

        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // User bukan admin hanya bisa lihat puskesmasnya sendiri
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses untuk mencetak laporan pemantauan.',
                ], 403);
            }
        } elseif ($request->has('puskesmas_id')) {
            // Admin bisa filter berdasarkan puskesmas_id
            $puskesmasQuery->where('id', $request->puskesmas_id);
        }

        $puskesmas = $puskesmasQuery->first();
        if (!$puskesmas) {
            return response()->json([
                'message' => 'Puskesmas tidak ditemukan.',
            ], 404);
        }

        // Ambil data pasien dan kedatangan
        $patientData = $this->getPatientAttendanceData($puskesmas->id, $year, $month, $diseaseType);

        // Buat nama file
        $filename = "laporan_pemantauan_";
        if ($diseaseType !== 'all') {
            $filename .= $diseaseType . "_";
        }

        $monthName = $this->getMonthName($month);
        $filename .= $year . "_" . str_pad($month, 2, '0', STR_PAD_LEFT) . "_";
        $filename .= str_replace(' ', '_', strtolower($puskesmas->name));

        // Export sesuai format
        if ($format === 'pdf') {
            return $this->exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename);
        } else {
            return $this->exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename);
        }
    }
    
    /**
     * Export laporan statistik ke format PDF menggunakan Dompdf
     */
    protected function exportToPdf($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        $title = "";

        // Tentukan jenis judul berdasarkan tipe laporan
        $reportTypeLabel = $reportType === "laporan_tahunan"
            ? "Laporan Tahunan"
            : "Laporan Bulanan";

        if ($diseaseType === 'ht') {
            $title = "$reportTypeLabel Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title = "$reportTypeLabel Diabetes Mellitus (DM)";
        } else {
            $title = "$reportTypeLabel Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }

        // Tambahkan kata "Rekap" jika ini adalah rekap untuk admin
        if ($isRecap) {
            $title = "Rekap " . $title;
        }

        // Jika bukan rekap, tambahkan nama puskesmas
        if (!$isRecap) {
            $puskesmasName = $statistics[0]['puskesmas_name'];
            $title .= " - " . $puskesmasName;
        }

        if ($month !== null) {
            $monthName = $this->getMonthName($month);
            $subtitle = "Bulan $monthName Tahun $year";
        } else {
            $subtitle = "Tahun $year";
        }

        $data = [
            'title' => $title,
            'subtitle' => $subtitle,
            'year' => $year,
            'month' => $month,
            'month_name' => $month !== null ? $this->getMonthName($month) : null,
            'type' => $diseaseType,
            'statistics' => $statistics,
            'is_recap' => $isRecap,
            'report_type' => $reportType,
            'generated_at' => Carbon::now()->format('d F Y H:i'),
            'generated_by' => Auth::user()->name,
            'user_role' => Auth::user()->is_admin ? 'Admin' : 'Petugas Puskesmas',
        ];

        // Generate PDF
        $pdf = PDF::loadView('exports.statistics_pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        // Simpan PDF ke storage dan return download response
        $pdfFilename = $filename . '.pdf';
        Storage::put('public/exports/' . $pdfFilename, $pdf->output());

        return response()->download(storage_path('app/public/exports/' . $pdfFilename), $pdfFilename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export laporan statistik ke format Excel menggunakan PhpSpreadsheet
     */
    protected function exportToExcel($statistics, $year, $month, $diseaseType, $filename, $isRecap, $reportType)
    {
        // Buat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Tentukan jenis judul berdasarkan tipe laporan
        $reportTypeLabel = $reportType === "laporan_tahunan"
            ? "Laporan Tahunan"
            : "Laporan Bulanan";

        // Set judul
        if ($diseaseType === 'ht') {
            $title = "$reportTypeLabel Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title = "$reportTypeLabel Diabetes Mellitus (DM)";
        } else {
            $title = "$reportTypeLabel Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }

        // Tambahkan kata "Rekap" jika ini adalah rekap untuk admin
        if ($isRecap) {
            $title = "Rekap " . $title;
        }

        // Jika bukan rekap, tambahkan nama puskesmas
        if (!$isRecap) {
            $puskesmasName = $statistics[0]['puskesmas_name'];
            $title .= " - " . $puskesmasName;
        }

        if ($month !== null) {
            $monthName = $this->getMonthName($month);
            $title .= " - Bulan $monthName Tahun $year";
        } else {
            $title .= " - Tahun $year";
        }

        // Judul spreadsheet
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Tambahkan informasi user yang mengekspor
        $exportInfo = "Diekspor oleh: " . Auth::user()->name . " (" .
            (Auth::user()->is_admin ? "Admin" : "Petugas Puskesmas") . ")";
        $sheet->setCellValue('A2', $exportInfo);
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Tanggal generate
        $sheet->setCellValue('A3', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A3:K3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Spasi
        $sheet->setCellValue('A4', '');

        // Header kolom
        $row = 5;

        // Jika ini adalah rekap, tampilkan kolom ranking dan puskesmas
        if ($isRecap) {
            $sheet->setCellValue('A' . $row, 'No');
            $sheet->setCellValue('B' . $row, 'Puskesmas');
            $col = 'C';
        } else {
            // Jika ini untuk satu puskesmas saja, tidak perlu tampilkan kolom ranking dan puskesmas
            $col = 'A';
        }

        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $sheet->setCellValue($col++ . $row, 'Target HT');
            $sheet->setCellValue($col++ . $row, 'Total Pasien HT');
            $sheet->setCellValue($col++ . $row, 'Pencapaian HT (%)');
            $sheet->setCellValue($col++ . $row, 'Pasien Standar HT');
            $sheet->setCellValue($col++ . $row, 'Pasien Tidak Standar HT');
            $sheet->setCellValue($col++ . $row, 'Pasien Laki-laki HT');
            $sheet->setCellValue($col++ . $row, 'Pasien Perempuan HT');
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $sheet->setCellValue($col++ . $row, 'Target DM');
            $sheet->setCellValue($col++ . $row, 'Total Pasien DM');
            $sheet->setCellValue($col++ . $row, 'Pencapaian DM (%)');
            $sheet->setCellValue($col++ . $row, 'Pasien Standar DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Tidak Standar DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Laki-laki DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Perempuan DM');
        }

        // Style header
        $lastCol = --$col;

        // Header range berbeda tergantung jenis laporan
        $headerColStart = $isRecap ? 'A' : 'A';
        $headerRange = $headerColStart . $row . ':' . $lastCol . $row;

        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Data
        foreach ($statistics as $index => $stat) {
            $row++;
            
            if ($isRecap) {
                $sheet->setCellValue('A' . $row, $stat['ranking']);
                $sheet->setCellValue('B' . $row, $stat['puskesmas_name']);
                $col = 'C';
            } else {
                $col = 'A';
            }

            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $sheet->setCellValue($col++ . $row, $stat['ht']['target'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['total_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['achievement_percentage'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['non_standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['male_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['ht']['female_patients'] ?? 0);
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $sheet->setCellValue($col++ . $row, $stat['dm']['target'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['total_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['achievement_percentage'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['non_standard_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['male_patients'] ?? 0);
                $sheet->setCellValue($col++ . $row, $stat['dm']['female_patients'] ?? 0);
            }
        }

        // Untuk laporan tahunan, tambahkan sheet data bulanan
        if ($month === null) {
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $this->addMonthlyDataSheet($spreadsheet, $statistics, 'ht', $year, $isRecap);
            }

            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $this->addMonthlyDataSheet($spreadsheet, $statistics, 'dm', $year, $isRecap);
            }
        }

        // Simpan file
        $writer = new Xlsx($spreadsheet);
        $excelFilename = $filename . '.xlsx';
        $path = storage_path('app/public/exports/' . $excelFilename);
        $writer->save($path);

        return response()->download($path, $excelFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Tambahkan sheet data bulanan ke spreadsheet untuk laporan tahunan
     */
    protected function addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year, $isRecap = false)
    {
        // Buat sheet baru
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data Bulanan ' . strtoupper($diseaseType));

        // Set judul
        $title = $diseaseType === 'ht'
            ? "Data Bulanan Hipertensi (HT) - Tahun " . $year
            : "Data Bulanan Diabetes Mellitus (DM) - Tahun " . $year;

        // Tambahkan kata "Rekap" jika ini adalah rekap untuk admin
        if ($isRecap) {
            $title = "Rekap " . $title;
        }

        // Jika bukan admin, atau admin melihat satu puskesmas saja
        if (!$isRecap) {
            $puskesmasName = $statistics[0]['puskesmas_name'];
            $title .= " - " . $puskesmasName;
        }

        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:O1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Tambahkan informasi user yang mengekspor
        $exportInfo = "Diekspor oleh: " . Auth::user()->name . " (" .
            (Auth::user()->is_admin ? "Admin" : "Petugas Puskesmas") . ")";
        $sheet->setCellValue('A2', $exportInfo);
        $sheet->mergeCells('A2:O2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Tanggal generate
        $sheet->setCellValue('A3', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A3:O3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Spasi
        $sheet->setCellValue('A4', '');

        // Header utama berbeda tergantung jenis laporan
        $row = 5;

        if ($isRecap) {
            // Jika ini adalah rekap, tampilkan kolom ranking dan puskesmas
            $sheet->setCellValue('A' . $row, 'No');
            $sheet->setCellValue('B' . $row, 'Puskesmas');
            $startCol = 'C';
        } else {
            // Jika ini untuk satu puskesmas saja, tidak perlu tampilkan kolom ranking dan puskesmas
            $startCol = 'A';
        }

        // Array bulan (untuk header)
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

        // Set header bulan
        $col = $startCol;
        foreach ($months as $monthName) {
            $sheet->setCellValue($col++ . $row, $monthName);
        }

        // Set header total
        $sheet->setCellValue($col . $row, 'Total');
        $lastCol = $col;

        // Style header
        $headerRange = 'A' . $row . ':' . $lastCol . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Data
        foreach ($statistics as $index => $stat) {
            $row++;

            if ($isRecap) {
                $sheet->setCellValue('A' . $row, $stat['ranking']);
                $sheet->setCellValue('B' . $row, $stat['puskesmas_name']);
                $col = $startCol;
            } else {
                $col = $startCol;
            }

            $total = 0;
            $monthly = $diseaseType === 'ht' ? $stat['ht']['monthly_data'] : $stat['dm']['monthly_data'];

            // Loop untuk isi data bulanan
            for ($month = 1; $month <= 12; $month++) {
                $count = $monthly[$month]['total'] ?? 0;
                $total += $count;
                $sheet->setCellValue($col++ . $row, $count);
            }

            // Isi total
            $sheet->setCellValue($col . $row, $total);
        }

        // Styling untuk seluruh data
        $dataRange = 'A5:' . $lastCol . $row;
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Styling untuk kolom angka
        if ($isRecap) {
            $sheet->getStyle('A6:A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Styling untuk semua kolom data angka
        $dataColStart = ($isRecap) ? 'C' : 'A';
        $sheet->getStyle($dataColStart . '6:' . $lastCol . $row)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Auto-size kolom
        $firstCol = ($isRecap) ? 'A' : 'A';
        foreach (range($firstCol, $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * Get patient attendance data for monitoring report
     */
    protected function getPatientAttendanceData($puskesmasId, $year, $month, $diseaseType)
    {
        $result = [
            'ht' => [],
            'dm' => []
        ];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $daysInMonth = $endDate->day;

        // Ambil data pasien hipertensi jika diperlukan
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $htPatients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereJsonContains('ht_years', $year)
                ->orderBy('name')
                ->get();

            foreach ($htPatients as $patient) {
                // Ambil pemeriksaan HT untuk pasien di bulan ini
                $examinations = HtExamination::where('patient_id', $patient->id)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->get()
                    ->pluck('examination_date')
                    ->map(function ($date) {
                        return Carbon::parse($date)->day;
                    })
                    ->toArray();

                // Buat data kehadiran per hari
                $attendance = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $attendance[$day] = in_array($day, $examinations);
                }

                $result['ht'][] = [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'medical_record_number' => $patient->medical_record_number,
                    'gender' => $patient->gender,
                    'age' => $patient->age,
                    'attendance' => $attendance,
                    'visit_count' => count($examinations)
                ];
            }
        }

        // Ambil data pasien diabetes jika diperlukan
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
                ->whereJsonContains('dm_years', $year)
                ->orderBy('name')
                ->get();

            foreach ($dmPatients as $patient) {
                // Ambil pemeriksaan DM untuk pasien di bulan ini
                $examinations = DmExamination::where('patient_id', $patient->id)
                    ->whereBetween('examination_date', [$startDate, $endDate])
                    ->distinct('examination_date')
                    ->pluck('examination_date')
                    ->map(function ($date) {
                        return Carbon::parse($date)->day;
                    })
                    ->toArray();

                // Buat data kehadiran per hari
                $attendance = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $attendance[$day] = in_array($day, $examinations);
                }

                $result['dm'][] = [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'medical_record_number' => $patient->medical_record_number,
                    'gender' => $patient->gender,
                    'age' => $patient->age,
                    'attendance' => $attendance,
                    'visit_count' => count($examinations)
                ];
            }
        }

        return $result;
    }

    /**
     * Export monitoring report to PDF
     */
    protected function exportMonitoringToPdf($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $monthName = $this->getMonthName($month);

        // Set judul
        $title = "Laporan Pemantauan ";
        if ($diseaseType === 'ht') {
            $title .= "Pasien Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title .= "Pasien Diabetes Mellitus (DM)";
        } else {
            $title .= "Pasien Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }

        $data = [
            'title' => $title,
            'subtitle' => "Bulan $monthName Tahun $year",
            'puskesmas' => $puskesmas,
            'year' => $year,
            'month' => $month,
            'month_name' => $monthName,
            'days_in_month' => Carbon::createFromDate($year, $month, 1)->daysInMonth,
            'type' => $diseaseType,
            'patients' => $patientData,
            'generated_at' => Carbon::now()->format('d F Y H:i'),
            'generated_by' => Auth::user()->name,
            'user_role' => Auth::user()->is_admin ? 'Admin' : 'Petugas Puskesmas',
        ];

        // Generate PDF
        $pdf = PDF::loadView('exports.monitoring_pdf', $data);
        $pdf->setPaper('a4', 'landscape');

        // Simpan PDF ke storage dan return download response
        $pdfFilename = $filename . '.pdf';
        Storage::put('public/exports/' . $pdfFilename, $pdf->output());

        return response()->download(storage_path('app/public/exports/' . $pdfFilename), $pdfFilename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export monitoring report to Excel
     */
    protected function exportMonitoringToExcel($patientData, $puskesmas, $year, $month, $diseaseType, $filename)
    {
        $monthName = $this->getMonthName($month);
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Buat spreadsheet baru
        $spreadsheet = new Spreadsheet();

        // Jika perlu, buat sheet untuk setiap jenis penyakit
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $this->createMonitoringSheet($spreadsheet, $patientData['ht'], $puskesmas, $year, $month, 'ht', $daysInMonth);
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            if ($diseaseType === 'all') {
                // Jika all, buat sheet baru untuk DM
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle('Pemantauan DM');
                $spreadsheet->setActiveSheetIndex(1);
            }
            $this->createMonitoringSheet($spreadsheet, $patientData['dm'], $puskesmas, $year, $month, 'dm', $daysInMonth);
        }

        // Set active sheet to first sheet
        $spreadsheet->setActiveSheetIndex(0);

        // Simpan file
        $excelFilename = $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/exports/' . $excelFilename);
        $writer->save($path);

        return response()->download($path, $excelFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Create sheet for monitoring report
     */
    protected function createMonitoringSheet($spreadsheet, $patients, $puskesmas, $year, $month, $diseaseType, $daysInMonth)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $monthName = $this->getMonthName($month);

        if ($diseaseType === 'ht') {
            $sheet->setTitle('Pemantauan HT');
            $title = "Laporan Pemantauan Pasien Hipertensi (HT)";
        } else {
            $sheet->setTitle('Pemantauan DM');
            $title = "Laporan Pemantauan Pasien Diabetes Mellitus (DM)";
        }

        $title .= " - " . $puskesmas->name;
        $subtitle = "Bulan $monthName Tahun $year";

        // Judul
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . $this->getColLetter(5 + $daysInMonth) . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Subtitle
        $sheet->setCellValue('A2', $subtitle);
        $sheet->mergeCells('A2:' . $this->getColLetter(5 + $daysInMonth) . '2');
        $sheet->getStyle('A2')->getFont()->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Info ekspor
        $exportInfo = "Diekspor oleh: " . Auth::user()->name . " (" .
            (Auth::user()->is_admin ? "Admin" : "Petugas Puskesmas") . ")";
        $sheet->setCellValue('A3', $exportInfo);
        $sheet->mergeCells('A3:' . $this->getColLetter(5 + $daysInMonth) . '3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Tanggal generate
        $sheet->setCellValue('A4', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A4:' . $this->getColLetter(5 + $daysInMonth) . '4');
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header baris 1
        $row = 6;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'No. RM');
        $sheet->setCellValue('C' . $row, 'Nama Pasien');
        $sheet->setCellValue('D' . $row, 'JK');
        $sheet->setCellValue('E' . $row, 'Umur');

        // Merge untuk header tanggal
        $sheet->setCellValue('F' . $row, 'Kedatangan (Tanggal)');
        $sheet->mergeCells('F' . $row . ':' . $this->getColLetter(5 + $daysInMonth) . $row);

        // Jumlah Kunjungan
        $sheet->setCellValue($this->getColLetter(6 + $daysInMonth) . $row, 'Jumlah');

        // Header baris 2 (tanggal)
        $row++;
        $sheet->setCellValue('A' . $row, '');
        $sheet->setCellValue('B' . $row, '');
        $sheet->setCellValue('C' . $row, '');
        $sheet->setCellValue('D' . $row, '');
        $sheet->setCellValue('E' . $row, '');

        // Isi header tanggal
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(5 + $day);
            $sheet->setCellValue($col . $row, $day);
        }

        $sheet->setCellValue($this->getColLetter(6 + $daysInMonth) . $row, 'Kunjungan');

        // Style header
        $headerRange1 = 'A6:' . $this->getColLetter(6 + $daysInMonth) . '6';
        $headerRange2 = 'A7:' . $this->getColLetter(6 + $daysInMonth) . '7';

        $sheet->getStyle($headerRange1)->getFont()->setBold(true);
        $sheet->getStyle($headerRange2)->getFont()->setBold(true);

        $sheet->getStyle($headerRange1)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange2)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');

        $sheet->getStyle($headerRange1)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange2)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $sheet->getStyle($headerRange1)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headerRange2)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Data pasien
        $row = 7;
        foreach ($patients as $index => $patient) {
            $row++;

            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $patient['medical_record_number']);
            $sheet->setCellValue('C' . $row, $patient['patient_name']);
            $sheet->setCellValue('D' . $row, $patient['gender'] === 'male' ? 'L' : 'P');
            $sheet->setCellValue('E' . $row, $patient['age']);

            // Isi checklist kedatangan
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $col = $this->getColLetter(5 + $day);
                if (isset($patient['attendance'][$day]) && $patient['attendance'][$day]) {
                    $sheet->setCellValue($col . $row, '✓');
                } else {
                    $sheet->setCellValue($col . $row, '');
                }
            }

            // Jumlah kunjungan
            $sheet->setCellValue($this->getColLetter(6 + $daysInMonth) . $row, $patient['visit_count']);
        }

        // Style untuk data
        $dataRange = 'A8:' . $this->getColLetter(6 + $daysInMonth) . $row;
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Alignment untuk kolom tertentu
        $sheet->getStyle('A8:A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D8:D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E8:E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Alignment untuk checklist kedatangan
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(5 + $day);
            $sheet->getStyle($col . '8:' . $col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // Alignment untuk jumlah kunjungan
        $sheet->getStyle($this->getColLetter(6 + $daysInMonth) . '8:' . $this->getColLetter(6 + $daysInMonth) . $row)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Auto-size kolom
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setWidth(30); // Nama pasien biasanya lebih panjang
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);

        // Width untuk kolom tanggal (lebih kecil)
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColLetter(5 + $day);
            $sheet->getColumnDimension($col)->setWidth(3.5);
        }

        $sheet->getColumnDimension($this->getColLetter(6 + $daysInMonth))->setAutoSize(true);

        // Freeze panes untuk memudahkan navigasi
        $sheet->freezePane('F8');
    }

    /**
     * Helper to get Excel column letter from number
     */
    protected function getColLetter($number)
    {
        $letter = '';
        while ($number > 0) {
            $temp = ($number - 1) % 26;
            $letter = chr($temp + 65) . $letter;
            $number = (int)(($number - $temp - 1) / 26);
        }
        return $letter;
    }

    /**
     * Mendapatkan nama bulan dalam bahasa Indonesia
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

    /**
     * Mendapatkan statistik HT (Hipertensi) secara lengkap
     */
    private function getHtStatistics($puskesmasId, $year, $month = null)
    {
        if ($month) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        } else {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        }

        // Get patients with HT in this puskesmas
        $htPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('ht_years', $year)
            ->get();

        $htPatientIds = $htPatients->pluck('id')->toArray();

        // Get examinations for these patients in the specified period
        $examinations = HtExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $htPatientIds)
            ->whereBetween('examination_date', [$startDate, $endDate])
            ->get();

        // Group examinations by patient
        $examinationsByPatient = $examinations->groupBy('patient_id');
        
        // Count unique patients served in the period
        $totalPatients = $examinationsByPatient->count();

        // Count patients by gender
        $malePatients = 0;
        $femalePatients = 0;
        
        foreach ($examinationsByPatient as $patientId => $patientExams) {
            $patient = $htPatients->firstWhere('id', $patientId);
            if ($patient) {
                if ($patient->gender === 'male') {
                    $malePatients++;
                } else if ($patient->gender === 'female') {
                    $femalePatients++;
                }
            }
        }

        // Calculate controlled and standard patients
        $standardPatients = 0;
        $controlledPatients = 0;

        foreach ($examinationsByPatient as $patientId => $patientExaminations) {
            // For standard patients, check if they have visits every month of the year
            if (!$month) {
                $months = $patientExaminations->pluck('month')->unique()->toArray();
                if (count($months) === 12) {
                    $standardPatients++;
                }
            } else {
                // For monthly report, all patients with examinations are considered standard
                $standardPatients++;
            }

            // For controlled patients - blood pressure 90-139/60-89 in at least 3 visits
            $controlledVisits = $patientExaminations->filter(function ($exam) {
                return $exam->systolic >= 90 && $exam->systolic <= 139 &&
                       $exam->diastolic >= 60 && $exam->diastolic <= 89;
            })->count();

            if ($controlledVisits >= 3) {
                $controlledPatients++;
            }
        }

        $nonStandardPatients = $totalPatients - $standardPatients;

        // Monthly breakdown
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($month && $m !== $month) continue;

            $monthExaminations = $examinations->filter(function ($exam) use ($m) {
                return $exam->month === $m;
            });

            $monthlyPatients = $monthExaminations->pluck('patient_id')->unique();
            
            $monthlyMale = 0;
            $monthlyFemale = 0;
            
            foreach ($monthlyPatients as $patientId) {
                $patient = $htPatients->firstWhere('id', $patientId);
                if ($patient) {
                    if ($patient->gender === 'male') {
                        $monthlyMale++;
                    } else if ($patient->gender === 'female') {
                        $monthlyFemale++;
                    }
                }
            }

            $monthlyData[$m] = [
                'male' => $monthlyMale,
                'female' => $monthlyFemale,
                'total' => $monthlyPatients->count(),
                'standard' => $monthlyPatients->count(), // All patients served in a month are considered standard
                'non_standard' => 0
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'controlled_patients' => $controlledPatients,
            'monthly_data' => $monthlyData
        ];
    }

    /**
     * Mendapatkan statistik DM (Diabetes) secara lengkap
     */
    private function getDmStatistics($puskesmasId, $year, $month = null)
    {
        if ($month) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        } else {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();
        }

        // Get patients with DM in this puskesmas
        $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->whereJsonContains('dm_years', $year)
            ->get();

        $dmPatientIds = $dmPatients->pluck('id')->toArray();

        // Get examinations for these patients in the specified period
        $examinations = DmExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $dmPatientIds)
            ->whereBetween('examination_date', [$startDate, $endDate])
            ->get();

        // Group examinations by patient and date
        $examinationsByPatient = $examinations->groupBy(['patient_id', function($exam) {
            return Carbon::parse($exam->examination_date)->format('Y-m-d');
        }]);
        
        // Count unique patients served in the period
        $totalPatients = $examinationsByPatient->count();

        // Count patients by gender
        $malePatients = 0;
        $femalePatients = 0;
        
        foreach ($examinationsByPatient as $patientId => $patientExams) {
            $patient = $dmPatients->firstWhere('id', $patientId);
            if ($patient) {
                if ($patient->gender === 'male') {
                    $malePatients++;
                } else if ($patient->gender === 'female') {
                    $femalePatients++;
                }
            }
        }

        // Calculate controlled and standard patients
        $standardPatients = 0;
        $controlledPatients = 0;

        foreach ($examinationsByPatient as $patientId => $patientDates) {
            $patientExams = $examinations->where('patient_id', $patientId);
            
            // For standard patients, check if they have visits every month of the year
            if (!$month) {
                $months = $patientExams->pluck('month')->unique()->toArray();
                if (count($months) === 12) {
                    $standardPatients++;
                }
            } else {
                // For monthly report, all patients with examinations are considered standard
                $standardPatients++;
            }

            // For controlled patients - check DM control criteria
            $controlledHbA1c = $patientExams->contains(function ($exam) {
                return $exam->examination_type === 'hba1c' && $exam->result < 7;
            });

            $controlledGdp = $patientExams->filter(function ($exam) {
                return $exam->examination_type === 'gdp' && $exam->result < 126;
            })->count() >= 3;

            $controlledGd2jpp = $patientExams->filter(function ($exam) {
                return $exam->examination_type === 'gd2jpp' && $exam->result < 200;
            })->count() >= 3;

            if ($controlledHbA1c || $controlledGdp || $controlledGd2jpp) {
                $controlledPatients++;
            }
        }

        $nonStandardPatients = $totalPatients - $standardPatients;

        // Monthly breakdown
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($month && $m !== $month) continue;

            $monthExaminations = $examinations->filter(function ($exam) use ($m) {
                return $exam->month === $m;
            });

            $monthlyPatients = $monthExaminations->pluck('patient_id')->unique();
            
            $monthlyMale = 0;
            $monthlyFemale = 0;
            
            foreach ($monthlyPatients as $patientId) {
                $patient = $dmPatients->firstWhere('id', $patientId);
                if ($patient) {
                    if ($patient->gender === 'male') {
                        $monthlyMale++;
                    } else if ($patient->gender === 'female') {
                        $monthlyFemale++;
                    }
                }
            }

            $monthlyData[$m] = [
                'male' => $monthlyMale,
                'female' => $monthlyFemale,
                'total' => $monthlyPatients->count(),
                'standard' => $monthlyPatients->count(), // All patients served in a month are considered standard
                'non_standard' => 0
            ];
        }

        return [
            'total_patients' => $totalPatients,
            'male_patients' => $malePatients,
            'female_patients' => $femalePatients,
            'standard_patients' => $standardPatients,
            'non_standard_patients' => $nonStandardPatients,
            'controlled_patients' => $controlledPatients,
            'monthly_data' => $monthlyData
        ];
    }
}
