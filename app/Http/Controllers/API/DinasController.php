<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Dinas;

class DinasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Dinas::query();
        
        // Search by name or code
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                 ->orWhere('kode', 'like', "%{$search}%");
            });
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
                'message' => 'Unauthorized. You do not have permission to create dinas.'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|max:10|unique:dinas',
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
        
        $dinas = Dinas::create([
            'kode' => $request->kode,
            'nama' => $request->nama,
            'alamat' => $request->alamat,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Dinas berhasil ditambahkan',
            'data' => $dinas
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $dinas = Dinas::with('puskesmas')->find($id);
        
        if (!$dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Dinas tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $dinas
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
                'message' => 'Unauthorized. You do not have permission to update dinas.'
            ], 403);
        }
        
        $dinas = Dinas::find($id);
        
        if (!$dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Dinas tidak ditemukan'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|max:10|unique:dinas,kode,' . $id,
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
        
        $dinas->update([
            'kode' => $request->kode,
            'nama' => $request->nama,
            'alamat' => $request->alamat,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Dinas berhasil diupdate',
            'data' => $dinas->fresh()
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
                'message' => 'Unauthorized. You do not have permission to delete dinas.'
            ], 403);
        }
        
        $dinas = Dinas::find($id);
        
        if (!$dinas) {
            return response()->json([
                'success' => false,
                'message' => 'Dinas tidak ditemukan'
            ], 404);
        }
        
        // Check if dinas has related data
        if ($dinas->puskesmas()->count() > 0 || $dinas->sasaranTahunan()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Dinas tidak dapat dihapus karena memiliki data terkait'
            ], 400);
        }
        
        $dinas->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Dinas berhasil dihapus'
        ]);
    }
}