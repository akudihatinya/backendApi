<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RekapDinas;
use App\Models\RekapDetail;
use App\Models\Dinas;
use App\Models\Puskesmas;
use App\Models\TahunProgram;
use App\Models\RefJenisProgram;
use Illuminate\Support\Facades\DB;

class RekapController extends Controller
{
    /**
     * Get rekap by dinas
     *
     * @param int $tahun_program_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByDinas($tahun_program_id)
    {
        $tahunProgram = TahunProgram::findOrFail($tahun_program_id);
        
        // Get user's dinas
        $user = request()->user();
        $dinas = null;
        
        if ($user->hasRole('dinas')) {
            // Get dinas associated with the user
            $dinas = Dinas::where('kode', $user->nama_puskesmas)->first();
            
            if (!$dinas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dinas tidak ditemukan'
                ], 404);
            }
        } else if ($user->hasRole('admin')) {
            // Admin can see all dinas, get the first one
            $dinas = Dinas::first();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $rekap = RekapDinas::with(['status', 'rekapDetail.puskesmas', 'rekapDetail.jenisProgram', 'rekapDetail.status'])
            ->where('dinas_id', $dinas->id)
            ->where('tahun_program_id', $tahun_program_id)
            ->orderBy('bulan', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'dinas' => $dinas->nama,
                'tahun_program' => $tahunProgram->tahun,
                'rekap' => $rekap
            ]
        ]);
    }
    
    /**
     * Get rekap by puskesmas
     *
     * @param int $puskesmas_id
     * @param int $tahun_program_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByPuskesmas($puskesmas_id, $tahun_program_id)
    {
        $puskesmas = Puskesmas::findOrFail($puskesmas_id);
        $tahunProgram = TahunProgram::findOrFail($tahun_program_id);
        
        $rekap = RekapDetail::with(['rekapDinas', 'jenisProgram', 'jenisKelamin', 'status'])
            ->where('puskesmas_id', $puskesmas_id)
            ->whereHas('rekapDinas', function($query) use ($tahun_program_id) {
                $query->where('tahun_program_id', $tahun_program_id);
            })
            ->get();
            
        // Group by report month
        $result = $rekap->groupBy(function($item) {
                return $item->rekapDinas->bulan;
            })
            ->map(function($items, $bulan) {
                $rekapDinas = $items->first()->rekapDinas;
                
                return [
                    'bulan' => $bulan,
                    'nama_bulan' => $rekapDinas->namaBulan,
                    'status' => $rekapDinas->status->nama,
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
                'puskesmas' => $puskesmas->nama,
                'tahun_program' => $tahunProgram->tahun,
                'rekap' => $result
            ]
        ]);
    }
    
    /**
     * Get rekap by program
     *
     * @param int $jenis_program_id
     * @param int $tahun_program_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByProgram($jenis_program_id, $tahun_program_id)
    {
        $jenisProgram = RefJenisProgram::findOrFail($jenis_program_id);
        $tahunProgram = TahunProgram::findOrFail($tahun_program_id);
        
        // Get user's dinas
        $user = request()->user();
        $dinasId = null;
        
        if ($user->hasRole('dinas')) {
            // Get dinas associated with the user
            $dinas = Dinas::where('kode', $user->nama_puskesmas)->first();
            
            if (!$dinas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dinas tidak ditemukan'
                ], 404);
            }
            $dinasId = $dinas->id;
        }
        
        $query = RekapDetail::with(['rekapDinas', 'puskesmas', 'jenisKelamin', 'status'])
            ->where('jenis_program_id', $jenis_program_id)
            ->whereHas('rekapDinas', function($query) use ($tahun_program_id, $dinasId) {
                $query->where('tahun_program_id', $tahun_program_id);
                
                if ($dinasId) {
                    $query->where('dinas_id', $dinasId);
                }
            });
            
        $rekap = $query->get();
        
        // Group by report month and puskesmas
        $result = $rekap->groupBy(function($item) {
                return $item->rekapDinas->bulan;
            })
            ->map(function($items, $bulan) {
                $rekapDinas = $items->first()->rekapDinas;
                
                return [
                    'bulan' => $bulan,
                    'nama_bulan' => $rekapDinas->namaBulan,
                    'data' => $items->groupBy('puskesmas_id')
                        ->map(function($puskesmasItems, $puskesmas_id) {
                            $puskesmas = Puskesmas::find($puskesmas_id);
                            
                            return [
                                'puskesmas' => $puskesmas->nama,
                                'items' => $puskesmasItems->groupBy('status_id')
                                    ->map(function($statusItems, $status_id) {
                                        return [
                                            'status' => $statusItems->first()->status->nama,
                                            'jumlah' => $statusItems->sum('jumlah')
                                        ];
                                    })
                            ];
                        })
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => [
                'program' => $jenisProgram->nama,
                'tahun_program' => $tahunProgram->tahun,
                'rekap' => $result
            ]
        ]);
    }
}