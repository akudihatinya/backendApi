<?php

namespace App\Services;

use App\Models\DmExamination;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Models\Puskesmas;
use App\Models\YearlyTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportService
{
    /**
     * Generate PDF report for HT or DM statistics
     */
    public function generatePdfReport($diseaseType, $year, $puskesmasId = null)
    {
        $data = $this->getReportData($diseaseType, $year, $puskesmasId);
        
        $pdf = PDF::loadView('exports.report_pdf', [
            'data' => $data,
            'disease_type' => $diseaseType,
            'year' => $year,
        ]);
        
        return $pdf->download("laporan_{$diseaseType}_{$year}.pdf");
    }
    
    /**
     * Generate Excel report for HT or DM statistics
     */
    public function generateExcelReport($diseaseType, $year, $puskesmasId = null)
    {
        $data = $this->getReportData($diseaseType, $year, $puskesmasId);
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $sheet->setCellValue('A1', 'Laporan ' . ($diseaseType === 'ht' ? 'Hipertensi' : 'Diabetes Mellitus'));
        $sheet->setCellValue('A2', 'Tahun ' . $year);
        
        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            $sheet->setCellValue('A3', $puskesmas->name);
        } else {
            $sheet->setCellValue('A3', 'Seluruh Puskesmas');
        }
        
        // Set column headers
        $sheet->setCellValue('A5', 'Bulan');
        $sheet->setCellValue('B5', 'Laki-laki');
        $sheet->setCellValue('C5', 'Perempuan');
        $sheet->setCellValue('D5', 'Total');
        $sheet->setCellValue('E5', 'Pasien Standar');
        $sheet->setCellValue('F5', 'Pasien Terkendali');
        
        // Fill data
        $row = 6;
        foreach ($data['monthly_data'] as $index => $month) {
            $sheet->setCellValue('A' . $row, $month['month_name']);
            $sheet->setCellValue('B' . $row, $month['male']);
            $sheet->setCellValue('C' . $row, $month['female']);
            $sheet->setCellValue('D' . $row, $month['total']);
            $sheet->setCellValue('E' . $row, $month['standard_patients']);
            $sheet->setCellValue('F' . $row, $month['controlled_patients']);
            $row++;
        }
        
        // Add summary
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Total Pasien');
        $sheet->setCellValue('D' . $row, $data['summary']['total_patients']);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Pasien Standar');
        $sheet->setCellValue('D' . $row, $data['summary']['standard_patients']);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Pasien Terkendali');
        $sheet->setCellValue('D' . $row, $data['summary']['controlled_patients']);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Sasaran');
        $sheet->setCellValue('D' . $row, $data['summary']['target']);
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Persentase Capaian');
        $sheet->setCellValue('D' . $row, $data['summary']['achievement_percentage'] . '%');
        
        // Format the document
        foreach(range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Create writer and save file
        $writer = new Xlsx($spreadsheet);
        $filename = "laporan_{$diseaseType}_{$year}.xlsx";
        $tempPath = storage_path('app/temp/' . $filename);
        
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        $writer->save($tempPath);
        
        return $tempPath;
    }
    
    /**
     * Get report data for export
     */
    private function getReportData($diseaseType, $year, $puskesmasId = null)
    {
        $data = [
            'monthly_data' => [],
            'summary' => [],
        ];
        
        // Process monthly data
        for ($month = 1; $month <= 12; $month++) {
            $monthData = [
                'month' => $month,
                'month_name' => Carbon::createFromDate($year, $month, 1)->locale('id')->monthName,
                'male' => 0,
                'female' => 0,
                'total' => 0,
                'standard_patients' => 0,
                'controlled_patients' => 0,
            ];
            
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            
            if ($diseaseType === 'ht') {
                $query = HtExamination::whereBetween('examination_date', [$startDate, $endDate]);
                
                if ($puskesmasId) {
                    $query->where('puskesmas_id', $puskesmasId);
                }
                
                $examinations = $query->get();
                
                // Count unique patients by gender
                $patientIds = $examinations->pluck('patient_id')->unique();
                $patients = Patient::whereIn('id', $patientIds)->get();
                
                $monthData['male'] = $patients->where('gender', 'male')->count();
                $monthData['female'] = $patients->where('gender', 'female')->count();
                $monthData['total'] = $patientIds->count();
                
                // Standard and controlled calculations would go here
                // These are simplified for this example
                $monthData['standard_patients'] = 0;
                $monthData['controlled_patients'] = 0;
            } else { // DM
                $query = DmExamination::whereBetween('examination_date', [$startDate, $endDate]);
                
                if ($puskesmasId) {
                    $query->where('puskesmas_id', $puskesmasId);
                }
                
                $examinations = $query->get();
                
                // Count unique patients by gender
                $patientIds = $examinations->pluck('patient_id')->unique();
                $patients = Patient::whereIn('id', $patientIds)->get();
                
                $monthData['male'] = $patients->where('gender', 'male')->count();
                $monthData['female'] = $patients->where('gender', 'female')->count();
                $monthData['total'] = $patientIds->count();
                
                // Standard and controlled calculations would go here
                // These are simplified for this example
                $monthData['standard_patients'] = 0;
                $monthData['controlled_patients'] = 0;
            }
            
            $data['monthly_data'][] = $monthData;
        }
        
        // Summary data
        $targetQuery = YearlyTarget::where('disease_type', $diseaseType)
            ->where('year', $year);
            
        if ($puskesmasId) {
            $targetQuery->where('puskesmas_id', $puskesmasId);
            
            $target = $targetQuery->first();
            $targetValue = $target ? $target->target_count : 0;
            
            $patientsQuery = Patient::where('puskesmas_id', $puskesmasId);
            if ($diseaseType === 'ht') {
                $patientsQuery->where('has_ht', true);
            } else {
                $patientsQuery->where('has_dm', true);
            }
            
            $patientsCount = $patientsQuery->count();
        } else {
            $targetValue = $targetQuery->sum('target_count');
            
            if ($diseaseType === 'ht') {
                $patientsCount = Patient::where('has_ht', true)->count();
            } else {
                $patientsCount = Patient::where('has_dm', true)->count();
            }
        }
        
        $achievementPercentage = $targetValue > 0 ? round(($patientsCount / $targetValue) * 100, 2) : 0;
        
        // Standard and controlled calculations would be more complex in a real implementation
        $standardPatients = 0;
        $controlledPatients = 0;
        
        $data['summary'] = [
            'total_patients' => $patientsCount,
            'standard_patients' => $standardPatients,
            'controlled_patients' => $controlledPatients,
            'target' => $targetValue,
            'achievement_percentage' => $achievementPercentage,
        ];
        
        return $data;
    }
}
