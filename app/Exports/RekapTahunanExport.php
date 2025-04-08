<?php

namespace App\Exports;

use App\Models\LaporanBulanan;
use App\Models\LaporanDetail;
use App\Models\Puskesmas;
use App\Models\RefJenisProgram;
use App\Models\RefStatus;
use App\Models\TahunProgram;
use App\Models\SasaranPuskesmas;
use App\Models\SasaranTahunan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

class RekapTahunanExport implements WithMultipleSheets
{
    protected $tahunProgramId;
    
    public function __construct($tahunProgramId)
    {
        $this->tahunProgramId = $tahunProgramId;
    }
    
    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        
        // Rekap per program
        $jenisPrograms = RefJenisProgram::all();
        foreach ($jenisPrograms as $jenisProgram) {
            $sheets[] = new RekapProgramSheet($this->tahunProgramId, $jenisProgram->id);
        }
        
        // Rekap pencapaian target
        $sheets[] = new RekapPencapaianSheet($this->tahunProgramId);
        
        return $sheets;
    }
}

class RekapProgramSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithCustomStartCell, ShouldAutoSize, WithEvents
{
    protected $tahunProgramId;
    protected $jenisProgramId;
    protected $jenisProgram;
    
    public function __construct($tahunProgramId, $jenisProgramId)
    {
        $this->tahunProgramId = $tahunProgramId;
        $this->jenisProgramId = $jenisProgramId;
        $this->jenisProgram = RefJenisProgram::find($jenisProgramId);
    }
    
    public function startCell(): string
    {
        return 'A7';
    }
    
    /**
     * @return string
     */
    public function title(): string
    {
        return $this->jenisProgram ? 'Rekap ' . $this->jenisProgram->nama : 'Rekap Program';
    }
    
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Get all puskesmas
        $puskesmasList = Puskesmas::all();
        
        // Get status terkendali
        $statusTerkendali = RefStatus::where('kode', 'TERKENDALI')->first();
        $statusTerkendaliId = $statusTerkendali ? $statusTerkendali->id : 0;
        
        // Get status rutin
        $statusRutin = RefStatus::where('kode', 'RUTIN')->first();
        $statusRutinId = $statusRutin ? $statusRutin->id : 0;
        
        // Initialize results array
        $results = [];
        
        foreach ($puskesmasList as $puskesmas) {
            // Get sasaran for this puskesmas and program
            $sasaran = SasaranPuskesmas::whereHas('sasaranTahunan', function($q) {
                $q->where('tahun_program_id', $this->tahunProgramId);
            })
            ->where('puskesmas_id', $puskesmas->id)
            ->where('jenis_program_id', $this->jenisProgramId)
            ->first();
            
            $targetValue = $sasaran ? $sasaran->nilai : 0;
            
            // Initialize bulan data
            $bulanData = [];
            
            // Get data for each month (1-12)
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $laporan = LaporanBulanan::where('tahun_program_id', $this->tahunProgramId)
                    ->where('bulan', $bulan)
                    ->where('puskesmas_id', $puskesmas->id)
                    ->first();
                    
                if ($laporan) {
                    // Get total pasien
                    $totalPasien = LaporanDetail::where('laporan_id', $laporan->id)
                        ->where('jenis_program_id', $this->jenisProgramId)
                        ->sum('jumlah');
                        
                    // Get total terkendali
                    $totalTerkendali = LaporanDetail::where('laporan_id', $laporan->id)
                        ->where('jenis_program_id', $this->jenisProgramId)
                        ->where('status_id', $statusTerkendaliId)
                        ->sum('jumlah');
                        
                    // Get total rutin
                    $totalRutin = LaporanDetail::where('laporan_id', $laporan->id)
                        ->where('jenis_program_id', $this->jenisProgramId)
                        ->where('status_id', $statusRutinId)
                        ->sum('jumlah');
                        
                    $bulanData["bulan_$bulan"] = [
                        'total' => $totalPasien,
                        'terkendali' => $totalTerkendali,
                        'rutin' => $totalRutin,
                    ];
                } else {
                    $bulanData["bulan_$bulan"] = [
                        'total' => 0,
                        'terkendali' => 0,
                        'rutin' => 0,
                    ];
                }
            }
            
