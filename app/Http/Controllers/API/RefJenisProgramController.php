<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RefJenisProgram;

class RefJenisProgramController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $jenisProgram = RefJenisProgram::all();
        
        return response()->json([
            'success' => true,
            'data' => $jenisProgram
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:ref_jenis_program,kode',
            'nama' => 'required|string|max:255',
            'keterangan' => 'nullable|string'
        ]);
        
        $jenisProgram = RefJenisProgram::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Jenis program berhasil ditambahkan',
            'data' => $jenisProgram
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $jenisProgram = RefJenisProgram::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $jenisProgram
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $jenisProgram = RefJenisProgram::findOrFail($id);
        
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:ref_jenis_program,kode,' . $id,
            'nama' => 'required|string|max:255',
            'keterangan' => 'nullable|string'
        ]);
        
        $jenisProgram->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Jenis program berhasil diupdate',
            'data' => $jenisProgram
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $jenisProgram = RefJenisProgram::findOrFail($id);
        
        // Check if the jenis program is being used
        if (
            $jenisProgram->pemeriksaanStatus()->count() > 0 || 
            $jenisProgram->sasaranPuskesmas()->count() > 0 || 
            $jenisProgram->laporanDetail()->count() > 0 || 
            $jenisProgram->rekapDetail()->count() > 0 || 
            $jenisProgram->pencapaianBulanan()->count() > 0
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis program tidak dapat dihapus karena sedang digunakan'
            ], 422);
        }
        
        $jenisProgram->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Jenis program berhasil dihapus'
        ]);
    }
}