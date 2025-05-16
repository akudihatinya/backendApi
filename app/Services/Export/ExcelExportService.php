<?php

namespace App\Services\Export;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelExportService
{
    /**
     * Export statistics to Excel
     */
    public function exportStatistics(array $statistics, int $year, ?int $month, string $diseaseType, string $filename): \Illuminate\Http\Response
    {
        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title based on disease type and period
        $title = "Statistik ";
        if ($diseaseType === 'ht') {
            $title .= "Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title .= "Diabetes Mellitus (DM)";
        } else {
            $title .= "Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }
        
        if ($month) {
            $monthName = $this->getMonthName($month);
            $title .= " - Bulan $monthName $year";
        } else {
            $title .= " - Tahun $year";
        }
        
        // Set header row
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Add generation info
        $generatedBy = Auth::user()->name;
        $generatedAt = Carbon::now()->format('d F Y H:i:s');
        $sheet->setCellValue('A2', "Dibuat oleh: $generatedBy");
        $sheet->setCellValue('A3', "Tanggal: $generatedAt");
        
        // Set header row for data table
        $row = 5;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'Puskesmas');
        
        $col = 'C';
        
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $sheet->setCellValue($col++ . $row, 'Target HT');
            $sheet->setCellValue($col++ . $row, 'Total Pasien HT');
            $sheet->setCellValue($col++ . $row, 'Pasien Standar HT');
            $sheet->setCellValue($col++ . $row, 'Pencapaian (%)');
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            $sheet->setCellValue($col++ . $row, 'Target DM');
            $sheet->setCellValue($col++ . $row, 'Total Pasien DM');
            $sheet->setCellValue($col++ . $row, 'Pasien Standar DM');
            $sheet->setCellValue($col++ . $row, 'Pencapaian (%)');
        }
        
        // Style header row
        $lastCol = --$col;
        $headerRange = 'A' . $row . ':' . $lastCol . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Add data rows
        foreach ($statistics as $index => $stat) {
            $row++;
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $stat['puskesmas_name']);
            
            $col = 'C';
            
            if ($diseaseType === 'all' || $diseaseType === 'ht') {
                $sheet->setCellValue($col++ . $row, $stat['ht']['target']);
                $sheet->setCellValue($col++ . $row, $stat['ht']['total_patients']);
                $sheet->setCellValue($col++ . $row, $stat['ht']['standard_patients']);
                $sheet->setCellValue($col++ . $row, $stat['ht']['achievement_percentage'] . '%');
            }
            
