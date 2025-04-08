<?php

namespace App\Exports;

use App\Models\Pasien;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PasienExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $puskesmasId;
    
    public function __construct($puskesmasId = null)
    {
        $this->puskesmasId = $puskesmasId;
    }
    
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Pasien::with(['puskesmas', 'jenisKelamin']);
        
        if ($this->puskesmasId) {
            $query->where('puskesmas_id', $this->puskesmasId);
        }
        
        return $query->get();
    }
    
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'No.',
            'NIK',
            'No. BPJS',
            'Nama',
            'Jenis Kelamin',
            'Tanggal Lahir',
            'Umur',
            'Alamat',
            'Puskesmas',
        ];
    }
    
    /**
     * @param mixed $pasien
     * @return array
     */
    public function map($pasien): array
    {
        $umur = \Carbon\Carbon::parse($pasien->tgl_lahir)->age;
        
        return [
            $pasien->id,
            $pasien->nik ?? '-',
            $pasien->no_bpjs ?? '-',
            $pasien->nama,
            $pasien->jenisKelamin->nama ?? '-',
            $pasien->tgl_lahir->format('d/m/Y'),
            $umur . ' tahun',
            $pasien->alamat ?? '-',
            $pasien->puskesmas->nama ?? '-',
        ];
    }
    
    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCCCCC');
    }
    
    /**
     * @return string
     */
    public function title(): string
    {
        return 'Daftar Pasien';
    }
}