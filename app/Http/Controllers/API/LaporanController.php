<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\LaporanBulanan;
use App\Models\LaporanDetail;
use App\Models\RefStatus;
use App\Models\TahunProgram;
use App\Models\RefJenisProgram;
use App\Models\RefJenisKelamin;
use App\Models\Puskesmas;
use Carbon\Carbon;

class LaporanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LaporanBulanan::with(['puskesmas', 'status', 'petugas', 'laporanDetail']);
        
        // Filter berdasarkan puskesmas jika user dari puskesmas
        if ($request->user() && !$request->user()->is_admin && !$request->user()->is_dinas) {
            $puskesmasId = Puskesmas::where('nama', $request->user()->nama_puskesmas)->value('id');
            if ($puskesmasId) {
                $query->where('puskesmas_id', $puskesmasId);
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
        
        // Filter berdasarkan bulan
        if ($request->has('bulan') && $request->bulan) {
            $query->where('bulan', $request->bulan);
        }
        
        // Filter berdasarkan status
        if ($request->has('status_id') && $request->status_id) {
            $query->where('status_id', $request->status_id);
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $laporan = $query->orderBy('bulan', 'desc')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $laporan
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|min:1|max:12',
            'detail' => 'required|array',
            'detail.*.jenis_program_id' => 'required|exists:ref_jenis_program,id',
            'detail.*.jenis_kelamin_id' => 'required|exists:ref_jenis_kelamin,id',
            'detail.*.status_id' => 'required|exists:ref_status,id',
            'detail.*.jumlah' => 'required|integer|min:0',
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
        
        // Cek puskesmas user
        $puskesmasId = Puskesmas::where('nama', $request->user()->nama_puskesmas)->value('id');
        if (!$puskesmasId) {
            return response()->json([
                'success' => false,
                'message' => 'Puskesmas tidak ditemukan',
            ], 400);
        }
        
        // Cek apakah sudah ada laporan untuk bulan ini
        $existingLaporan = LaporanBulanan::where('tahun_program_id', $tahunProgram->id)
            ->where('puskesmas_id', $puskesmasId)
            ->where('bulan', $request->bulan)
            ->first();
            
        if ($existingLaporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan untuk bulan ini sudah ada',
            ], 400);
        }
        
        // Ambil status DRAFT
        $statusDraft = RefStatus::where('kode', 'DRAFT')->first();
        if (!$statusDraft) {
            return response()->json([
                'success' => false,
                'message' => 'Status Draft tidak ditemukan',
            ], 400);
        }
        
        // Mulai transaksi DB
        DB::beginTransaction();
        
        try {
            // Buat laporan
            $laporan = LaporanBulanan::create([
                'tahun_program_id' => $tahunProgram->id,
                'puskesmas_id' => $puskesmasId,
                'bulan' => $request->bulan,
                'status_id' => $statusDraft->id,
                'petugas_id' => $request->user()->id,
            ]);
            
            // Simpan detail laporan
            foreach ($request->detail as $detail) {
                LaporanDetail::create([
                    'laporan_id' => $laporan->id,
                    'jenis_program_id' => $detail['jenis_program_id'],
                    'jenis_kelamin_id' => $detail['jenis_kelamin_id'],
                    'status_id' => $detail['status_id'],
                    'jumlah' => $detail['jumlah'],
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil disimpan',
                'data' => $laporan->load('laporanDetail')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan laporan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $laporan = LaporanBulanan::with(['puskesmas', 'status', 'petugas', 'laporanDetail', 'laporanDetail.jenisProgram', 'laporanDetail.jenisKelamin', 'laporanDetail.status'])
            ->find($id);
            
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $laporan
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'detail' => 'required|array',
            'detail.*.id' => 'nullable|exists:laporan_detail,id',
            'detail.*.jenis_program_id' => 'required|exists:ref_jenis_program,id',
            'detail.*.jenis_kelamin_id' => 'required|exists:ref_jenis_kelamin,id',
            'detail.*.status_id' => 'required|exists:ref_status,id',
            'detail.*.jumlah' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Cek laporan
        $laporan = LaporanBulanan::find($id);
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }
        
        // Cek status laporan, hanya DRAFT yang bisa diupdate
        if ($laporan->status->kode !== 'DRAFT') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak dapat diupdate karena status bukan Draft',
            ], 400);
        }
        
        // Mulai transaksi DB
        DB::beginTransaction();
        
        try {
            // Update detail laporan
            $existingDetailIds = $laporan->laporanDetail->pluck('id')->toArray();
            $newDetailIds = [];
            
            foreach ($request->detail as $detail) {
                if (isset($detail['id'])) {
                    // Update detail yang sudah ada
                    LaporanDetail::where('id', $detail['id'])
                        ->update([
                            'jenis_program_id' => $detail['jenis_program_id'],
                            'jenis_kelamin_id' => $detail['jenis_kelamin_id'],
                            'status_id' => $detail['status_id'],
                            'jumlah' => $detail['jumlah'],
                        ]);
                        
                    $newDetailIds[] = $detail['id'];
                } else {
                    // Buat detail baru
                    $newDetail = LaporanDetail::create([
                        'laporan_id' => $laporan->id,
                        'jenis_program_id' => $detail['jenis_program_id'],
                        'jenis_kelamin_id' => $detail['jenis_kelamin_id'],
                        'status_id' => $detail['status_id'],
                        'jumlah' => $detail['jumlah'],
                    ]);
                    
                    $newDetailIds[] = $newDetail->id;
                }
            }
            
            // Hapus detail yang tidak ada dalam request
            $detailToDelete = array_diff($existingDetailIds, $newDetailIds);
            if (!empty($detailToDelete)) {
                LaporanDetail::whereIn('id', $detailToDelete)->delete();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil diupdate',
                'data' => $laporan->fresh()->load('laporanDetail')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate laporan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $laporan = LaporanBulanan::find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }
        
        // Cek status laporan, hanya DRAFT yang bisa dihapus
        if ($laporan->status->kode !== 'DRAFT') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak dapat dihapus karena status bukan Draft',
            ], 400);
        }
        
        // Hapus laporan beserta relasinya (akan terhapus cascade)
        $laporan->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dihapus',
        ]);
    }
    
    /**
     * Submit laporan untuk disetujui
     */
    public function submit(string $id)
    {
        $laporan = LaporanBulanan::find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }
        
        // Cek status laporan, hanya DRAFT yang bisa disubmit
        if ($laporan->status->kode !== 'DRAFT') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak dapat disubmit karena status bukan Draft',
            ], 400);
        }
        
        // Ambil status SUBMITTED
        $statusSubmitted = RefStatus::where('kode', 'SUBMITTED')->first();
        if (!$statusSubmitted) {
            return response()->json([
                'success' => false,
                'message' => 'Status Submitted tidak ditemukan',
            ], 400);
        }
        
        // Update status laporan
        $laporan->update([
            'status_id' => $statusSubmitted->id,
            'submitted_at' => Carbon::now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil disubmit',
            'data' => $laporan->fresh()
        ]);
    }
    
    /**
     * Approve laporan
     */
    public function approve(Request $request, string $id)
    {
        // Cek jika user dari dinas
        if (!$request->user()->is_admin && !$request->user()->is_dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui laporan',
            ], 403);
        }
        
        $laporan = LaporanBulanan::find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }
        
        // Cek status laporan, hanya SUBMITTED yang bisa diapprove
        if ($laporan->status->kode !== 'SUBMITTED') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak dapat disetujui karena status bukan Submitted',
            ], 400);
        }
        
        // Ambil status APPROVED
        $statusApproved = RefStatus::where('kode', 'APPROVED')->first();
        if (!$statusApproved) {
            return response()->json([
                'success' => false,
                'message' => 'Status Approved tidak ditemukan',
            ], 400);
        }
        
        // Update status laporan
        $laporan->update([
            'status_id' => $statusApproved->id,
            'approved_at' => Carbon::now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil disetujui',
            'data' => $laporan->fresh()
        ]);
    }
    
    /**
     * Reject laporan
     */
    public function reject(Request $request, string $id)
    {
        // Cek jika user dari dinas
        if (!$request->user()->is_admin && !$request->user()->is_dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak laporan',
            ], 403);
        }
        
        $laporan = LaporanBulanan::find($id);
        
        if (!$laporan) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan',
            ], 404);
        }
        
        // Cek status laporan, hanya SUBMITTED yang bisa direject
        if ($laporan->status->kode !== 'SUBMITTED') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak dapat ditolak karena status bukan Submitted',
            ], 400);
        }
        
        // Ambil status REJECTED
        $statusRejected = RefStatus::where('kode', 'REJECTED')->first();
        if (!$statusRejected) {
            return response()->json([
                'success' => false,
                'message' => 'Status Rejected tidak ditemukan',
            ], 400);
        }
        
        // Ambil status DRAFT
        $statusDraft = RefStatus::where('kode', 'DRAFT')->first();
        
        // Update status laporan
        $laporan->update([
            'status_id' => $statusDraft ? $statusDraft->id : $statusRejected->id,
            'submitted_at' => null,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Laporan ditolak dan dikembalikan ke status Draft',
            'data' => $laporan->fresh()
        ]);
    }
    
    /**
     * Generate template laporan bulanan
     */
    public function generateTemplate(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Ambil semua jenis program
        $jenisProgram = RefJenisProgram::all();
        
        // Ambil semua jenis kelamin
        $jenisKelamin = RefJenisKelamin::all();
        
        // Ambil status pasien (RUTIN, TERKENDALI, TIDAK_TERKENDALI)
        $statusPasien = RefStatus::where('kategori', 'pasien')->get();
        
        // Generate template
        $template = [];
        
        foreach ($jenisProgram as $program) {
            foreach ($jenisKelamin as $kelamin) {
                foreach ($statusPasien as $status) {
                    $template[] = [
                        'jenis_program_id' => $program->id,
                        'jenis_program' => $program->nama,
                        'jenis_kelamin_id' => $kelamin->id,
                        'jenis_kelamin' => $kelamin->nama,
                        'status_id' => $status->id,
                        'status' => $status->nama,
                        'jumlah' => 0,
                    ];
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'bulan' => $request->bulan,
                'template' => $template
            ]
        ]);
    }
}