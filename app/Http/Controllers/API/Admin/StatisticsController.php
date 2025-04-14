<?php

namespace App\Http\Controllers\API\Admin;

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

class StatisticsController extends Controller
{
    // ... Metode index dan metode lainnya tetap sama ...
    
    /**
     * Export statistik ke format PDF atau Excel
     */
    public function exportStatistics(Request $request)
    {
        $year = $request->year ?? Carbon::now()->year;
        $diseaseType = $request->type ?? 'all'; // Nilai default: 'all', bisa juga 'ht' atau 'dm'
        
        // Validasi nilai disease_type
        if (!in_array($diseaseType, ['all', 'ht', 'dm'])) {
            return response()->json([
                'message' => 'Parameter type tidak valid. Gunakan all, ht, atau dm.',
            ], 400);
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
        
        // Jika ada filter nama puskesmas
        if ($request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }
        
        $puskesmasAll = $puskesmasQuery->get();
        
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
                
                $htData = $this->getHtStatistics($puskesmas->id, $year);
                
                $data['ht'] = [
                    'target' => $htTarget ? $htTarget->target_count : 0,
                    'total_patients' => $htData['total_patients'],
                    'achievement_percentage' => $htTarget && $htTarget->target_count > 0
                        ? round(($htData['total_patients'] / $htTarget->target_count) * 100, 2)
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
                
                $dmData = $this->getDmStatistics($puskesmas->id, $year);
                
                $data['dm'] = [
                    'target' => $dmTarget ? $dmTarget->target_count : 0,
                    'total_patients' => $dmData['total_patients'],
                    'achievement_percentage' => $dmTarget && $dmTarget->target_count > 0
                        ? round(($dmData['total_patients'] / $dmTarget->target_count) * 100, 2)
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
        $filename = "statistik_";
        if ($diseaseType !== 'all') {
            $filename .= $diseaseType . "_";
        }
        $filename .= $year;
        
        // Proses export sesuai format
        if ($format === 'pdf') {
            return $this->exportToPdf($statistics, $year, $diseaseType, $filename);
        } else {
            return $this->exportToExcel($statistics, $year, $diseaseType, $filename);
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
     * Export statistik ke format PDF menggunakan Dompdf
     */
    private function exportToPdf($statistics, $year, $diseaseType, $filename)
    {
        $title = "";
        $subtitle = "Laporan Tahun " . $year;
        
        if ($diseaseType === 'ht') {
            $title = "Statistik Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title = "Statistik Diabetes Mellitus (DM)";
        } else {
            $title = "Statistik Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }
        
        $data = [
            'title' => $title,
            'subtitle' => $subtitle,
            'year' => $year,
            'type' => $diseaseType,
            'statistics' => $statistics,
            'generated_at' => Carbon::now()->format('d F Y H:i'),
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
     * Export statistik ke format Excel menggunakan PhpSpreadsheet
     */
    private function exportToExcel($statistics, $year, $diseaseType, $filename)
    {
        // Buat spreadsheet baru
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set judul
        if ($diseaseType === 'ht') {
            $title = "Statistik Hipertensi (HT) - Tahun " . $year;
        } elseif ($diseaseType === 'dm') {
            $title = "Statistik Diabetes Mellitus (DM) - Tahun " . $year;
        } else {
            $title = "Statistik Hipertensi (HT) dan Diabetes Mellitus (DM) - Tahun " . $year;
        }
        
        // Judul spreadsheet
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Tanggal generate
        $sheet->setCellValue('A2', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Spasi
        $sheet->setCellValue('A3', '');
        
        // Header kolom
        $row = 4;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'Puskesmas');
        
        $col = 'C';
        
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
        foreach ($statistics as $stat) {
            $row++;
            $sheet->setCellValue('A' . $row, $stat['ranking']);
            $sheet->setCellValue('B' . $row, $stat['puskesmas_name']);
            
            $col = 'C';
            
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $sheet->setCellValue($col++ . $row, $stat['ht']['target']);
                $sheet->setCellValue($col++ . $row, $stat['ht']['total_patients']);
                $sheet->setCellValue($col++ . $row, $stat['ht']['achievement_percentage'] . '%');
                $sheet->setCellValue($col++ . $row, $stat['ht']['standard_patients']);
                $sheet->setCellValue($col++ . $row, $stat['ht']['controlled_patients']);
            }
            
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $sheet->setCellValue($col++ . $row, $stat['dm']['target']);
                $sheet->setCellValue($col++ . $row, $stat['dm']['total_patients']);
                $sheet->setCellValue($col++ . $row, $stat['dm']['achievement_percentage'] . '%');
                $sheet->setCellValue($col++ . $row, $stat['dm']['standard_patients']);
                $sheet->setCellValue($col++ . $row, $stat['dm']['controlled_patients']);
            }
        }
        
        // Styling untuk seluruh data
        $dataRange = 'A5:' . $lastCol . $row;
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Styling untuk kolom nomor dan puskesmas
        $sheet->getStyle('A5:A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Auto-size kolom
        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Jika laporan terpisah, buat sheet tambahan untuk data bulanan
        if ($diseaseType !== 'all') {
            $this->addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year);
        }
        
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
     * Tambahkan sheet data bulanan ke spreadsheet
     */
    private function addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year)
    {
        // Buat sheet baru
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Data Bulanan');
        
        // Set judul
        $title = $diseaseType === 'ht' 
            ? "Data Bulanan Hipertensi (HT) - Tahun " . $year
            : "Data Bulanan Diabetes Mellitus (DM) - Tahun " . $year;
        
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:O1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Tanggal generate
        $sheet->setCellValue('A2', 'Generated: ' . Carbon::now()->format('d F Y H:i'));
        $sheet->mergeCells('A2:O2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Spasi
        $sheet->setCellValue('A3', '');
        
        // Header utama
        $row = 4;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'Puskesmas');
        $sheet->setCellValue('C' . $row, 'Jan');
        $sheet->setCellValue('D' . $row, 'Feb');
        $sheet->setCellValue('E' . $row, 'Mar');
        $sheet->setCellValue('F' . $row, 'Apr');
        $sheet->setCellValue('G' . $row, 'Mei');
        $sheet->setCellValue('H' . $row, 'Jun');
        $sheet->setCellValue('I' . $row, 'Jul');
        $sheet->setCellValue('J' . $row, 'Ags');
        $sheet->setCellValue('K' . $row, 'Sep');
        $sheet->setCellValue('L' . $row, 'Okt');
        $sheet->setCellValue('M' . $row, 'Nov');
        $sheet->setCellValue('N' . $row, 'Des');
        $sheet->setCellValue('O' . $row, 'Total');
        
        // Style header
        $headerRange = 'A' . $row . ':O' . $row;
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
            $sheet->setCellValue('A' . $row, $stat['ranking']);
            $sheet->setCellValue('B' . $row, $stat['puskesmas_name']);
            
            $total = 0;
            $monthly = $diseaseType === 'ht' ? $stat['ht']['monthly_data'] : $stat['dm']['monthly_data'];
            
            for ($month = 1; $month <= 12; $month++) {
                $col = chr(ord('C') + $month - 1); // C untuk Jan, D untuk Feb, dst.
                $count = $monthly[$month]['total'] ?? 0;
                $total += $count;
                $sheet->setCellValue($col . $row, $count);
            }
            
            $sheet->setCellValue('O' . $row, $total);
        }
        
        // Styling untuk seluruh data
        $dataRange = 'A4:O' . $row;
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Styling untuk kolom angka
        $sheet->getStyle('A5:A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C5:O' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Auto-size kolom
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}