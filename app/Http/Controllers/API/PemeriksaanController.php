<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Pemeriksaan;
use App\Models\PemeriksaanParam;
use App\Models\PemeriksaanStatus;
use App\Models\Pasien;
use App\Models\RefJenisProgram;
use App\Models\RefStatus;
use App\Models\TahunProgram;
use App\Traits\AnalisisHTDM;

class PemeriksaanController extends Controller
{
    use AnalisisHTDM;
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Pemeriksaan::with(['pasien', 'pemeriksaanParam', 'pemeriksaanStatus', 'pemeriksaanStatus.jenisProgram', 'pemeriksaanStatus.status']);
        
        // Filter berdasarkan puskesmas jika user dari puskesmas
        if ($request->user() && !$request->user()->is_admin && !$request->user()->is_dinas) {
            $pasienIds = Pasien::where('puskesmas_id', $request->user()->puskesmas_id)->pluck('id');
            $query->whereIn('pasien_id', $pasienIds);
        }
        
        // Filter berdasarkan jenis program
        if ($request->has('jenis_program') && $request->jenis_program) {
            $jenisProgram = RefJenisProgram::where('kode', $request->jenis_program)->first();
            if ($jenisProgram) {
                $query->whereHas('pemeriksaanStatus', function ($q) use ($jenisProgram) {
                    $q->where('jenis_program_id', $jenisProgram->id);
                });
            }
        }
        
        // Filter berdasarkan tahun program
        if ($request->has('tahun_program_id') && $request->tahun_program_id) {
            $query->where('tahun_program_id', $request->tahun_program_id);
        } else {
            // Default gunakan tahun program aktif
            $tahunAktif = TahunProgram::where('is_active', true)->first();
            if ($tahunAktif) {
                $query->where('tahun_program_id', $tahunAktif->id);
            }
        }
        
        // Filter berdasarkan pasien
        if ($request->has('pasien_id') && $request->pasien_id) {
            $query->where('pasien_id', $request->pasien_id);
        }
        
        // Filter berdasarkan tanggal
        if ($request->has('start_date') && $request->start_date) {
            $query->where('tgl_periksa', '>=', $request->start_date);
        }
        