            // Calculate totals for the year
            $totalPasienTahun = 0;
            $totalTerkendaliTahun = 0;
            $totalRutinTahun = 0;
            
            foreach ($bulanData as $data) {
                $totalPasienTahun += $data['total'];
                $totalTerkendaliTahun += $data['terkendali'];
                $totalRutinTahun += $data['rutin'];
            }
            
            // Calculate percentages
            $persentaseTerkendali = $totalPasienTahun > 0 ? round(($totalTerkendaliTahun / $totalPasienTahun) * 100, 2) : 0;
            $persentaseTarget = $targetValue > 0 ? round(($totalPasienTahun / $targetValue) * 100, 2) : 0;
            
            // Add to results
            $results[] = array_merge(
                [
                    'puskesmas' => $puskesmas->nama,
                    'target' => $targetValue,
                ],
                $bulanData,
                [
                    'total_pasien' => $totalPasienTahun,
                    'total_terkendali' => $totalTerkendaliTahun,
                    'total_rutin' => $totalRutinTahun,
                    'persentase_terkendali' => $persentaseTerkendali,
                    'persentase_target' => $persentaseTarget,
                ]
            );
        }
        
        return collect($results);
    }
    
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Puskesmas',
            'Target',
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'Mei',
            'Jun',
            'Jul',
            'Agt',
            'Sep',
            'Okt',
            'Nov',
            'Des',
            'Total Pasien',
            'Total Terkendali',
            'Total Rutin',
            '% Terkendali',
            '% Pencapaian Target',
        ];
    }
    
    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        return [
            $row['puskesmas'],
            $row['target'],
            $row['bulan_1']['total'],
            $row['bulan_2']['total'],
            $row['bulan_3']['total'],
            $row['bulan_4']['total'],
            $row['bulan_5']['total'],
            $row['bulan_6']['total'],
            $row['bulan_7']['total'],
            $row['bulan_8']['total'],
            $row['bulan_9']['total'],
            $row['bulan_10']['total'],
            $row['bulan_11']['total'],
            $row['bulan_12']['total'],
            $row['total_pasien'],
            $row['total_terkendali'],
            $row['total_rutin'],
            $row['persentase_terkendali'] . '%',
            $row['persentase_target'] . '%',
        ];
    }
    
    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A7:S7')->getFont()->setBold(true);
        $sheet->getStyle('A7:S7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A7:S7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
    }
    
    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Get tahun program 
                $tahunProgram = TahunProgram::find($this->tahunProgramId);
                $tahun = $tahunProgram ? $tahunProgram->tahun : date('Y');
                
                // Set title and header information
                $event->sheet->mergeCells('A1:S1');
                $event->sheet->setCellValue('A1', 'REKAP TAHUNAN PROGRAM ' . strtoupper($this->jenisProgram->nama));
                $event->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $event->sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $event->sheet->mergeCells('A2:S2');
                $event->sheet->setCellValue('A2', 'TAHUN ' . $tahun);
                $event->sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $event->sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $event->sheet->mergeCells('A3:S3');
                $event->sheet->setCellValue('A3', 'DINAS KESEHATAN');
                $event->sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
                $event->sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // Add empty row as separator
                $event->sheet->mergeCells('A4:S4');
                $event->sheet->setCellValue('A4', '');
                
                // Add date
                $event->sheet->mergeCells('A5:S5');
                $event->sheet->setCellValue('A5', 'Tanggal Cetak: ' . date('d/m/Y'));
                $event->sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                
                // Apply borders to the data
                $dataRange = 'A7:S' . ($event->sheet->getHighestRow());
                $event->sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                
                // Add conditional formatting for % columns
                $lastRow = $event->sheet->getHighestRow();
                $event->sheet->getStyle('R8:S'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');
                
                // Convert the percentage values from "90%" to 0.90
                for ($row = 8; $row <= $lastRow; $row++) {
                    $percentTerkendali = $event->sheet->getCell("R{$row}")->getValue();
                    $percentTarget = $event->sheet->getCell("S{$row}")->getValue();
                    
                    // Remove % and convert to decimal
                    if (is_string($percentTerkendali) && strpos($percentTerkendali, '%') !== false) {
                        $percentTerkendali = str_replace('%', '', $percentTerkendali) / 100;
                        $event->sheet->setCellValue("R{$row}", $percentTerkendali);
                    }
                    
                    if (is_string($percentTarget) && strpos($percentTarget, '%') !== false) {
                        $percentTarget = str_replace('%', '', $percentTarget) / 100;
                        $event->sheet->setCellValue("S{$row}", $percentTarget);
                    }
                }
                
                // Apply conditional formatting (color scales)
                $highestRow = $event->sheet->getHighestRow();
                $conditionalRange = 'R8:R' . $highestRow;
                $conditionalStyles = $event->sheet->getStyle($conditionalRange)->getConditionalStyles();
                
                $conditionalStyles[] = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $conditionalStyles[0]->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_COLORSCALE);
                $conditionalStyles[0]->setColorScale(
                    new \PhpOffice\PhpSpreadsheet\Style\ConditionalFormatting\ColorScale(
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED),
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW),
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_GREEN)
                    )
                );
                
                $event->sheet->getStyle($conditionalRange)->setConditionalStyles($conditionalStyles);
            },
        ];
    }
}

class RekapPencapaianSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithCustomStartCell, ShouldAutoSize, WithEvents
{
    protected $tahunProgramId;
    
    public function __construct($tahunProgramId)
    {
        $this->tahunProgramId = $tahunProgramId;
    }
    
    public function startCell(): string
    {
        return 'A7';
    }
    
    /**
     * @return string
     */
    public function title(): string
    {
        return 'Rekap Pencapaian Target';
    }
    
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Get all puskesmas
        $puskesmasList = Puskesmas::all();
        
        // Get all program
        $jenisPrograms = RefJenisProgram::all();
        
        // Initialize results array
        $results = [];
        
        foreach ($puskesmasList as $puskesmas) {
            $programData = [];
            
            foreach ($jenisPrograms as $program) {
                // Get sasaran for this puskesmas and program
                $sasaran = SasaranPuskesmas::whereHas('sasaranTahunan', function($q) {
                    $q->where('tahun_program_id', $this->tahunProgramId);
                })
                ->where('puskesmas_id', $puskesmas->id)
                ->where('jenis_program_id', $program->id)
                ->first();
                
                $targetValue = $sasaran ? $sasaran->nilai : 0;
                
                // Calculate total pasien for the year
                $totalPasien = LaporanDetail::whereHas('laporanBulanan', function($q) use ($puskesmas) {
                    $q->where('tahun_program_id', $this->tahunProgramId)
                      ->where('puskesmas_id', $puskesmas->id);
                })
                ->where('jenis_program_id', $program->id)
                ->sum('jumlah');
                
                // Calculate percentage
                $persentase = $targetValue > 0 ? round(($totalPasien / $targetValue) * 100, 2) : 0;
                
                $programData[$program->kode] = [
                    'target' => $targetValue,
                    'pencapaian' => $totalPasien,
                    'persentase' => $persentase,
                ];
            }
            
            // Add to results
            $results[] = [
                'puskesmas' => $puskesmas->nama,
                'programs' => $programData,
            ];
        }
        
