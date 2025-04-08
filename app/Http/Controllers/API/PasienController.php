<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Pasien;
use App\Models\Puskesmas;
use App\Models\RefJenisKelamin;
use App\Models\TahunProgram;
use App\Traits\AnalisisHTDM;

class PasienController extends Controller
{
    use AnalisisHTDM;
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Pasien::with(['puskesmas', 'jenisKelamin']);
        
        // Filter berdasarkan puskesmas jika user dari puskesmas
        if ($request->user() && !$request->user()->is_admin && !$request->user()->is_dinas) {
            $puskesmasId = Puskesmas::where('nama', $request->user()->nama_puskesmas)->value('id');
            if ($puskesmasId) {
                $query->where('puskesmas_id', $puskesmasId);
            }
        }
        
        // Filter berdasarkan puskesmas_id
        if ($request->has('puskesmas_id') && $request->puskesmas_id) {
            $query->where('puskesmas_id', $request->puskesmas_id);
        }
        
        // Filter berdasarkan jenis kelamin
        if ($request->has('jenis_kelamin_id') && $request->jenis_kelamin_id) {
            $query->where('jenis_kelamin_id', $request->jenis_kelamin_id);
        }
        
        // Filter berdasarkan nama, nik, atau no_bpjs
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('no_bpjs', 'like', "%{$search}%");
            });
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $pasien = $query->orderBy('nama')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $pasien
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'puskesmas_id' => 'required|exists:puskesmas,id',
            'nik' => 'nullable|string|max:16|unique:pasien,nik',
            'no_bpjs' => 'nullable|string|max:20|unique:pasien,no_bpjs',
            'nama' => 'required|string|max:100',
            'jenis_kelamin_id' => 'required|exists:ref_jenis_kelamin,id',
            'tgl_lahir' => 'required|date',
            'alamat' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Buat pasien baru
            $pasien = Pasien::create([
                'puskesmas_id' => $request->puskesmas_id,
                'nik' => $request->nik,
                'no_bpjs' => $request->no_bpjs,
                'nama' => $request->nama,
                'jenis_kelamin_id' => $request->jenis_kelamin_id,
                'tgl_lahir' => $request->tgl_lahir,
                'alamat' => $request->alamat,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Pasien berhasil ditambahkan',
                'data' => $pasien->load(['puskesmas', 'jenisKelamin'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pasien',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pasien = Pasien::with(['puskesmas', 'jenisKelamin'])->find($id);
        
        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'Pasien tidak ditemukan',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $pasien
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'puskesmas_id' => 'required|exists:puskesmas,id',
            'nik' => 'nullable|string|max:16|unique:pasien,nik,'.$id,
            'no_bpjs' => 'nullable|string|max:20|unique:pasien,no_bpjs,'.$id,
            'nama' => 'required|string|max:100',
            'jenis_kelamin_id' => 'required|exists:ref_jenis_kelamin,id',
            'tgl_lahir' => 'required|date',
            'alamat' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $pasien = Pasien::find($id);
        
        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'Pasien tidak ditemukan',
            ], 404);
        }
        
        try {
            // Update pasien
            $pasien->update([
                'puskesmas_id' => $request->puskesmas_id,
                'nik' => $request->nik,
                'no_bpjs' => $request->no_bpjs,
                'nama' => $request->nama,
                'jenis_kelamin_id' => $request->jenis_kelamin_id,
                'tgl_lahir' => $request->tgl_lahir,
                'alamat' => $request->alamat,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Pasien berhasil diupdate',
                'data' => $pasien->fresh()->load(['puskesmas', 'jenisKelamin'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate pasien',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pasien = Pasien::find($id);
        
        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'Pasien tidak ditemukan',
            ], 404);
        }
        
        // Cek jika pasien memiliki pemeriksaan
        if ($pasien->pemeriksaan()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Pasien tidak dapat dihapus karena memiliki data pemeriksaan',
            ], 400);
        }
        
        // Hapus pasien
        $pasien->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Pasien berhasil dihapus',
        ]);
    }
    
    /**
     * Get analisis status pasien
     */
    public function analisisStatus(string $id, Request $request)
    {
        $pasien = Pasien::find($id);
        
        if (!$pasien) {
            return response()->json([
                'success' => false,
                'message' => 'Pasien tidak ditemukan',
            ], 404);
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
        
        // Analisis status HT
        $statusHT = $this->analisaHT($pasien->id, $tahunProgram->id, $bulan);
        
        // Analisis status DM
        $statusDM = $this->analisaDM($pasien->id, $tahunProgram->id, $bulan);
        
        return response()->json([
            'success' => true,
            'data' => [
                'pasien' => $pasien->load(['puskesmas', 'jenisKelamin']),
                'tahun_program' => $tahunProgram,
                'bulan' => $bulan,
                'status_ht' => $statusHT,
                'status_dm' => $statusDM,
            ]
        ]);
    }
}