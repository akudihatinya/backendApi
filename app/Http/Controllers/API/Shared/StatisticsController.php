<?php

namespace App\Http\Controllers\API\Shared;

use App\Http\Controllers\Controller;
use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
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
use Illuminate\Support\Facades\Log;  // Add this import for Log facade
class StatisticsController extends Controller
{
        /**
     * Display a listing of statistics.
     * Now accessible to all authenticated users with data filtering
     * 
     * @param Request $request
     * @return JsonResponse
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

                $htData = $this->getHtStatistics($puskesmas->id, $year, $month);

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
                    'controlled_patients' => $htData['controlled_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Ambil data DM jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->getDmStatistics($puskesmas->id, $year, $month);

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
                    'controlled_patients' => $dmData['controlled_patients'],
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
     * Get HT statistics for specific puskesmas
     */
    public function htStatistics(Request $request)
    {
        $request->merge(['type' => 'ht']);
        return $this->index($request);
    }

    /**
     * Get DM statistics for specific puskesmas
     */
    public function dmStatistics(Request $request)
    {
        $request->merge(['type' => 'dm']);
        return $this->index($request);
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

                $htData = $this->getHtStatistics($puskesmas->id, $year, $month);

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
                    'controlled_patients' => $htData['controlled_patients'],
                    'monthly_data' => $htData['monthly_data'],
                ];
            }

            // Ambil data DM jika diperlukan
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $dmTarget = YearlyTarget::where('puskesmas_id', $puskesmas->id)
                    ->where('disease_type', 'dm')
                    ->where('year', $year)
                    ->first();

                $dmData = $this->getDmStatistics($puskesmas->id, $year, $month);

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
                    'controlled_patients' => $dmData['controlled_patients'],
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
            $sheet->setCellValue($col++ . $row, 'Pasien Terkontrol HT');
        }

        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $sheet->setCellValue($col++ . $row, 'Target DM');
            $sheet->setCellValue($col++ . $row, 'Total Pasien DM');
            $sheet->setCellValue($col++ . $row, 'Pencapaian DM (%)');
            $sheet->setCellValue($col++ . $row, 'Pasien Standar DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Terkontrol DM');
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
        $sheet->setTitle('Data Bulanan');

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
                ->where('has_ht', true)
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
                    'age' => Carbon::parse($patient->birth_date)->age,
                    'attendance' => $attendance,
                    'visit_count' => count($examinations)
                ];
            }
        }

        // Ambil data pasien diabetes jika diperlukan
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
                ->where('has_dm', true)
                ->orderBy('name')
                ->get();

            foreach ($dmPatients as $patient) {
                // Ambil pemeriksaan DM untuk pasien di bulan ini
                $examinations = DmExamination::where('patient_id', $patient->id)
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

                $result['dm'][] = [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'medical_record_number' => $patient->medical_record_number,
                    'gender' => $patient->gender,
                    'age' => Carbon::parse($patient->birth_date)->age,
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
     * Get dashboard statistics for the current year
     * 
     * @param Request $request
     * @return JsonResponse
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
        
        // Check if user is puskesmas but has no puskesmas_id
        if ($user->isPuskesmas() && !$user->puskesmas_id) {
            // Try to find a matching puskesmas by name
            $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . $user->name . '%')->first();
            
            if ($puskesmasWithSameName) {
                // Update the user with the correct puskesmas_id
                $user->update(['puskesmas_id' => $puskesmasWithSameName->id]);
                Log::info('Auto-fixed: Updated user ' . $user->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
            } else {
                return response()->json([
                    'message' => 'Akun puskesmas Anda tidak terkait dengan data puskesmas manapun. Hubungi administrator.',
                ], 400);
            }
        }
        
        // Buat request untuk mengambil data statistik
        $statsRequest = new Request([
            'year' => $year,
            'type' => $type,
            'per_page' => $user->isAdmin() ? ($request->per_page ?? 10) : 1
        ]);
        
        // Dapatkan data statistik
        $response = $this->index($statsRequest)->getData();
        
        // Untuk admin, kembalikan seluruh daftar puskesmas
        if ($user->isAdmin()) {
            return response()->json([
                'year' => $year,
                'type' => $type,
                'puskesmas_data' => $response->data ?? [],
                'pagination' => $response->meta ?? null
            ]);
        }
        
        // Untuk puskesmas, kembalikan data puskesmas tersebut saja
        $puskesmasData = $response->data[0] ?? null;
        
        if (!$puskesmasData) {
            // Check if there's examination data for any year
            $availableYears = $this->getAvailableYearsForPuskesmas($user->puskesmas_id);
            
            if (!empty($availableYears)) {
                $yearsStr = implode(', ', $availableYears);
                return response()->json([
                    'message' => "Data statistik tidak ditemukan untuk tahun $year. Tersedia data untuk tahun: $yearsStr",
                    'available_years' => $availableYears
                ], 404);
            }
            
            // Check if there are any patients for this puskesmas
            $patientCount = Patient::where('puskesmas_id', $user->puskesmas_id)->count();
            
            if ($patientCount > 0) {
                return response()->json([
                    'message' => "Data statistik tidak ditemukan untuk tahun $year. Anda memiliki $patientCount pasien terdaftar tetapi belum ada pemeriksaan yang tercatat.",
                ], 404);
            }
            
            return response()->json([
                'message' => "Data statistik tidak ditemukan untuk tahun $year. Pastikan Anda telah memasukkan data pasien dan pemeriksaan.",
            ], 404);
        }
        
        // Buat response yang berbeda sesuai parameter type
        $result = [
            'puskesmas' => $puskesmasData->puskesmas_name,
            'year' => $year,
            'type' => $type
        ];
        
        if ($type === 'all' || $type === 'ht') {
            $result['ht'] = $puskesmasData->ht ?? null;
        }
        
        if ($type === 'all' || $type === 'dm') {
            $result['dm'] = $puskesmasData->dm ?? null;
        }
        
        return response()->json($result);
    }

    /**
     * Mendapatkan statistik HT berdasarkan tahun dan bulan (opsional)
     * 
     * @param int $puskesmasId
     * @param int $year
     * @param int|null $month
     * @return array
     */
    protected function getHtStatistics($puskesmasId, $year, $month = null)
    {
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();

        // Jika filter bulan digunakan
        if ($month !== null) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        }

        // Log query parameters for debugging
        Log::debug("getHtStatistics params: puskesmas=$puskesmasId, year=$year, month=$month, dateRange=$startDate to $endDate");

        // Get all patients with HT in this puskesmas
        $htPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->where('has_ht', true)
            ->get();

        $htPatientIds = $htPatients->pluck('id')->toArray();

        // Get examinations for these patients in the specified period
        $examinations = HtExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $htPatientIds)
            ->where(function($query) use ($startDate, $endDate, $year) {
                $query->whereBetween('examination_date', [$startDate, $endDate])
                      ->orWhere('year', $year);
            })
            ->get();

        // Log query results for debugging
        Log::debug("getHtStatistics results: patients=" . count($htPatientIds) . ", examinations=" . $examinations->count());

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
        foreach ($examinationsByMonth as $monthNum => $monthExaminations) {
            $patientIdsByMonth = $monthExaminations->pluck('patient_id')->unique();

            $maleCount = 0;
            $femaleCount = 0;

            foreach ($patientIdsByMonth as $patientId) {
                $patient = $htPatients->firstWhere('id', $patientId);
                if ($patient) {
                    if ($patient->gender === 'male') {
                        $maleCount++;
                    } elseif ($patient->gender === 'female') {
                        $femaleCount++;
                    }
                }
            }

            $monthlyData[$monthNum] = [
                'male' => $maleCount,
                'female' => $femaleCount,
                'total' => $patientIdsByMonth->count(),
            ];
        }

        // Calculate standard and controlled patients
        $standardPatients = 0;
        $controlledPatients = 0;

        foreach ($examinationsByPatient as $patientId => $patientExams) {
            // Get the latest examination for each patient
            $latestExam = $patientExams->sortByDesc('examination_date')->first();

            if ($latestExam) {
                // A patient is considered "standard" if they have the required examinations
                if (isset($latestExam->has_lab_test) && $latestExam->has_lab_test) {
                    $standardPatients++;
                }

                // A patient is considered "controlled" if their most recent examination shows controlled blood pressure
                if ($latestExam->systolic <= 140 && $latestExam->diastolic <= 90) {
                    $controlledPatients++;
                }
            }
        }

        // Return the formatted statistics
        return [
            'total_patients' => $examinationsByPatient->count(),
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
            'monthly_data' => $monthlyData,
        ];
    }

    /**
     * Mendapatkan statistik DM berdasarkan tahun dan bulan (opsional)
     * 
     * @param int $puskesmasId
     * @param int $year
     * @param int|null $month
     * @return array
     */
    protected function getDmStatistics($puskesmasId, $year, $month = null)
    {
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear();

        // Jika filter bulan digunakan
        if ($month !== null) {
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        }

        // Log query parameters for debugging
        Log::debug("getDmStatistics params: puskesmas=$puskesmasId, year=$year, month=$month, dateRange=$startDate to $endDate");

        // Get all patients with DM in this puskesmas
        $dmPatients = Patient::where('puskesmas_id', $puskesmasId)
            ->where('has_dm', true)
            ->get();

        $dmPatientIds = $dmPatients->pluck('id')->toArray();

        // Get examinations for these patients in the specified period
        $examinations = DmExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $dmPatientIds)
            ->where(function($query) use ($startDate, $endDate, $year) {
                $query->whereBetween('examination_date', [$startDate, $endDate])
                      ->orWhere('year', $year);
            })
            ->get();

        // Log query results for debugging
        Log::debug("getDmStatistics results: patients=" . count($dmPatientIds) . ", examinations=" . $examinations->count());

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
        foreach ($examinationsByMonth as $monthNum => $monthExaminations) {
            $patientIdsByMonth = $monthExaminations->pluck('patient_id')->unique();

            $maleCount = 0;
            $femaleCount = 0;

            foreach ($patientIdsByMonth as $patientId) {
                $patient = $dmPatients->firstWhere('id', $patientId);
                if ($patient) {
                    if ($patient->gender === 'male') {
                        $maleCount++;
                    } elseif ($patient->gender === 'female') {
                        $femaleCount++;
                    }
                }
            }

            $monthlyData[$monthNum] = [
                'male' => $maleCount,
                'female' => $femaleCount,
                'total' => $patientIdsByMonth->count(),
            ];
        }

        // Calculate standard and controlled patients
        $standardPatients = 0;
        $controlledPatients = 0;

        foreach ($examinationsByPatient as $patientId => $patientExams) {
            // Get the latest examination for each patient
            $latestExam = $patientExams->sortByDesc('examination_date')->first();

            if ($latestExam) {
                // A patient is considered "standard" if they have the required examinations
                if (isset($latestExam->has_lab_test) && $latestExam->has_lab_test) {
                    $standardPatients++;
                }

                // A patient is considered "controlled" if their most recent examination shows controlled glucose level
                if (isset($latestExam->blood_sugar) && $latestExam->blood_sugar <= 200) {
                    $controlledPatients++;
                } else if (isset($latestExam->result) && $latestExam->result <= 200) {
                    // Alternative check for different field name
                    $controlledPatients++;
                }
            }
        }

        // Return the formatted statistics
        return [
            'total_patients' => $examinationsByPatient->count(),
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
            'monthly_data' => $monthlyData,
        ];
    }
/**
 * Helper method to get available years with data for a puskesmas
 * 
 * @param int $puskesmasId
 * @return array
 */
private function getAvailableYearsForPuskesmas($puskesmasId)
{
    $htYears = HtExamination::where('puskesmas_id', $puskesmasId)
        ->distinct('year')
        ->pluck('year')
        ->toArray();
        
    $dmYears = DmExamination::where('puskesmas_id', $puskesmasId)
        ->distinct('year')
        ->pluck('year')
        ->toArray();
        
    $years = array_unique(array_merge($htYears, $dmYears));
    sort($years);
    
    return $years;
}
}