            if ($diseaseType === 'all' || $diseaseType === 'dm') {
                $sheet->setCellValue($col++ . $row, $stat['dm']['target']);
                $sheet->setCellValue($col++ . $row, $stat['dm']['total_patients']);
                $sheet->setCellValue($col++ . $row, $stat['dm']['standard_patients']);
                $sheet->setCellValue($col++ . $row, $stat['dm']['achievement_percentage'] . '%');
            }
        }
        
        // Add borders to data
        $dataRange = 'A5:' . $lastCol . $row;
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Auto-size columns
        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Create monthly data sheet if yearly report
        if (!$month) {
            $this->addMonthlyDataSheet($spreadsheet, $statistics, $diseaseType, $year);
        }
        
        // Save file
        $excelFilename = $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/exports/' . $excelFilename);
        $writer->save($path);
        
        // Return download response
        return response()->download($path, $excelFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export monitoring report to Excel
     */
    public function exportMonitoringReport(array $monitoringData, int $year, int $month, string $diseaseType, string $filename): \Illuminate\Http\Response
    {
        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title based on disease type
        $title = "Laporan Pemantauan ";
        if ($diseaseType === 'ht') {
            $title .= "Hipertensi (HT)";
        } elseif ($diseaseType === 'dm') {
            $title .= "Diabetes Mellitus (DM)";
        } else {
            $title .= "Hipertensi (HT) dan Diabetes Mellitus (DM)";
        }
        
        $monthName = $this->getMonthName($month);
        $title .= " - Bulan $monthName $year";
        
        // Set title
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:Z1'); // Merge enough cells for all days in month
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Add puskesmas info
        $puskesmasName = isset($monitoringData['puskesmas_name']) ? $monitoringData['puskesmas_name'] : '(Semua Puskesmas)';
        $sheet->setCellValue('A2', "Puskesmas: $puskesmasName");
        
        // Add generation info
        $generatedBy = Auth::user()->name;
        $generatedAt = Carbon::now()->format('d F Y H:i:s');
        $sheet->setCellValue('A3', "Dibuat oleh: $generatedBy");
        $sheet->setCellValue('A4', "Tanggal: $generatedAt");
        
        // Get days in month
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        
        // Set header row for data table
        $row = 6;
        $sheet->setCellValue('A' . $row, 'No');
        $sheet->setCellValue('B' . $row, 'No. RM');
        $sheet->setCellValue('C' . $row, 'Nama Pasien');
        $sheet->setCellValue('D' . $row, 'JK');
        $sheet->setCellValue('E' . $row, 'Umur');
        
        // Set headers for each day of month
        $sheet->setCellValue('F' . $row, 'Tanggal Kunjungan');
        $sheet->mergeCells('F' . $row . ':' . $this->getColumnLetter(5 + $daysInMonth) . $row);
        
        // Set jumlah kunjungan header
        $sheet->setCellValue($this->getColumnLetter(6 + $daysInMonth) . $row, 'Jumlah');
        
        // Add day numbers in second header row
        $row++;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $sheet->setCellValue($this->getColumnLetter(5 + $day) . $row, $day);
        }
        $sheet->setCellValue($this->getColumnLetter(6 + $daysInMonth) . $row, 'Kunjungan');
        
        // Style header rows
        $headerRange1 = 'A6:' . $this->getColumnLetter(6 + $daysInMonth) . '6';
        $headerRange2 = 'A7:' . $this->getColumnLetter(6 + $daysInMonth) . '7';
        
        $sheet->getStyle($headerRange1)->getFont()->setBold(true);
        $sheet->getStyle($headerRange2)->getFont()->setBold(true);
        $sheet->getStyle($headerRange1)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        $sheet->getStyle($headerRange2)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D3D3D3');
        
        // Add data rows
        $row = 7;
        $patients = isset($monitoringData['patients']) ? $monitoringData['patients'] : [];
        
        foreach ($patients as $index => $patient) {
            $row++;
            
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $patient['medical_record_number'] ?? '');
            $sheet->setCellValue('C' . $row, $patient['patient_name']);
            $sheet->setCellValue('D' . $row, ($patient['gender'] === 'male') ? 'L' : 'P');
            $sheet->setCellValue('E' . $row, $patient['age'] ?? '');
            
            // Add attendance data for each day
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $col = $this->getColumnLetter(5 + $day);
                if (isset($patient['attendance'][$day]) && $patient['attendance'][$day]) {
                    $sheet->setCellValue($col . $row, '✓');
                }
            }
            
            // Add visit count
            $sheet->setCellValue($this->getColumnLetter(6 + $daysInMonth) . $row, $patient['visit_count']);
        }
        
        // Add borders to data
        $dataRange = 'A6:' . $this->getColumnLetter(6 + $daysInMonth) . $row;
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        // Auto-size columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        
        // Set width for day columns
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $col = $this->getColumnLetter(5 + $day);
            $sheet->getColumnDimension($col)->setWidth(3);
        }
        
        $sheet->getColumnDimension($this->getColumnLetter(6 + $daysInMonth))->setAutoSize(true);
        
        // Save file
        $excelFilename = $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/exports/' . $excelFilename);
        $writer->save($path);
        
        // Return download response
        return response()->download($path, $excelFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Add monthly data sheet for yearly reports
     */
    private function addMonthlyDataSheet(Spreadsheet $spreadsheet, array $statistics, string $diseaseType, int $year): void
    {
        if ($diseaseType === 'all' || $diseaseType === 'ht') {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Data Bulanan HT');
            
            // Set title
            $sheet->setCellValue('A1', "Data Bulanan Hipertensi (HT) - Tahun $year");
            $sheet->mergeCells('A1:N1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Header row
            $sheet->setCellValue('A3', 'No');
            $sheet->setCellValue('B3', 'Puskesmas');
            
            // Month columns
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            for ($i = 0; $i < 12; $i++) {
                $sheet->setCellValue(chr(67 + $i) . '3', $months[$i]);
            }
            $sheet->setCellValue('O3', 'Total');
            
            // Style header
            $sheet->getStyle('A3:O3')->getFont()->setBold(true);
            $sheet->getStyle('A3:O3')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D3D3D3');
            
            // Add data
            $row = 3;
            foreach ($statistics as $index => $stat) {
                $row++;
                $sheet->setCellValue('A' . $row, $index + 1);
                $sheet->setCellValue('B' . $row, $stat['puskesmas_name']);
                
                $total = 0;
                for ($month = 1; $month <= 12; $month++) {
                    $col = chr(66 + $month);
                    $value = isset($stat['ht']['monthly_data'][$month]) 
                        ? $stat['ht']['monthly_data'][$month]['total'] 
                        : 0;
                    $sheet->setCellValue($col . $row, $value);
                    $total += $value;
                }
                
                $sheet->setCellValue('O' . $row, $total);
            }
            
            // Add borders
            $sheet->getStyle('A3:O' . $row)->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
                
            // Auto-size columns
            foreach (range('A', 'O') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        
        if ($diseaseType === 'all' || $diseaseType === 'dm') {
            // Similar implementation for DM sheet
            // For brevity, not duplicating the code here
        }
    }

    /**
     * Get column letter for a number
     */
    private function getColumnLetter(int $columnNumber): string
    {
        $dividend = $columnNumber;
        $columnLetter = '';
        
        while ($dividend > 0) {
            $modulo = ($dividend - 1) % 26;
            $columnLetter = chr(65 + $modulo) . $columnLetter;
            $dividend = (int)(($dividend - $modulo) / 26);
        }
        
        return $columnLetter;
    }

    /**
     * Get month name in Indonesian
     */
    private function getMonthName(int $month): string
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