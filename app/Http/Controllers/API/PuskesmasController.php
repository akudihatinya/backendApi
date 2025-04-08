<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Puskesmas;

class PuskesmasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Puskesmas::with('dinas');
        
        // Filter by dinas_id
        if ($request->has('dinas_id') && $request->dinas_id) {
            $query->where('dinas_id', $request->dinas_id);
        }
        
        // Search by name or code
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                 ->orWhere('kode', 'like', "%{$search}%");
            });
        }
        
        $perPage = $request->input('per_page', 25); // Default 25 records per page
        
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
        if (!$request->user()->is_admin && !$request->user()->is_dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to create puskesmas.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'dinas_id' => 'required|exists:dinas,id',
            'kode' => 'required|string|max:10|unique:puskesmas',
            'nama' => 'required|string|max:100',
            'alamat' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $puskesmas = Puskesmas::create([
            'dinas_id' => $request->dinas_id,
            'kode' => $request->kode,
            'nama' => $request->nama,
            'alamat' => $request->alamat,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Puskesmas berhasil ditambahkan',
            'data' => $puskesmas->load('dinas')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $puskesmas = Puskesmas::with('dinas')->find($id);
        
        if (!$puskesmas) {
            return response()->json([
                'success' => false,
                'message' => 'Puskesmas tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $puskesmas
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Verify user has admin access
        if (!$request->user()->is_admin && !$request->user()->is_dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to update puskesmas.'
            ], 403);
        }
        
        $puskesmas = Puskesmas::find($id);
        
        if (!$puskesmas) {
            return response()->json([
                'success' => false,
                'message' => 'Puskesmas tidak ditemukan'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'dinas_id' => 'required|exists:dinas,id',
            'kode' => 'required|string|max:10|unique:puskesmas,kode,' . $id,
            'nama' => 'required|string|max:100',
            'alamat' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $puskesmas->update([
            'dinas_id' => $request->dinas_id,
            'kode' => $request->kode,
            'nama' => $request->nama,
            'alamat' => $request->alamat,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Puskesmas berhasil diupdate',
            'data' => $puskesmas->fresh()->load('dinas')
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
                'message' => 'Unauthorized. You do not have permission to delete puskesmas.'
            ], 403);
        }
        
        $puskesmas = Puskesmas::find($id);
        
        if (!$puskesmas) {
            return response()->json([
                'success' => false,
                'message' => 'Puskesmas tidak ditemukan'
            ], 404);
        }
        
        // Check if puskesmas has related data
        if ($puskesmas->pasien()->count() > 0 || $puskesmas->laporanBulanan()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Puskesmas tidak dapat dihapus karena memiliki data terkait'
            ], 400);
        }
        
        $puskesmas->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Puskesmas berhasil dihapus'
        ]);
    }
}