        if ($request->has('end_date') && $request->end_date) {
            $query->where('tgl_periksa', '<=', $request->end_date);
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $pemeriksaan = $query->orderBy('tgl_periksa', 'desc')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $pemeriksaan
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'pasien_id' => 'required|exists:pasien,id',
            'tgl_periksa' => 'required|date',
            'jenis_program' => 'required|in:HT,DM',
            'keterangan' => 'nullable|string',
            'parameter' => 'required|array',
            'parameter.*.nama' => 'required|string',
            'parameter.*.nilai' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Cek tahun program aktif
        $tahunProgram = TahunProgram::where('is_active', true)->first();
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tahun program aktif',
            ], 400);
        }
        
        // Cek jenis program
        $jenisProgram = RefJenisProgram::where('kode', $request->jenis_program)->first();
        if (!$jenisProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis program tidak ditemukan',
            ], 400);
        }
        
        // Mulai transaksi DB
        DB::beginTransaction();
        
        try {
            // Buat pemeriksaan
            $pemeriksaan = Pemeriksaan::create([
                'tahun_program_id' => $tahunProgram->id,
                'pasien_id' => $request->pasien_id,
                'petugas_id' => $request->user()->id,
                'tgl_periksa' => $request->tgl_periksa,
                'keterangan' => $request->keterangan,
            ]);
            
            // Simpan parameter
            foreach ($request->parameter as $param) {
                PemeriksaanParam::create([
                    'pemeriksaan_id' => $pemeriksaan->id,
                    'jenis_program_id' => $jenisProgram->id,
                    'nama_parameter' => $param['nama'],
                    'nilai' => $param['nilai'],
                ]);
            }
            
            // Tentukan status berdasarkan analisis
            $statusId = $this->tentukanStatus($request->pasien_id, $jenisProgram->kode, $request->parameter);
            
            // Simpan status
            PemeriksaanStatus::create([
                'pemeriksaan_id' => $pemeriksaan->id,
                'jenis_program_id' => $jenisProgram->id,
                'status_id' => $statusId,
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Pemeriksaan berhasil disimpan',
                'data' => $pemeriksaan->load(['pemeriksaanParam', 'pemeriksaanStatus'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pemeriksaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pemeriksaan = Pemeriksaan::with(['pasien', 'pemeriksaanParam', 'pemeriksaanStatus', 'pemeriksaanStatus.jenisProgram', 'pemeriksaanStatus.status'])
            ->find($id);
            
        if (!$pemeriksaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pemeriksaan tidak ditemukan',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $pemeriksaan
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'tgl_periksa' => 'required|date',
            'keterangan' => 'nullable|string',
            'parameter' => 'required|array',
            'parameter.*.id' => 'nullable|exists:pemeriksaan_param,id',
            'parameter.*.nama' => 'required|string',
            'parameter.*.nilai' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Cek pemeriksaan
        $pemeriksaan = Pemeriksaan::find($id);
        if (!$pemeriksaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pemeriksaan tidak ditemukan',
            ], 404);
        }
        
        // Cek jenis program
        $jenisProgramId = $pemeriksaan->pemeriksaanStatus->first()->jenis_program_id;
        $jenisProgram = RefJenisProgram::find($jenisProgramId);
        
        // Mulai transaksi DB
        DB::beginTransaction();
        
        try {
            // Update pemeriksaan
            $pemeriksaan->update([
                'tgl_periksa' => $request->tgl_periksa,
                'keterangan' => $request->keterangan,
            ]);
            
            // Update parameter
            foreach ($request->parameter as $param) {
                if (isset($param['id'])) {
                    // Update parameter yang sudah ada
                    PemeriksaanParam::where('id', $param['id'])
                        ->update([
                            'nama_parameter' => $param['nama'],
                            'nilai' => $param['nilai'],
                        ]);
                } else {
                    // Buat parameter baru
                    PemeriksaanParam::create([
                        'pemeriksaan_id' => $pemeriksaan->id,
                        'jenis_program_id' => $jenisProgramId,
                        'nama_parameter' => $param['nama'],
                        'nilai' => $param['nilai'],
                    ]);
                }
            }
            
            // Tentukan status berdasarkan analisis
            $statusId = $this->tentukanStatus($pemeriksaan->pasien_id, $jenisProgram->kode, $request->parameter);
            
            // Update status
            PemeriksaanStatus::where('pemeriksaan_id', $pemeriksaan->id)
                ->update([
                    'status_id' => $statusId,
                ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Pemeriksaan berhasil diupdate',
                'data' => $pemeriksaan->fresh()->load(['pemeriksaanParam', 'pemeriksaanStatus'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate pemeriksaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pemeriksaan = Pemeriksaan::find($id);
        
        if (!$pemeriksaan) {
            return response()->json([
                'success' => false,
                'message' => 'Pemeriksaan tidak ditemukan',
            ], 404);
        }
        
        // Hapus pemeriksaan beserta relasinya (akan terhapus cascade)
        $pemeriksaan->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Pemeriksaan berhasil dihapus',
        ]);
    }
    
    /**
     * Tentukan status pasien berdasarkan hasil pemeriksaan
     */
    private function tentukanStatus($pasienId, $jenisProgramKode, $parameter)
    {
        $statusTerkendali = RefStatus::where('kode', 'TERKENDALI')->first();
        $statusTidakTerkendali = RefStatus::where('kode', 'TIDAK_TERKENDALI')->first();
        
        if (!$statusTerkendali || !$statusTidakTerkendali) {
            throw new \Exception('Status referensi tidak ditemukan');
        }
        
        // Cek status berdasarkan jenis program
        if ($jenisProgramKode === 'HT') {
            // Cek jika sistole dan diastole dalam batas normal
            $sistole = null;
            $diastole = null;
            
            foreach ($parameter as $param) {
                if ($param['nama'] === 'sistole') {
                    $sistole = $param['nilai'];
                } elseif ($param['nama'] === 'diastole') {
                    $diastole = $param['nilai'];
                }
            }
            
            if ($sistole !== null && $diastole !== null) {
                // Terkendali jika 120-139/80-89 mmHg
                if ($sistole >= 120 && $sistole <= 139 && $diastole >= 80 && $diastole <= 89) {
                    return $statusTerkendali->id;
                }
            }
            
            return $statusTidakTerkendali->id;
        } elseif ($jenisProgramKode === 'DM') {
            // Cek hasil pemeriksaan DM
            foreach ($parameter as $param) {
                if ($param['nama'] === 'hba1c' && $param['nilai'] < 7) {
                    return $statusTerkendali->id;
                } elseif ($param['nama'] === 'gdp' && $param['nilai'] < 126) {
                    return $statusTerkendali->id;
                } elseif ($param['nama'] === 'gd2pp' && $param['nilai'] < 200) {
                    return $statusTerkendali->id;
                }
            }
            
            return $statusTidakTerkendali->id;
        }
        
        // Default tidak terkendali
        return $statusTidakTerkendali->id;
    }
}