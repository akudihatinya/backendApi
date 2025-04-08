<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PasienExport;
use App\Exports\PemeriksaanExport;
use App\Exports\LaporanBulananExport;
use App\Exports\RekapTahunanExport;
use App\Models\TahunProgram;
use App\Models\Puskesmas;

class ExportController extends Controller
{
    /**
     * Export data pasien ke Excel
     */
    public function exportPasien(Request $request)
    {
        // Validasi user puskesmas hanya bisa export data puskesmasnya
        $puskesmasId = null;
        
        if ($request->user() && !$request->user()->is_admin && !$request->user()->is_dinas) {
            $puskesmas = Puskesmas::where('nama', $request->user()->nama_puskesmas)->first();
            if ($puskesmas) {
                $puskesmasId = $puskesmas->id;
            }
        } else if ($request->has('puskesmas_id') && $request->puskesmas_id) {
            $puskesmasId = $request->puskesmas_id;
        }
        
        // Generate filename
        $filename = 'daftar_pasien';
        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            if ($puskesmas) {
                $filename .= '_' . str_replace(' ', '_', strtolower($puskesmas->nama));
            }
        }
        $filename .= '_' . date('Ymd') . '.xlsx';
        
        return Excel::download(new PasienExport($puskesmasId), $filename);
    }
    
    /**
     * Export data pemeriksaan ke Excel
     */
    public function exportPemeriksaan(Request $request)
    {
        // Validasi input
        $request->validate([
            'tahun_program_id' => 'required|exists:tahun_program,id',
            'puskesmas_id' => 'nullable|exists:puskesmas,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        
        // Validasi user puskesmas hanya bisa export data puskesmasnya
        $puskesmasId = null;
        
        if ($request->user() && !$request->user()->is_admin && !$request->user()->is_dinas) {
            $puskesmas = Puskesmas::where('nama', $request->user()->nama_puskesmas)->first();
            if ($puskesmas) {
                $puskesmasId = $puskesmas->id;
            }
        } else if ($request->has('puskesmas_id') && $request->puskesmas_id) {
            $puskesmasId = $request->puskesmas_id;
        }
        
        // Get tahun program
        $tahunProgram = TahunProgram::find($request->tahun_program_id);
        $tahun = $tahunProgram ? $tahunProgram->tahun : date('Y');
        
        // Generate filename
        $filename = 'data_pemeriksaan_' . $tahun;
        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            if ($puskesmas) {
                $filename .= '_' . str_replace(' ', '_', strtolower($puskesmas->nama));
            }
        }
        $filename .= '_' . date('Ymd') . '.xlsx';
        
        return Excel::download(new PemeriksaanExport(
            $request->tahun_program_id,
            $puskesmasId,
            $request->start_date,
            $request->end_date
        ), $filename);
    }
    
    /**
     * Export laporan bulanan ke Excel
     */
    public function exportLaporanBulanan(Request $request)
    {
        // Validasi input
        $request->validate([
            'tahun_program_id' => 'required|exists:tahun_program,id',
            'bulan' => 'required|integer|min:1|max:12',
            'puskesmas_id' => 'nullable|exists:puskesmas,id',
        ]);
        
        // Validasi user puskesmas hanya bisa export data puskesmasnya
        $puskesmasId = null;
        
        if ($request->user() && !$request->user()->is_admin && !$request->user()->is_dinas) {
            $puskesmas = Puskesmas::where('nama', $request->user()->nama_puskesmas)->first();
            if ($puskesmas) {
                $puskesmasId = $puskesmas->id;
            }
        } else if ($request->has('puskesmas_id') && $request->puskesmas_id) {
            $puskesmasId = $request->puskesmas_id;
        }
        
        // Get tahun program
        $tahunProgram = TahunProgram::find($request->tahun_program_id);
        $tahun = $tahunProgram ? $tahunProgram->tahun : date('Y');
        
        // Get nama bulan
        $namaBulan = $this->getNamaBulan($request->bulan);
        
        // Generate filename
        $filename = 'laporan_bulanan_' . strtolower($namaBulan) . '_' . $tahun;
        if ($puskesmasId) {
            $puskesmas = Puskesmas::find($puskesmasId);
            if ($puskesmas) {
                $filename .= '_' . str_replace(' ', '_', strtolower($puskesmas->nama));
            }
        }
        $filename .= '.xlsx';
        
        return Excel::download(new LaporanBulananExport(
            $request->tahun_program_id,
            $request->bulan,
            $puskesmasId
        ), $filename);
    }
    
    /**
     * Export rekap tahunan ke Excel
     */
    public function exportRekapTahunan(Request $request)
    {
        // Validasi input
        $request->validate([
            'tahun_program_id' => 'required|exists:tahun_program,id',
        ]);
        
        // Validasi akses (hanya admin dan dinas yang boleh)
        if ($request->user() && !$request->user()->is_admin && !$request->user()->is_dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengunduh rekap tahunan',
            ], 403);
        }
        
        // Get tahun program
        $tahunProgram = TahunProgram::find($request->tahun_program_id);
        $tahun = $tahunProgram ? $tahunProgram->tahun : date('Y');
        
        // Generate filename
        $filename = 'rekap_tahunan_' . $tahun . '.xlsx';
        
        return Excel::download(new RekapTahunanExport($request->tahun_program_id), $filename);
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