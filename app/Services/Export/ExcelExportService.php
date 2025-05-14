<?php

namespace App\Services\Export;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class ExcelExportService
{
    /**
     * Export statistics data to Excel
     */
    public function exportStatistics(
        array $data, 
        string $filename, 
        string $title, 
        ?string $subtitle = null
    ): string {
        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:M1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set subtitle if provided
        if ($subtitle) {
            $sheet->setCellValue('A2', $subtitle);
            $sheet->mergeCells('A2:M2');
            $sheet->getStyle('A2')->getFont()->setSize(12);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $currentRow = 3;
        } else {
            $currentRow = 2;
        }
        
        // Add generation timestamp
        $sheet->setCellValue('A' . $currentRow, 'Generated: ' . Carbon::now()->format('d F Y H:i:s'));
        $sheet->mergeCells('A' . $currentRow . ':M' . $currentRow);
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $currentRow += 2;
        
        // Headers
        $isRecap = isset($data[0]['puskesmas_name']);
        
        if ($isRecap) {
            $headers = ['No', 'Puskesmas'];
            $startColumn = 'C';
        } else {
            $headers = [];
            $startColumn = 'A';
        }
        
        // Add disease type headers
        $diseaseType = '';
        if (isset($data[0]['ht']) && isset($data[0]['dm'])) {
            $diseaseType = 'both';
        } elseif (isset($data[0]['ht'])) {
            $diseaseType = 'ht';
        } elseif (isset($data[0]['dm'])) {
            $diseaseType = 'dm';
        }
        
        if ($diseaseType === 'both' || $diseaseType === 'ht') {
            $headers = array_merge($headers, [
                'Target HT',
                'Total Pasien HT',
                'Pencapaian HT (%)',
                'Pasien Standar HT',
                'Pasien Non-Standar HT',
                'Pasien Laki-laki HT',
                'Pasien Perempuan HT',
            ]);
        }
        
        if ($diseaseType === 'both' || $diseaseType === 'dm') {
            $headers = array_merge($headers, [
                'Target DM',
                'Total Pasien DM',
                'Pencapaian DM (%)',
                'Pasien Standar DM',
                'Pasien Non-Standar DM',
                'Pasien Laki-laki DM',
                'Pasien Perempuan DM',
            ]);
        }
        
        // Add headers to sheet
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . $currentRow, $header);
            $column++;
        }
        
        $lastColumn = --$column;
        
        // Style headers
        $headerRange = 'A' . $currentRow . ':' . $lastColumn . $currentRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
            
        // Auto-size columns
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Add data rows
        $currentRow++;
        foreach ($data as $index => $item) {
            $column = 'A';
            
            if ($isRecap) {
                $sheet->setCellValue($column++, $index + 1);
                $sheet->setCellValue($column++, $item['puskesmas_name']);
            }
            
            if ($diseaseType === 'both' || $diseaseType === 'ht') {
                $ht = $item['ht'];
                $sheet->setCellValue($column++, $ht['target']);
                $sheet->setCellValue($column++, $ht['total_patients']);
                $sheet->setCellValue($column++, $ht['achievement_percentage']);
                $sheet->setCellValue($column++, $ht['standard_patients']);
                $sheet->setCellValue($column++, $ht['non_standard_patients']);
                $sheet->setCellValue($column++, $ht['male_patients']);
                $sheet->setCellValue($column++, $ht['female_patients']);
            }
            
            if ($diseaseType === 'both' || $diseaseType === 'dm') {
                $dm = $item['dm'];
                $sheet->setCellValue($column++, $dm['target']);
                $sheet->setCellValue($column++, $dm['total_patients']);
                $sheet->setCellValue($column++, $dm['achievement_percentage']);
                $sheet->setCellValue($column++, $dm['standard_patients']);
                $sheet->setCellValue($column++, $dm['non_standard_patients']);
                $sheet->setCellValue($column++, $dm['male_patients']);
                $sheet->setCellValue($column++, $dm['female_patients']);
            }
            
            $currentRow++;
        }
        
        // Style data
        $dataRange = 'A' . ($currentRow - count($data)) . ':' . $lastColumn . ($currentRow - 1);
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
            
        // If there's monthly data, add a new sheet
        if (isset($data[0]['ht']['monthly_data']) || isset($data[0]['dm']['monthly_data'])) {
            $this->addMonthlyDataSheet($spreadsheet, $data, $diseaseType, $isRecap);
        }
        
        // Create writer and save file
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $writer->save($path);
        
        return $path;
    }
    
    /**
     * Add a sheet with monthly data
     */
    private function addMonthlyDataSheet(Spreadsheet $spreadsheet, array $data, string $diseaseType, bool $isRecap): void
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        
        if ($diseaseType === 'both' || $diseaseType === 'ht') {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Data Bulanan HT');
            
            // Add title
            $sheet->setCellValue('A1', 'Data Bulanan Hipertensi (HT)');
            $sheet->mergeCells('A1:O1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Add generation timestamp
            $sheet->setCellValue('A2', 'Generated: ' . Carbon::now()->format('d F Y H:i:s'));
            $sheet->mergeCells('A2:O2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // Headers
            $currentRow = 4;
            $column = 'A';
            
            if ($isRecap) {
                $sheet->setCellValue($column++, 'No');
                $sheet->setCellValue($column++, 'Puskesmas');
            }
            
            foreach ($months as $month) {
                $sheet->setCellValue($column++, $month);
            }
            
            $sheet->setCellValue($column, 'Total');
            $lastColumn = $column;
            
            // Style header
            $headerRange = 'A' . $currentRow . ':' . $lastColumn . $currentRow;
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');
            $sheet->getStyle($headerRange)->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($headerRange)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
                
            // Add data
            $currentRow++;
            foreach ($data as $index => $item) {
                $column = 'A';
                
                if ($isRecap) {
                    $sheet->setCellValue($column++, $index + 1);
                    $sheet->setCellValue($column++, $item['puskesmas_name']);
                }
                
                $total = 0;
                for ($m = 1; $m <= 12; $m++) {
                    $monthlyData = $item['ht']['monthly_data'][$m] ?? ['total' => 0];
                    $value = $monthlyData['total'];
                    $total += $value;
                    $sheet->setCellValue($column++, $value);
                }
                
                $sheet->setCellValue($column, $total);
                $currentRow++;
            }
            
            // Style data
            $dataRange = 'A' . ($currentRow - count($data)) . ':' . $lastColumn . ($currentRow - 1);
            $sheet->getStyle($dataRange)->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
                
            // Auto-size columns
            foreach (range('A', $lastColumn) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        
        if ($diseaseType === 'both' || $diseaseType === 'dm') {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Data Bulanan DM');
            
            // Add title
            $sheet->setCellValue('A1', 'Data Bulanan Diabetes Mellitus (DM)');
            $sheet->mergeCells('A1:O1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Add generation timestamp
            $sheet->setCellValue('A2', 'Generated: ' . Carbon::now()->format('d F Y H:i:s'));
            $sheet->mergeCells('A2:O2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // Headers
            $currentRow = 4;
            $column = 'A';
            
            if ($isRecap) {
                $sheet->setCellValue($column++, 'No');
                $sheet->setCellValue($column++, 'Puskesmas');
            }
            
            foreach ($months as $month) {
                $sheet->setCellValue($column++, $month);
            }
            
            $sheet->setCellValue($column, 'Total');
            $lastColumn = $column;
            
            // Style header
            $headerRange = 'A' . $currentRow . ':' . $lastColumn . $currentRow;
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDDDDD');
            $sheet->getStyle($headerRange)->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($headerRange)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
                
            // Add data
            $currentRow++;
            foreach ($data as $index => $item) {
                $column = 'A';
                
                if ($isRecap) {
                    $sheet->setCellValue($column++, $index + 1);
                    $sheet->setCellValue($column++, $item['puskesmas_name']);
                }
                
                $total = 0;
                for ($m = 1; $m <= 12; $m++) {
                    $monthlyData = $item['dm']['monthly_data'][$m] ?? ['total' => 0];
                    $value = $monthlyData['total'];
                    $total += $value;
                    $sheet->setCellValue($column++, $value);
                }
                
                $sheet->setCellValue($column, $total);
                $currentRow++;
            }
            
            // Style data
            $dataRange = 'A' . ($currentRow - count($data)) . ':' . $lastColumn . ($currentRow - 1);
            $sheet->getStyle($dataRange)->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
                
            // Auto-size columns
            foreach (range('A', $lastColumn) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
    }
    
    /**
     * Export monitoring data to Excel
     */
    public function exportMonitoring(
        array $data, 
        string $filename, 
        string $title, 
        string $subtitle
    ): string {
        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:AH1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set subtitle
        $sheet->setCellValue('A2', $subtitle);
        $sheet->mergeCells('A2:AH2');
        $sheet->getStyle('A2')->getFont()->setSize(12);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Add generation timestamp
        $sheet->setCellValue('A3', 'Generated: ' . Carbon::now()->format('d F Y H:i:s'));
        $sheet->mergeCells('A3:AH3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        // Empty row
        $currentRow = 5;
        
        // Basic info
        $sheet->setCellValue('A' . $currentRow, 'Puskesmas:');
        $sheet->setCellValue('B' . $currentRow, $data['puskesmas_name']);
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
        
        $currentRow++;
        $sheet->setCellValue('A' . $currentRow, 'Bulan:');
        $sheet->setCellValue('B' . $currentRow, $data['month_name'] . ' ' . $data['year']);
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
        
        $currentRow += 2;
        
        // Headers - row 1
        $sheet->setCellValue('A' . $currentRow, 'No');
        $sheet->setCellValue('B' . $currentRow, 'No. RM');
        $sheet->setCellValue('C' . $currentRow, 'Nama Pasien');
        $sheet->setCellValue('D' . $currentRow, 'JK');
        $sheet->setCellValue('E' . $currentRow, 'Umur');
        
        $sheet->setCellValue('F' . $currentRow, 'Kedatangan (Tanggal)');
        $lastDateCol = $this->getExcelColumn(5 + $data['days_in_month']);
        $sheet->mergeCells('F' . $currentRow . ':' . $lastDateCol . $currentRow);
        
        $sheet->setCellValue($this->getExcelColumn(6 + $data['days_in_month']) . $currentRow, 'Jml');
        
        // Style header row 1
        $headerRange = 'A' . $currentRow . ':' . $this->getExcelColumn(6 + $data['days_in_month']) . $currentRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
            
        // Headers - row 2
        $currentRow++;
        
        $sheet->setCellValue('A' . $currentRow, '');
        $sheet->setCellValue('B' . $currentRow, '');
        $sheet->setCellValue('C' . $currentRow, '');
        $sheet->setCellValue('D' . $currentRow, '');
        $sheet->setCellValue('E' . $currentRow, '');
        
        // Date columns
        for ($day = 1; $day <= $data['days_in_month']; $day++) {
            $column = $this->getExcelColumn(5 + $day);
            $sheet->setCellValue($column . $currentRow, $day);
            $sheet->getColumnDimension($column)->setWidth(3);
        }
        
        $sheet->setCellValue($this->getExcelColumn(6 + $data['days_in_month']) . $currentRow, 'Kunj.');
        
        // Style header row 2
        $headerRange = 'A' . $currentRow . ':' . $this->getExcelColumn(6 + $data['days_in_month']) . $currentRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDDDDD');
        $sheet->getStyle($headerRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
            
        // Add patient data
        $currentRow++;
        $startDataRow = $currentRow;
        
        $patientType = isset($data['patients']['ht']) ? 'ht' : 'dm';
        $patients = $data['patients'][$patientType] ?? [];
        
        foreach ($patients as $index => $patient) {
            $sheet->setCellValue('A' . $currentRow, $index + 1);
            $sheet->setCellValue('B' . $currentRow, $patient['medical_record_number'] ?? '');
            $sheet->setCellValue('C' . $currentRow, $patient['patient_name']);
            $sheet->setCellValue('D' . $currentRow, $patient['gender'] === 'male' ? 'L' : 'P');
            $sheet->setCellValue('E' . $currentRow, $patient['age']);
            
            // Attendance columns
            for ($day = 1; $day <= $data['days_in_month']; $day++) {
                $column = $this->getExcelColumn(5 + $day);
                $attended = isset($patient['attendance'][$day]) && $patient['attendance'][$day];
                $sheet->setCellValue($column . $currentRow, $attended ? '✓' : '');
            }
            
            // Visit count
            $sheet->setCellValue($this->getExcelColumn(6 + $data['days_in_month']) . $currentRow, $patient['visit_count']);
            
            $currentRow++;
        }
        
        // Style data
        $dataRange = 'A' . $startDataRow . ':' . $this->getExcelColumn(6 + $data['days_in_month']) . ($currentRow - 1);
        $sheet->getStyle($dataRange)->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
            
        // Center align specific columns
        $sheet->getStyle('A' . $startDataRow . ':A' . ($currentRow - 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $startDataRow . ':D' . ($currentRow - 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . $startDataRow . ':E' . ($currentRow - 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
        // Center align attendance columns
        for ($day = 1; $day <= $data['days_in_month']; $day++) {
            $column = $this->getExcelColumn(5 + $day);
            $sheet->getStyle($column . $startDataRow . ':' . $column . ($currentRow - 1))->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        // Center align visit count column
        $sheet->getStyle($this->getExcelColumn(6 + $data['days_in_month']) . $startDataRow . ':' . 
            $this->getExcelColumn(6 + $data['days_in_month']) . ($currentRow - 1))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
        // Auto-size specific columns
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setWidth(30); // Name column
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension($this->getExcelColumn(6 + $data['days_in_month']))->setAutoSize(true);
        
        // Create writer and save file
        $writer = new Xlsx($spreadsheet);
        $path = storage_path('app/public/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $writer->save($path);
        
        return $path;
    }
    
    /**
     * Convert column number to Excel column letter (A, B, C, ... AA, AB, etc.)
     */
    private function getExcelColumn(int $columnNumber): string
    {
        $columnLetter = '';
        
        while ($columnNumber > 0) {
            $modulo = ($columnNumber - 1) % 26;
            $columnLetter = chr(65 + $modulo) . $columnLetter;
            $columnNumber = (int)(($columnNumber - $modulo) / 26);
        }
        
        return $columnLetter;
    }
}