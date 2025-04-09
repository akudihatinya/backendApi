<?php

namespace App\Exports;

use App\Models\LaporanBulanan;
use App\Models\LaporanDetail;
use App\Models\Puskesmas;
use App\Models\RefJenisProgram;
use App\Models\RefJenisKelamin;
use App\Models\RefStatus;
use App\Models\TahunProgram;
// Tambahkan use statement untuk Laravel Excel
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

class LaporanBulananExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithCustomStartCell, ShouldAutoSize, WithEvents
{
    protected $tahunProgramId;
    protected $bulan;
    protected $puskesmasId;
    
    public function __construct($tahunProgramId, $bulan, $puskesmasId = null)
    {
        $this->tahunProgramId = $tahunProgramId;
        $this->bulan = $bulan;
        $this->puskesmasId = $puskesmasId;
    }
    
    /**
     * @return string
     */
    public function title(): string
    {
        $tahunProgram = TahunProgram::find($this->tahunProgramId);
        $tahun = $tahunProgram ? $tahunProgram->tahun : date('Y');
        $namaBulan = $this->getNamaBulan($this->bulan);
        
        if ($this->puskesmasId) {
            $puskesmas = Puskesmas::find($this->puskesmasId);
            $namaPuskesmas = $puskesmas ? $puskesmas->nama : '';
            return "Laporan $namaBulan $tahun - $namaPuskesmas";
        } else {
            return "Laporan $namaBulan $tahun";
        }
    }
    
    public function startCell(): string
    {
        return 'A7';
    }
    
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // Get all jenis program
        $jenisPrograms = RefJenisProgram::all();
        
        // Get all jenis kelamin
        $jenisKelamins = RefJenisKelamin::all();
        
        // Get all status pasien (RUTIN, TERKENDALI, TIDAK_TERKENDALI)
        $statuses = RefStatus::where('kategori', 'pasien')->get();
        
        // Initialize result array
        $results = [];
        
        // If puskesmasId is provided, get only data for that puskesmas
        if ($this->puskesmasId) {
            $puskesmas = Puskesmas::find($this->puskesmasId);
            
            // Skip if puskesmas not found
            if (!$puskesmas) {
                return collect($results);
            }
            
            // Get or create laporan for this puskesmas
            $laporan = LaporanBulanan::where('tahun_program_id', $this->tahunProgramId)
                ->where('bulan', $this->bulan)
                ->where('puskesmas_id', $this->puskesmasId)
                ->first();
            
            // Aggregate data for each program, gender and status
            foreach ($jenisPrograms as $program) {
                foreach ($jenisKelamins as $kelamin) {
                    foreach ($statuses as $status) {
                        $jumlah = 0;
                        
                        if ($laporan) {
                            $detail = LaporanDetail::where('laporan_id', $laporan->id)
                                ->where('jenis_program_id', $program->id)
                                ->where('jenis_kelamin_id', $kelamin->id)
                                ->where('status_id', $status->id)
                                ->first();
                                
                            if ($detail) {
                                $jumlah = $detail->jumlah;
                            }
                        }
                        
                        $results[] = [
                            'puskesmas' => $puskesmas->nama,
                            'program' => $program->nama,
                            'kelamin' => $kelamin->nama,
                            'status' => $status->nama,
                            'jumlah' => $jumlah,
                        ];
                    }
                }
            }
        } else {
            // Get all puskesmas
            $puskesmasList = Puskesmas::all();
            
            foreach ($puskesmasList as $puskesmas) {
                // Get laporan for this puskesmas
                $laporan = LaporanBulanan::where('tahun_program_id', $this->tahunProgramId)
                    ->where('bulan', $this->bulan)
                    ->where('puskesmas_id', $puskesmas->id)
                    ->first();
                
                // Aggregate data for each program, gender and status
                foreach ($jenisPrograms as $program) {
                    foreach ($jenisKelamins as $kelamin) {
                        foreach ($statuses as $status) {
                            $jumlah = 0;
                            
                            if ($laporan) {
                                $detail = LaporanDetail::where('laporan_id', $laporan->id)
                                    ->where('jenis_program_id', $program->id)
                                    ->where('jenis_kelamin_id', $kelamin->id)
                                    ->where('status_id', $status->id)
                                    ->first();
                                    
                                if ($detail) {
                                    $jumlah = $detail->jumlah;
                                }
                            }
                            
                            $results[] = [
                                'puskesmas' => $puskesmas->nama,
                                'program' => $program->nama,
                                'kelamin' => $kelamin->nama,
                                'status' => $status->nama,
                                'jumlah' => $jumlah,
                            ];
                        }
                    }
                }
            }
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
            'Program',
            'Jenis Kelamin',
            'Status',
            'Jumlah',
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
            $row['program'],
            $row['kelamin'],
            $row['status'],
            $row['jumlah'],
        ];
    }
    
    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A7:E7')->getFont()->setBold(true);
        $sheet->getStyle('A7:E7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
    }
    
    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Get tahun program and bulan
                $tahunProgram = TahunProgram::find($this->tahunProgramId);
                $tahun = $tahunProgram ? $tahunProgram->tahun : date('Y');
                $namaBulan = $this->getNamaBulan($this->bulan);
                
                // Set title and header information
                $event->sheet->mergeCells('A1:E1');
                $event->sheet->setCellValue('A1', 'LAPORAN BULANAN PROGRAM PENANGGULANGAN HIPERTENSI & DIABETES MELLITUS');
                $event->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $event->sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $event->sheet->mergeCells('A2:E2');
                $event->sheet->setCellValue('A2', strtoupper('BULAN ' . $namaBulan . ' ' . $tahun));
                $event->sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
                $event->sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                if ($this->puskesmasId) {
                    $puskesmas = Puskesmas::find($this->puskesmasId);
                    if ($puskesmas) {
                        $event->sheet->mergeCells('A3:E3');
                        $event->sheet->setCellValue('A3', strtoupper($puskesmas->nama));
                        $event->sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
                        $event->sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                } else {
                    $event->sheet->mergeCells('A3:E3');
                    $event->sheet->setCellValue('A3', 'SELURUH PUSKESMAS');
                    $event->sheet->getStyle('A3')->getFont()->setBold(true)->setSize(12);
                    $event->sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                
                $event->sheet->mergeCells('A4:E4');
                $event->sheet->setCellValue('A4', 'DINAS KESEHATAN');
                $event->sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
                $event->sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // Add empty row as separator
                $event->sheet->mergeCells('A5:E5');
                $event->sheet->setCellValue('A5', '');
                
                // Add date
                $event->sheet->mergeCells('A6:E6');
                $event->sheet->setCellValue('A6', 'Tanggal Cetak: ' . date('d/m/Y'));
                $event->sheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            },
        ];
    }
    
    /**
     * Get nama bulan dalam Bahasa Indonesia
     */
    private function getNamaBulan($bulan)
    {
        $namaBulan = [
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
            12 => 'Desember',
        ];
        
        return $namaBulan[$bulan] ?? '';
    }
}