<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RefJenisKelamin;

class RefJenisKelaminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $jenisKelamin = RefJenisKelamin::all();
        
        return response()->json([
            'success' => true,
            'data' => $jenisKelamin
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:ref_jenis_kelamin,kode',
            'nama' => 'required|string|max:255'
        ]);
        
        $jenisKelamin = RefJenisKelamin::create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Jenis kelamin berhasil ditambahkan',
            'data' => $jenisKelamin
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $jenisKelamin = RefJenisKelamin::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $jenisKelamin
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $jenisKelamin = RefJenisKelamin::findOrFail($id);
        
        $validated = $request->validate([
            'kode' => 'required|string|max:10|unique:ref_jenis_kelamin,kode,' . $id,
            'nama' => 'required|string|max:255'
        ]);
        
        $jenisKelamin->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Jenis kelamin berhasil diupdate',
            'data' => $jenisKelamin
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $jenisKelamin = RefJenisKelamin::findOrFail($id);
        
        // Check if the jenis kelamin is being used
        if ($jenisKelamin->pasien()->count() > 0 || $jenisKelamin->laporanDetail()->count() > 0 || $jenisKelamin->rekapDetail()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis kelamin tidak dapat dihapus karena sedang digunakan'
            ], 422);
        }
        
        $jenisKelamin->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Jenis kelamin berhasil dihapus'
        ]);
    }
}