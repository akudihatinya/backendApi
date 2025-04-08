<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PencapaianBulanan;
use App\Models\SasaranPuskesmas;
use App\Models\Puskesmas;
use App\Models\TahunProgram;
use App\Models\RefJenisProgram;
use Illuminate\Support\Facades\DB;

class PencapaianController extends Controller
{
    /**
     * Get pencapaian by puskesmas
     *
     * @param int $puskesmas_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByPuskesmas($puskesmas_id)
    {
        $puskesmas = Puskesmas::findOrFail($puskesmas_id);
        
        // Get active tahun program
        $tahunProgram = TahunProgram::where('is_active', true)->first();
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada program aktif saat ini'
            ], 404);
        }
        
        $pencapaian = PencapaianBulanan::with(['jenisProgram'])
            ->where('puskesmas_id', $puskesmas_id)
            ->where('tahun_program_id', $tahunProgram->id)
            ->orderBy('bulan')
            ->get();
            
        $sasaran = SasaranPuskesmas::with(['jenisProgram'])
            ->whereHas('sasaranTahunan', function($query) use ($tahunProgram) {
                $query->where('tahun_program_id', $tahunProgram->id);
            })
            ->where('puskesmas_id', $puskesmas_id)
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'puskesmas' => $puskesmas->nama,
                'tahun_program' => $tahunProgram->tahun,
                'sasaran' => $sasaran,
                'pencapaian' => $pencapaian
            ]
        ]);
    }
    
    /**
     * Get pencapaian by month
     *
     * @param int $bulan
     * @param int $tahun_program_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByMonth($bulan, $tahun_program_id)
    {
        $tahunProgram = TahunProgram::findOrFail($tahun_program_id);
        
        $pencapaian = PencapaianBulanan::with(['jenisProgram', 'puskesmas'])
            ->where('bulan', $bulan)
            ->where('tahun_program_id', $tahun_program_id)
            ->get();
            
        // Group by puskesmas
        $result = $pencapaian->groupBy('puskesmas_id')
            ->map(function($items, $puskesmas_id) {
                $puskesmas = Puskesmas::find($puskesmas_id);
                
                return [
                    'puskesmas' => $puskesmas->nama,
                    'data' => $items->groupBy('jenis_program_id')
                        ->map(function($programItems, $jenis_program_id) {
                            $jenisProgram = RefJenisProgram::find($jenis_program_id);
                            
                            return [
                                'program' => $jenisProgram->nama,
                                'items' => $programItems
                            ];
                        })
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => [
                'bulan' => $bulan,
                'tahun_program' => $tahunProgram->tahun,
                'pencapaian' => $result
            ]
        ]);
    }
    
    /**
     * Get chart data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChartData(Request $request)
    {
        $request->validate([
            'tahun_program_id' => 'required|exists:tahun_program,id',
            'jenis_program_id' => 'required|exists:ref_jenis_program,id',
            'parameter' => 'required|string',
        ]);
        
        $tahunProgramId = $request->tahun_program_id;
        $jenisProgramId = $request->jenis_program_id;
        $parameter = $request->parameter;
        $puskesmasId = $request->puskesmas_id;
        
        $query = PencapaianBulanan::with(['puskesmas'])
            ->where('tahun_program_id', $tahunProgramId)
            ->where('jenis_program_id', $jenisProgramId)
            ->where('parameter', $parameter)
            ->orderBy('bulan');
            
        if ($puskesmasId) {
            $query->where('puskesmas_id', $puskesmasId);
        }
        
        $pencapaian = $query->get();
        
        // Get sasaran data
        $sasaranQuery = SasaranPuskesmas::with(['puskesmas'])
            ->whereHas('sasaranTahunan', function($q) use ($tahunProgramId) {
                $q->where('tahun_program_id', $tahunProgramId);
            })
            ->where('jenis_program_id', $jenisProgramId)
            ->where('parameter', $parameter);
            
        if ($puskesmasId) {
            $sasaranQuery->where('puskesmas_id', $puskesmasId);
        }
        
        $sasaran = $sasaranQuery->get();
        
        // Format data for chart
        $chartData = [];
        
        // Group by puskesmas
        $pencapaianByPuskesmas = $pencapaian->groupBy('puskesmas_id');
        
        foreach ($pencapaianByPuskesmas as $puskesmasId => $items) {
            $puskesmas = $items->first()->puskesmas;
            $sasaranValue = $sasaran->where('puskesmas_id', $puskesmasId)->first()->nilai ?? 0;
            
            $monthlyData = [];
            for ($i = 1; $i <= 12; $i++) {
                $item = $items->where('bulan', $i)->first();
                $monthlyData[] = [
                    'bulan' => $i,
                    'nama_bulan' => $item ? $item->namaBulan : null,
                    'nilai' => $item ? $item->nilai : 0,
                    'persentase' => $item && $sasaranValue > 0 ? round(($item->nilai / $sasaranValue) * 100, 2) : 0,
                ];
            }
            
            $chartData[] = [
                'puskesmas' => $puskesmas->nama,
                'sasaran' => $sasaranValue,
                'data' => $monthlyData
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $chartData
        ]);
    }
}