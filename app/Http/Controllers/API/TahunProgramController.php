<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\TahunProgram;

class TahunProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = TahunProgram::orderBy('tahun', 'desc');
        
        // Filter by active status
        if ($request->has('is_active') && $request->is_active !== null) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }
        
        $perPage = $request->input('per_page', 10); // Default 10 records per page
        
        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Verify user has admin access
        if (!$request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to create tahun program.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|unique:tahun_program',
            'nama' => 'required|string|max:50',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // If new tahun program is active, deactivate all others
        if ($request->is_active) {
            TahunProgram::where('is_active', true)->update(['is_active' => false]);
        }
        
        $tahunProgram = TahunProgram::create([
            'tahun' => $request->tahun,
            'nama' => $request->nama,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'is_active' => $request->is_active ?? false,
            'keterangan' => $request->keterangan,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Tahun Program berhasil ditambahkan',
            'data' => $tahunProgram
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $tahunProgram = TahunProgram::find($id);
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun Program tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $tahunProgram
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Verify user has admin access
        if (!$request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to update tahun program.'
            ], 403);
        }
        
        $tahunProgram = TahunProgram::find($id);
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun Program tidak ditemukan'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|unique:tahun_program,tahun,' . $id,
            'nama' => 'required|string|max:50',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // If updating to active, deactivate all others
        if ($request->is_active && !$tahunProgram->is_active) {
            TahunProgram::where('is_active', true)->update(['is_active' => false]);
        }
        
        $tahunProgram->update([
            'tahun' => $request->tahun,
            'nama' => $request->nama,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'is_active' => $request->is_active ?? $tahunProgram->is_active,
            'keterangan' => $request->keterangan,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Tahun Program berhasil diupdate',
            'data' => $tahunProgram->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        // Verify user has admin access
        if (!$request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to delete tahun program.'
            ], 403);
        }
        
        $tahunProgram = TahunProgram::find($id);
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun Program tidak ditemukan'
            ], 404);
        }
        
        // Check if tahun program has related data
        if ($tahunProgram->pemeriksaan()->count() > 0 || 
            $tahunProgram->sasaranTahunan()->count() > 0 ||
            $tahunProgram->laporanBulanan()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun Program tidak dapat dihapus karena memiliki data terkait'
            ], 400);
        }
        
        $tahunProgram->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Tahun Program berhasil dihapus'
        ]);
    }
    
    /**
     * Activate a tahun program and deactivate others
     */
    public function activate(Request $request, string $id)
    {
        // Verify user has admin access
        if (!$request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to activate tahun program.'
            ], 403);
        }
        
        $tahunProgram = TahunProgram::find($id);
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun Program tidak ditemukan'
            ], 404);
        }
        
        // Deactivate all tahun program
        TahunProgram::where('is_active', true)->update(['is_active' => false]);
        
        // Activate the selected tahun program
        $tahunProgram->update(['is_active' => true]);
        
        return response()->json([
            'success' => true,
            'message' => 'Tahun Program berhasil diaktifkan',
            'data' => $tahunProgram->fresh()
        ]);
    }
    
    /**
     * Get active tahun program
     */
    public function getActive()
    {
        $tahunProgram = TahunProgram::where('is_active', true)->first();
        
        if (!$tahunProgram) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada Tahun Program yang aktif'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $tahunProgram
        ]);
    }
}