<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pemeriksaan;
use App\Models\PemeriksaanParam;
use App\Models\PemeriksaanStatus;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\RefJenisProgram;
use App\Models\RefStatus;
use App\Models\TahunProgram;
use App\Models\LaporanBulanan;
use App\Models\LaporanDetail;
use App\Models\SasaranPuskesmas;
use App\Models\PencapaianBulanan;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard data for dinas
     */
    public function dinasDashboard(Request $request)
    {
        // Cek tahun program aktif
        $tahunProgram = null;
        
        if ($request->has('tahun_program_id') && $request->tahun_program_id) {
            $tahunProgram = TahunProgram::find($request->tahun_program_id);
        } else {
            $tahunProgram = TahunProgram::where('is_active', true)->first();
        }
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun program tidak ditemukan',
            ], 404);
        }
        
        // Ambil bulan untuk analisis
        $bulan = null;
        if ($request->has('bulan') && $request->bulan) {
            $bulan = (int) $request->bulan;
        } else {
            $bulan = (int) date('m');
        }
        
        // 1. Total pasien per program
        $totalPasien = $this->getTotalPasienPerProgram($tahunProgram->id, $bulan);
        
        // 2. Total pasien terkendali per program
        $pasienTerkendali = $this->getPasienTerkendaliPerProgram($tahunProgram->id, $bulan);
        
        // 3. Total pasien rutin per program
        $pasienRutin = $this->getPasienRutinPerProgram($tahunProgram->id, $bulan);
        
        // 4. Pencapaian per puskesmas
        $pencapaianPuskesmas = $this->getPencapaianPerPuskesmas($tahunProgram->id, $bulan);
        
        // 5. Persentase sasaran puskesmas
        $persentaseSasaran = $this->getPersentaseSasaran($tahunProgram->id, $bulan);
        
        // 6. Status laporan puskesmas
        $statusLaporan = $this->getStatusLaporanPuskesmas($tahunProgram->id, $bulan);
        
        // 7. Data per bulan (trend)
        $dataPerBulan = $this->getDataPerBulan($tahunProgram->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'tahun_program' => $tahunProgram,
                'bulan' => $bulan,
                'total_pasien' => $totalPasien,
                'pasien_terkendali' => $pasienTerkendali,
                'pasien_rutin' => $pasienRutin,
                'pencapaian_puskesmas' => $pencapaianPuskesmas,
                'persentase_sasaran' => $persentaseSasaran,
                'status_laporan' => $statusLaporan,
                'data_per_bulan' => $dataPerBulan,
            ]
        ]);
    }
    
    /**
     * Get dashboard data for puskesmas
     */
    public function puskesmasDashboard(Request $request)
    {
        // Validasi input
        if (!$request->has('puskesmas_id') && !$request->user()->nama_puskesmas) {
            return response()->json([
                'success' => false,
                'message' => 'ID Puskesmas diperlukan',
            ], 400);
        }
        
        // Cek tahun program aktif
        $tahunProgram = null;
        
        if ($request->has('tahun_program_id') && $request->tahun_program_id) {
            $tahunProgram = TahunProgram::find($request->tahun_program_id);
        } else {
            $tahunProgram = TahunProgram::where('is_active', true)->first();
        }
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun program tidak ditemukan',
            ], 404);
        }
        
        // Ambil bulan untuk analisis
        $bulan = null;
        if ($request->has('bulan') && $request->bulan) {
            $bulan = (int) $request->bulan;
        } else {
            $bulan = (int) date('m');
        }
        
        // Ambil ID puskesmas
        $puskesmasId = null;
        if ($request->has('puskesmas_id')) {
            $puskesmasId = $request->puskesmas_id;
        } else {
            $puskesmasId = Puskesmas::where('nama', $request->user()->nama_puskesmas)->value('id');
        }
        
        if (!$puskesmasId) {
            return response()->json([
                'success' => false,
                'message' => 'Puskesmas tidak ditemukan',
            ], 404);
        }
        
        // 1. Total pasien per program untuk puskesmas ini
        $totalPasien = $this->getTotalPasienPerProgramByPuskesmas($puskesmasId, $tahunProgram->id, $bulan);
        
        // 2. Total pasien terkendali per program untuk puskesmas ini
        $pasienTerkendali = $this->getPasienTerkendaliPerProgramByPuskesmas($puskesmasId, $tahunProgram->id, $bulan);
        
        // 3. Total pasien rutin per program untuk puskesmas ini
        $pasienRutin = $this->getPasienRutinPerProgramByPuskesmas($puskesmasId, $tahunProgram->id, $bulan);
        
        // 4. Sasaran puskesmas
        $sasaranPuskesmas = $this->getSasaranPuskesmas($puskesmasId, $tahunProgram->id);
        
        // 5. Pencapaian puskesmas
        $pencapaianPuskesmas = $this->getPencapaianPuskesmasByBulan($puskesmasId, $tahunProgram->id, $bulan);
        
        // 6. Status laporan puskesmas
        $statusLaporan = $this->getStatusLaporanByPuskesmas($puskesmasId, $tahunProgram->id, $bulan);
        
        // 7. Data per bulan (trend) untuk puskesmas ini
        $dataPerBulan = $this->getDataPerBulanByPuskesmas($puskesmasId, $tahunProgram->id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'puskesmas' => Puskesmas::find($puskesmasId),
                'tahun_program' => $tahunProgram,
                'bulan' => $bulan,
                'total_pasien' => $totalPasien,
                'pasien_terkendali' => $pasienTerkendali,
                'pasien_rutin' => $pasienRutin,
                'sasaran_puskesmas' => $sasaranPuskesmas,
                'pencapaian_puskesmas' => $pencapaianPuskesmas,
                'status_laporan' => $statusLaporan,
                'data_per_bulan' => $dataPerBulan,
            ]
        ]);
    }
    
    /**
     * Get total pasien per program
     */
    private function getTotalPasienPerProgram($tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $result = [];
        
        foreach ($jenisProgram as $jp) {
            $totalPasien = Pasien::whereHas('pemeriksaan', function($q) use($tahunProgramId, $bulan, $jp) {
                $q->where('tahun_program_id', $tahunProgramId)
                  ->whereHas('pemeriksaanStatus', function($sq) use($jp) {
                      $sq->where('jenis_program_id', $jp->id);
                  })
                  ->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
            })->count();
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'total' => $totalPasien,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get pasien terkendali per program
     */
    private function getPasienTerkendaliPerProgram($tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $statusTerkendali = RefStatus::where('kode', 'TERKENDALI')->first();
        $result = [];
        
        if (!$statusTerkendali) {
            return $result;
        }
        
        foreach ($jenisProgram as $jp) {
            // Menggunakan data laporan
            $totalPasienTerkendali = LaporanDetail::whereHas('laporanBulanan', function($q) use($tahunProgramId, $bulan) {
                $q->where('tahun_program_id', $tahunProgramId)
                  ->where('bulan', $bulan);
            })
            ->where('jenis_program_id', $jp->id)
            ->where('status_id', $statusTerkendali->id)
            ->sum('jumlah');
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'total' => $totalPasienTerkendali,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get pasien rutin per program
     */
    private function getPasienRutinPerProgram($tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $statusRutin = RefStatus::where('kode', 'RUTIN')->first();
        $result = [];
        
        if (!$statusRutin) {
            return $result;
        }
        
        foreach ($jenisProgram as $jp) {
            // Menggunakan data laporan
            $totalPasienRutin = LaporanDetail::whereHas('laporanBulanan', function($q) use($tahunProgramId, $bulan) {
                $q->where('tahun_program_id', $tahunProgramId)
                  ->where('bulan', $bulan);
            })
            ->where('jenis_program_id', $jp->id)
            ->where('status_id', $statusRutin->id)
            ->sum('jumlah');
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'total' => $totalPasienRutin,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get pencapaian per puskesmas
     */
    private function getPencapaianPerPuskesmas($tahunProgramId, $bulan)
    {
        $puskesmas = Puskesmas::all();
        $jenisProgram = RefJenisProgram::all();
        $result = [];
        
        foreach ($puskesmas as $p) {
            $pencapaianProgram = [];
            
            foreach ($jenisProgram as $jp) {
                $pencapaian = PencapaianBulanan::where('tahun_program_id', $tahunProgramId)
                    ->where('puskesmas_id', $p->id)
                    ->where('jenis_program_id', $jp->id)
                    ->where('bulan', $bulan)
                    ->first();
                    
                $sasaran = SasaranPuskesmas::whereHas('sasaranTahunan', function($q) use($tahunProgramId) {
                    $q->where('tahun_program_id', $tahunProgramId);
                })
                ->where('puskesmas_id', $p->id)
                ->where('jenis_program_id', $jp->id)
                ->first();
                
                $pencapaianProgram[] = [
                    'jenis_program' => $jp->nama,
                    'kode' => $jp->kode,
                    'nilai' => $pencapaian ? $pencapaian->nilai : 0,
                    'sasaran' => $sasaran ? $sasaran->nilai : 0,
                    'persentase' => $sasaran && $sasaran->nilai > 0 && $pencapaian
                        ? round(($pencapaian->nilai / $sasaran->nilai) * 100, 2)
                        : 0,
                ];
            }
            
            $result[] = [
                'puskesmas' => $p->nama,
                'kode' => $p->kode,
                'pencapaian' => $pencapaianProgram,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get persentase sasaran
     */
    private function getPersentaseSasaran($tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $result = [];
        
        foreach ($jenisProgram as $jp) {
            $totalSasaran = SasaranPuskesmas::whereHas('sasaranTahunan', function($q) use($tahunProgramId) {
                $q->where('tahun_program_id', $tahunProgramId);
            })
            ->where('jenis_program_id', $jp->id)
            ->sum('nilai');
            
            $totalPencapaian = PencapaianBulanan::where('tahun_program_id', $tahunProgramId)
                ->where('jenis_program_id', $jp->id)
                ->where('bulan', $bulan)
                ->sum('nilai');
                
            $persentase = $totalSasaran > 0 ? round(($totalPencapaian / $totalSasaran) * 100, 2) : 0;
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'sasaran' => $totalSasaran,
                'pencapaian' => $totalPencapaian,
                'persentase' => $persentase,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get status laporan puskesmas
     */
    private function getStatusLaporanPuskesmas($tahunProgramId, $bulan)
    {
        $puskesmas = Puskesmas::all();
        $statuses = RefStatus::where('kategori', 'laporan')->get();
        $result = [];
        
        foreach ($puskesmas as $p) {
            $laporan = LaporanBulanan::where('tahun_program_id', $tahunProgramId)
                ->where('puskesmas_id', $p->id)
                ->where('bulan', $bulan)
                ->first();
                
            $statusNama = $laporan ? $laporan->status->nama : 'Belum Ada';
            $statusKode = $laporan ? $laporan->status->kode : 'NONE';
            
            $result[] = [
                'puskesmas' => $p->nama,
                'kode' => $p->kode,
                'status' => $statusNama,
                'kode_status' => $statusKode,
                'tanggal_submit' => $laporan && $laporan->submitted_at ? $laporan->submitted_at->format('Y-m-d H:i:s') : null,
                'tanggal_approve' => $laporan && $laporan->approved_at ? $laporan->approved_at->format('Y-m-d H:i:s') : null,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get data per bulan (trend)
     */
    private function getDataPerBulan($tahunProgramId)
    {
        $jenisProgram = RefJenisProgram::all();
        $bulanData = [];
        $currentMonth = (int) date('m');
        
        // Hanya tampilkan data sampai bulan saat ini
        for ($i = 1; $i <= $currentMonth; $i++) {
            $dataProgram = [];
            
            foreach ($jenisProgram as $jp) {
                $totalPasien = LaporanDetail::whereHas('laporanBulanan', function($q) use($tahunProgramId, $i) {
                    $q->where('tahun_program_id', $tahunProgramId)
                      ->where('bulan', $i);
                })
                ->where('jenis_program_id', $jp->id)
                ->sum('jumlah');
                
                $dataProgram[] = [
                    'jenis_program' => $jp->nama,
                    'kode' => $jp->kode,
                    'total' => $totalPasien,
                ];
            }
            
            $bulanData[] = [
                'bulan' => $i,
                'nama_bulan' => $this->getNamaBulan($i),
                'data' => $dataProgram,
            ];
        }
        
        return $bulanData;
    }
    
    /**
     * Get total pasien per program by puskesmas
     */
    private function getTotalPasienPerProgramByPuskesmas($puskesmasId, $tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $result = [];
        
        foreach ($jenisProgram as $jp) {
            $totalPasien = Pasien::where('puskesmas_id', $puskesmasId)
                ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $bulan, $jp) {
                    $q->where('tahun_program_id', $tahunProgramId)
                      ->whereHas('pemeriksaanStatus', function($sq) use($jp) {
                          $sq->where('jenis_program_id', $jp->id);
                      })
                      ->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
                })->count();
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'total' => $totalPasien,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get pasien terkendali per program by puskesmas
     */
    private function getPasienTerkendaliPerProgramByPuskesmas($puskesmasId, $tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $statusTerkendali = RefStatus::where('kode', 'TERKENDALI')->first();
        $result = [];
        
        if (!$statusTerkendali) {
            return $result;
        }
        
        foreach ($jenisProgram as $jp) {
            // Menggunakan data pemeriksaan
            $totalPasienTerkendali = Pasien::where('puskesmas_id', $puskesmasId)
                ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $bulan, $jp, $statusTerkendali) {
                    $q->where('tahun_program_id', $tahunProgramId)
                      ->whereHas('pemeriksaanStatus', function($sq) use($jp, $statusTerkendali) {
                          $sq->where('jenis_program_id', $jp->id)
                             ->where('status_id', $statusTerkendali->id);
                      })
                      ->whereRaw('MONTH(tgl_periksa) <= ?', [$bulan]);
                })->count();
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'total' => $totalPasienTerkendali,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get pasien rutin per program by puskesmas
     */
    private function getPasienRutinPerProgramByPuskesmas($puskesmasId, $tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $statusRutin = RefStatus::where('kode', 'RUTIN')->first();
        $result = [];
        
        if (!$statusRutin) {
            return $result;
        }
        
        foreach ($jenisProgram as $jp) {
            // Untuk sementara, gunakan jumlah pasien yang melakukan pemeriksaan di setiap bulan
            $totalPasienRutin = Pasien::where('puskesmas_id', $puskesmasId)
                ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $bulan, $jp) {
                    $q->where('tahun_program_id', $tahunProgramId)
                      ->whereHas('pemeriksaanStatus', function($sq) use($jp) {
                          $sq->where('jenis_program_id', $jp->id);
                      })
                      ->whereRaw('MONTH(tgl_periksa) = ?', [$bulan]);
                })->count();
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'total' => $totalPasienRutin,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get sasaran puskesmas
     */
    private function getSasaranPuskesmas($puskesmasId, $tahunProgramId)
    {
        $sasaran = SasaranPuskesmas::with(['jenisProgram'])
            ->whereHas('sasaranTahunan', function($q) use($tahunProgramId) {
                $q->where('tahun_program_id', $tahunProgramId);
            })
            ->where('puskesmas_id', $puskesmasId)
            ->get();
            
        $result = [];
        
        foreach ($sasaran as $s) {
            $result[] = [
                'jenis_program' => $s->jenisProgram->nama,
                'kode' => $s->jenisProgram->kode,
                'parameter' => $s->parameter,
                'nilai' => $s->nilai,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get pencapaian puskesmas by bulan
     */
    private function getPencapaianPuskesmasByBulan($puskesmasId, $tahunProgramId, $bulan)
    {
        $jenisProgram = RefJenisProgram::all();
        $result = [];
        
        foreach ($jenisProgram as $jp) {
            $pencapaian = PencapaianBulanan::where('tahun_program_id', $tahunProgramId)
                ->where('puskesmas_id', $puskesmasId)
                ->where('jenis_program_id', $jp->id)
                ->where('bulan', $bulan)
                ->first();
                
            $sasaran = SasaranPuskesmas::whereHas('sasaranTahunan', function($q) use($tahunProgramId) {
                $q->where('tahun_program_id', $tahunProgramId);
            })
            ->where('puskesmas_id', $puskesmasId)
            ->where('jenis_program_id', $jp->id)
            ->first();
            
            $result[] = [
                'jenis_program' => $jp->nama,
                'kode' => $jp->kode,
                'nilai' => $pencapaian ? $pencapaian->nilai : 0,
                'sasaran' => $sasaran ? $sasaran->nilai : 0,
                'persentase' => $sasaran && $sasaran->nilai > 0 && $pencapaian
                    ? round(($pencapaian->nilai / $sasaran->nilai) * 100, 2)
                    : 0,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get status laporan by puskesmas
     */
    private function getStatusLaporanByPuskesmas($puskesmasId, $tahunProgramId, $bulan)
    {
        $laporan = LaporanBulanan::with(['status'])
            ->where('tahun_program_id', $tahunProgramId)
            ->where('puskesmas_id', $puskesmasId)
            ->where('bulan', $bulan)
            ->first();
            
        if (!$laporan) {
            return [
                'status' => 'Belum Ada',
                'kode_status' => 'NONE',
                'tanggal_submit' => null,
                'tanggal_approve' => null,
            ];
        }
        
        return [
            'status' => $laporan->status->nama,
            'kode_status' => $laporan->status->kode,
            'tanggal_submit' => $laporan->submitted_at ? $laporan->submitted_at->format('Y-m-d H:i:s') : null,
            'tanggal_approve' => $laporan->approved_at ? $laporan->approved_at->format('Y-m-d H:i:s') : null,
        ];
    }
    
    /**
     * Get data per bulan by puskesmas
     */
    private function getDataPerBulanByPuskesmas($puskesmasId, $tahunProgramId)
    {
        $jenisProgram = RefJenisProgram::all();
        $bulanData = [];
        $currentMonth = (int) date('m');
        
        // Hanya tampilkan data sampai bulan saat ini
        for ($i = 1; $i <= $currentMonth; $i++) {
            $dataProgram = [];
            
            foreach ($jenisProgram as $jp) {
                $totalPasien = Pasien::where('puskesmas_id', $puskesmasId)
                    ->whereHas('pemeriksaan', function($q) use($tahunProgramId, $i, $jp) {
                        $q->where('tahun_program_id', $tahunProgramId)
                          ->whereHas('pemeriksaanStatus', function($sq) use($jp) {
                              $sq->where('jenis_program_id', $jp->id);
                          })
                          ->whereRaw('MONTH(tgl_periksa) = ?', [$i]);
                    })->count();
                
                $dataProgram[] = [
                    'jenis_program' => $jp->nama,
                    'kode' => $jp->kode,
                    'total' => $totalPasien,
                ];
            }
            
            $bulanData[] = [
                'bulan' => $i,
                'nama_bulan' => $this->getNamaBulan($i),
                'data' => $dataProgram,
            ];
        }
        
        return $bulanData;
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