        return collect($results);
    }
    
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Puskesmas',
            'Target HT',
            'Pencapaian HT',
            '% HT',
            'Target DM',
            'Pencapaian DM',
            '% DM',
        ];
    }
    
    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        return [
            $row['puskesmas'],
            $row['programs']['HT']['target'] ?? 0,
            $row['programs']['HT']['pencapaian'] ?? 0,
            ($row['programs']['HT']['persentase'] ?? 0) . '%',
            $row['programs']['DM']['target'] ?? 0,
            $row['programs']['DM']['pencapaian'] ?? 0,
            ($row['programs']['DM']['persentase'] ?? 0) . '%',
        ];
    }
    
    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A7:G7')->getFont()->setBold(true);
        $sheet->getStyle('A7:G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A7:G7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
    }
    
    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Get tahun program 
                $tahunProgram = TahunProgram::find($this->tahunProgramId);
                $tahun = $tahunProgram ? $tahunProgram->tahun : date('Y');
                
                // Set title and header information
                $event->sheet->mergeCells('A1:G1');
                $event->sheet->setCellValue('A1', 'REKAP PENCAPAIAN TARGET PROGRAM HT DAN DM');
                $event->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $event->sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $event->sheet->mergeCells('A2:G2');
                $event->sheet->setCellValue('A2', 'TAHUN ' . $tahun);
                $event->sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $event->sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $event->sheet->mergeCells('A3:G3');
                $event->sheet->setCellValue('A3', 'DINAS KESEHATAN');
                $event->sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
                $event->sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // Add empty row as separator
                $event->sheet->mergeCells('A4:G4');
                $event->sheet->setCellValue('A4', '');
                
                // Add date
                $event->sheet->mergeCells('A5:G5');
                $event->sheet->setCellValue('A5', 'Tanggal Cetak: ' . date('d/m/Y'));
                $event->sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                
                // Apply borders to the data
                $dataRange = 'A7:G' . ($event->sheet->getHighestRow());
                $event->sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                
                // Add conditional formatting for % columns
                $lastRow = $event->sheet->getHighestRow();
                $event->sheet->getStyle('D8:D'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');
                $event->sheet->getStyle('G8:G'.$lastRow)->getNumberFormat()->setFormatCode('0.00%');
                
                // Convert the percentage values from "90%" to 0.90
                for ($row = 8; $row <= $lastRow; $row++) {
                    $percentHT = $event->sheet->getCell("D{$row}")->getValue();
                    $percentDM = $event->sheet->getCell("G{$row}")->getValue();
                    
                    // Remove % and convert to decimal
                    if (is_string($percentHT) && strpos($percentHT, '%') !== false) {
                        $percentHT = str_replace('%', '', $percentHT) / 100;
                        $event->sheet->setCellValue("D{$row}", $percentHT);
                    }
                    
                    if (is_string($percentDM) && strpos($percentDM, '%') !== false) {
                        $percentDM = str_replace('%', '', $percentDM) / 100;
                        $event->sheet->setCellValue("G{$row}", $percentDM);
                    }
                }
                
                // Apply conditional formatting (color scales)
                $conditionalRangeHT = 'D8:D' . $lastRow;
                $conditionalRangeDM = 'G8:G' . $lastRow;
                
                // For HT
                $conditionalStylesHT = $event->sheet->getStyle($conditionalRangeHT)->getConditionalStyles();
                
                $conditionalStylesHT[] = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $conditionalStylesHT[0]->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_COLORSCALE);
                $conditionalStylesHT[0]->setColorScale(
                    new \PhpOffice\PhpSpreadsheet\Style\ConditionalFormatting\ColorScale(
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED),
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW),
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_GREEN)
                    )
                );
                
                $event->sheet->getStyle($conditionalRangeHT)->setConditionalStyles($conditionalStylesHT);
                
                // For DM
                $conditionalStylesDM = $event->sheet->getStyle($conditionalRangeDM)->getConditionalStyles();
                
                $conditionalStylesDM[] = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
                $conditionalStylesDM[0]->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_COLORSCALE);
                $conditionalStylesDM[0]->setColorScale(
                    new \PhpOffice\PhpSpreadsheet\Style\ConditionalFormatting\ColorScale(
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED),
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_YELLOW),
                        new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_GREEN)
                    )
                );
                
                $event->sheet->getStyle($conditionalRangeDM)->setConditionalStyles($conditionalStylesDM);
            },
        ];
    }
}