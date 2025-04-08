<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RefStatus;

class RefStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $status = RefStatus::all();
        
        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:20|unique:ref_status,kode',
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|max:50',
            'keterangan' => 'nullable|string'
        ]);
        
        $status = RefStatus::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Status berhasil ditambahkan',
            'data' => $status
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $status = RefStatus::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $status = RefStatus::findOrFail($id);
        
        $validated = $request->validate([
            'kode' => 'required|string|max:20|unique:ref_status,kode,' . $id,
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|max:50',
            'keterangan' => 'nullable|string'
        ]);
        
        $status->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diupdate',
            'data' => $status
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $status = RefStatus::findOrFail($id);
        
        // Check if the status is being used
        if (
            $status->pemeriksaanStatus()->count() > 0 || 
            $status->sasaranTahunan()->count() > 0 || 
            $status->laporanBulanan()->count() > 0 || 
            $status->laporanDetail()->count() > 0 || 
            $status->rekapDinas()->count() > 0 || 
            $status->rekapDetail()->count() > 0
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Status tidak dapat dihapus karena sedang digunakan'
            ], 422);
        }
        
        $status->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Status berhasil dihapus'
        ]);
    }
    
    /**
     * Get status by kategori
     *
     * @param string $kategori
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByKategori($kategori)
    {
        $status = RefStatus::where('kategori', $kategori)->get();
        
        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }
}