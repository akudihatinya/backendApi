<?php

namespace App\Exports;

use App\Models\Pemeriksaan;
use App\Models\RefJenisProgram;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PemeriksaanExport implements WithMultipleSheets
{
    protected $puskesmasId;
    protected $tahunProgramId;
    protected $startDate;
    protected $endDate;
    
    public function __construct($tahunProgramId, $puskesmasId = null, $startDate = null, $endDate = null)
    {
        $this->tahunProgramId = $tahunProgramId;
        $this->puskesmasId = $puskesmasId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }
    
    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        
        // Get all jenis program (HT, DM)
        $jenisPrograms = RefJenisProgram::all();
        
        foreach ($jenisPrograms as $jenisProgram) {
            $sheets[] = new PemeriksaanProgramSheet(
                $this->tahunProgramId,
                $jenisProgram->id,
                $this->puskesmasId,
                $this->startDate,
                $this->endDate
            );
        }
        
        return $sheets;
    }
}

class PemeriksaanProgramSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $tahunProgramId;
    protected $jenisProgramId;
    protected $puskesmasId;
    protected $startDate;
    protected $endDate;
    protected $jenisProgram;
    
    public function __construct($tahunProgramId, $jenisProgramId, $puskesmasId = null, $startDate = null, $endDate = null)
    {
        $this->tahunProgramId = $tahunProgramId;
        $this->jenisProgramId = $jenisProgramId;
        $this->puskesmasId = $puskesmasId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->jenisProgram = RefJenisProgram::find($jenisProgramId);
    }
    
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Pemeriksaan::with([
            'pasien.puskesmas', 
            'pasien.jenisKelamin', 
            'petugas', 
            'pemeriksaanParam',
            'pemeriksaanStatus.status'
        ])
        ->where('tahun_program_id', $this->tahunProgramId)
        ->whereHas('pemeriksaanStatus', function($q) {
            $q->where('jenis_program_id', $this->jenisProgramId);
        });
        
        if ($this->puskesmasId) {
            $query->whereHas('pasien', function($q) {
                $q->where('puskesmas_id', $this->puskesmasId);
            });
        }
        
        if ($this->startDate) {
            $query->where('tgl_periksa', '>=', $this->startDate);
        }
        
        if ($this->endDate) {
            $query->where('tgl_periksa', '<=', $this->endDate);
        }
        
        return $query->orderBy('tgl_periksa', 'desc')->get();
    }
    
    /**
     * @return array
     */
    public function headings(): array
    {
        if ($this->jenisProgram && $this->jenisProgram->kode === 'HT') {
            return [
                'No.',
                'Tanggal Periksa',
                'NIK/BPJS',
                'Nama Pasien',
                'Jenis Kelamin',
                'Umur',
                'Sistole (mmHg)',
                'Diastole (mmHg)',
                'Status',
                'Puskesmas',
                'Petugas',
            ];
        } else {
            return [
                'No.',
                'Tanggal Periksa',
                'NIK/BPJS',
                'Nama Pasien',
                'Jenis Kelamin',
                'Umur',
                'Jenis Pemeriksaan',
                'Nilai',
                'Status',
                'Puskesmas',
                'Petugas',
            ];
        }
    }
    
    /**
     * @param mixed $pemeriksaan
     * @return array
     */
    public function map($pemeriksaan): array
    {
        $pasien = $pemeriksaan->pasien;
        $umur = \Carbon\Carbon::parse($pasien->tgl_lahir)->age;
        $identitas = $pasien->nik ?? $pasien->no_bpjs ?? '-';
        
        if ($this->jenisProgram && $this->jenisProgram->kode === 'HT') {
            // HT parameters
            $sistole = '-';
            $diastole = '-';
            
            foreach ($pemeriksaan->pemeriksaanParam as $param) {
                if ($param->nama_parameter === 'sistole') {
                    $sistole = $param->nilai;
                } elseif ($param->nama_parameter === 'diastole') {
                    $diastole = $param->nilai;
                }
            }
            
            return [
                $pemeriksaan->id,
                $pemeriksaan->tgl_periksa->format('d/m/Y'),
                $identitas,
                $pasien->nama,
                $pasien->jenisKelamin->nama ?? '-',
                $umur . ' tahun',
                $sistole,
                $diastole,
                $pemeriksaan->pemeriksaanStatus->first()->status->nama ?? '-',
                $pasien->puskesmas->nama ?? '-',
                $pemeriksaan->petugas->nama_puskesmas ?? '-',
            ];
        } else {
            // DM parameters
            $jenisPemeriksaan = '-';
            $nilai = '-';
            
            if ($pemeriksaan->pemeriksaanParam->count() > 0) {
                $param = $pemeriksaan->pemeriksaanParam->first();
                $jenisPemeriksaan = $this->getNamaParameterDM($param->nama_parameter);
                $nilai = $param->nilai;
            }
            
            return [
                $pemeriksaan->id,
                $pemeriksaan->tgl_periksa->format('d/m/Y'),
                $identitas,
                $pasien->nama,
                $pasien->jenisKelamin->nama ?? '-',
                $umur . ' tahun',
                $jenisPemeriksaan,
                $nilai,
                $pemeriksaan->pemeriksaanStatus->first()->status->nama ?? '-',
                $pasien->puskesmas->nama ?? '-',
                $pemeriksaan->petugas->nama_puskesmas ?? '-',
            ];
        }
    }
    
    /**
     * Get nama parameter DM in Indonesian
     */
    private function getNamaParameterDM($parameter)
    {
        switch ($parameter) {
            case 'gdp':
                return 'Gula Darah Puasa';
            case 'gd2pp':
                return 'Gula Darah 2 Jam PP';
            case 'hba1c':
                return 'HbA1c';
            default:
                return $parameter;
        }
    }
    
    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        $lastColumn = $this->jenisProgram && $this->jenisProgram->kode === 'HT' ? 'K' : 'K';
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastColumn . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
    }
    
    /**
     * @return string
     */
    public function title(): string
    {
        return $this->jenisProgram ? 'Pemeriksaan ' . $this->jenisProgram->nama : 'Pemeriksaan';
    }
}