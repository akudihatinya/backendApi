<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\SasaranTahunan;
use App\Models\SasaranPuskesmas;
use App\Models\TahunProgram;
use App\Models\Dinas;
use App\Models\Puskesmas;
use App\Models\RefStatus;
use App\Models\RefJenisProgram;

class SasaranController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SasaranTahunan::with(['dinas', 'status', 'tahunProgram']);
        
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
        
        // Filter berdasarkan dinas
        if ($request->has('dinas_id') && $request->dinas_id) {
            $query->where('dinas_id', $request->dinas_id);
        }
        
        // Filter berdasarkan status
        if ($request->has('status_id') && $request->status_id) {
            $query->where('status_id', $request->status_id);
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $sasaran = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $sasaran
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'tahun_program_id' => 'required|exists:tahun_program,id',
            'dinas_id' => 'required|exists:dinas,id',
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string',
            'status_id' => 'required|exists:ref_status,id',
            'sasaran_puskesmas' => 'required|array',
            'sasaran_puskesmas.*.puskesmas_id' => 'required|exists:puskesmas,id',
            'sasaran_puskesmas.*.jenis_program_id' => 'required|exists:ref_jenis_program,id',
            'sasaran_puskesmas.*.parameter' => 'required|string|max:30',
            'sasaran_puskesmas.*.nilai' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Cek jika sudah ada sasaran untuk tahun program dan dinas yang sama
        $existingSasaran = SasaranTahunan::where('tahun_program_id', $request->tahun_program_id)
            ->where('dinas_id', $request->dinas_id)
            ->where('nama', $request->nama)
            ->first();
            
        if ($existingSasaran) {
            return response()->json([
                'success' => false,
                'message' => 'Sasaran dengan nama dan tahun program yang sama sudah ada',
            ], 400);
        }
        
        // Mulai transaksi DB
        DB::beginTransaction();
        
        try {
            // Buat sasaran tahunan
            $sasaran = SasaranTahunan::create([
                'tahun_program_id' => $request->tahun_program_id,
                'dinas_id' => $request->dinas_id,
                'nama' => $request->nama,
                'keterangan' => $request->keterangan,
                'status_id' => $request->status_id,
            ]);
            
            // Simpan sasaran puskesmas
            foreach ($request->sasaran_puskesmas as $sp) {
                SasaranPuskesmas::create([
                    'sasaran_tahunan_id' => $sasaran->id,
                    'puskesmas_id' => $sp['puskesmas_id'],
                    'jenis_program_id' => $sp['jenis_program_id'],
                    'parameter' => $sp['parameter'],
                    'nilai' => $sp['nilai'],
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Sasaran berhasil disimpan',
                'data' => $sasaran->load(['sasaranPuskesmas', 'sasaranPuskesmas.puskesmas', 'sasaranPuskesmas.jenisProgram'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan sasaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $sasaran = SasaranTahunan::with(['dinas', 'status', 'tahunProgram', 'sasaranPuskesmas', 'sasaranPuskesmas.puskesmas', 'sasaranPuskesmas.jenisProgram'])
            ->find($id);
            
        if (!$sasaran) {
            return response()->json([
                'success' => false,
                'message' => 'Sasaran tidak ditemukan',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $sasaran
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'keterangan' => 'nullable|string',
            'status_id' => 'required|exists:ref_status,id',
            'sasaran_puskesmas' => 'required|array',
            'sasaran_puskesmas.*.id' => 'nullable|exists:sasaran_puskesmas,id',
            'sasaran_puskesmas.*.puskesmas_id' => 'required|exists:puskesmas,id',
            'sasaran_puskesmas.*.jenis_program_id' => 'required|exists:ref_jenis_program,id',
            'sasaran_puskesmas.*.parameter' => 'required|string|max:30',
            'sasaran_puskesmas.*.nilai' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $sasaran = SasaranTahunan::find($id);
        
        if (!$sasaran) {
            return response()->json([
                'success' => false,
                'message' => 'Sasaran tidak ditemukan',
            ], 404);
        }
        
        // Cek jika nama berubah, pastikan tidak konflik dengan yang sudah ada
        if ($sasaran->nama != $request->nama) {
            $existingSasaran = SasaranTahunan::where('tahun_program_id', $sasaran->tahun_program_id)
                ->where('dinas_id', $sasaran->dinas_id)
                ->where('nama', $request->nama)
                ->where('id', '!=', $sasaran->id)
                ->first();
                
            if ($existingSasaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sasaran dengan nama dan tahun program yang sama sudah ada',
                ], 400);
            }
        }
        
        // Mulai transaksi DB
        DB::beginTransaction();
        
        try {
            // Update sasaran tahunan
            $sasaran->update([
                'nama' => $request->nama,
                'keterangan' => $request->keterangan,
                'status_id' => $request->status_id,
            ]);
            
            // Update sasaran puskesmas
            $existingIds = $sasaran->sasaranPuskesmas->pluck('id')->toArray();
            $newIds = [];
            
            foreach ($request->sasaran_puskesmas as $sp) {
                if (isset($sp['id'])) {
                    // Update yang sudah ada
                    SasaranPuskesmas::where('id', $sp['id'])
                        ->update([
                            'puskesmas_id' => $sp['puskesmas_id'],
                            'jenis_program_id' => $sp['jenis_program_id'],
                            'parameter' => $sp['parameter'],
                            'nilai' => $sp['nilai'],
                        ]);
                        
                    $newIds[] = $sp['id'];
                } else {
                    // Buat baru
                    $newSp = SasaranPuskesmas::create([
                        'sasaran_tahunan_id' => $sasaran->id,
                        'puskesmas_id' => $sp['puskesmas_id'],
                        'jenis_program_id' => $sp['jenis_program_id'],
                        'parameter' => $sp['parameter'],
                        'nilai' => $sp['nilai'],
                    ]);
                    
                    $newIds[] = $newSp->id;
                }
            }
            
            // Hapus yang tidak ada dalam request
            $idsToDelete = array_diff($existingIds, $newIds);
            if (!empty($idsToDelete)) {
                SasaranPuskesmas::whereIn('id', $idsToDelete)->delete();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Sasaran berhasil diupdate',
                'data' => $sasaran->fresh()->load(['sasaranPuskesmas', 'sasaranPuskesmas.puskesmas', 'sasaranPuskesmas.jenisProgram'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate sasaran',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sasaran = SasaranTahunan::find($id);
        
        if (!$sasaran) {
            return response()->json([
                'success' => false,
                'message' => 'Sasaran tidak ditemukan',
            ], 404);
        }
        
        // Hapus sasaran beserta relasinya (akan terhapus cascade)
        $sasaran->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Sasaran berhasil dihapus',
        ]);
    }
    
    /**
     * Get sasaran by puskesmas
     */
    public function sasaranByPuskesmas(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'puskesmas_id' => 'required|exists:puskesmas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
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
        
        // Ambil sasaran puskesmas
        $query = SasaranPuskesmas::with(['jenisProgram', 'sasaranTahunan'])
            ->whereHas('sasaranTahunan', function($q) use($tahunProgram) {
                $q->where('tahun_program_id', $tahunProgram->id);
            })
            ->where('puskesmas_id', $request->puskesmas_id);
            
        // Filter jenis program
        if ($request->has('jenis_program_id') && $request->jenis_program_id) {
            $query->where('jenis_program_id', $request->jenis_program_id);
        }
        
        $sasaran = $query->get();
        
        // Jika tidak ada sasaran untuk puskesmas ini
        if ($sasaran->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada sasaran untuk puskesmas ini',
                'data' => []
            ]);
        }
        
        return response()->json([
            'success' => true,
            'data' => $sasaran
        ]);
    }
    
    /**
     * Generate template sasaran puskesmas
     */
    public function generateTemplate(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'tahun_program_id' => 'required|exists:tahun_program,id',
            'dinas_id' => 'required|exists:dinas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Ambil semua puskesmas
        $puskesmas = Puskesmas::where('dinas_id', $request->dinas_id)->get();
        
        // Ambil semua jenis program
        $jenisProgram = RefJenisProgram::all();
        
        // Generate template
        $template = [];
        
        foreach ($puskesmas as $p) {
            foreach ($jenisProgram as $jp) {
                $template[] = [
                    'puskesmas_id' => $p->id,
                    'puskesmas' => $p->nama,
                    'jenis_program_id' => $jp->id,
                    'jenis_program' => $jp->nama,
                    'parameter' => 'target_pasien',
                    'nilai' => 0,
                ];
            }
        }
        
        // Ambil status default (ACTIVE)
        $statusActive = RefStatus::where('kode', 'ACTIVE')->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'tahun_program_id' => $request->tahun_program_id,
                'dinas_id' => $request->dinas_id,
                'status_id' => $statusActive ? $statusActive->id : null,
                'template' => $template
            ]
        ]);
    }
}