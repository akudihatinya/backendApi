<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\PatientRequest;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;
        
        $query = Patient::where('puskesmas_id', $puskesmasId);
        
        // Filter by disease type
        if ($request->has('disease_type')) {
            if ($request->disease_type === 'ht') {
                $query->where('has_ht', true);
            } elseif ($request->disease_type === 'dm') {
                $query->where('has_dm', true);
            } elseif ($request->disease_type === 'both') {
                $query->where('has_ht', true)->where('has_dm', true);
            }
        }
        
        // Search by name, NIK, or BPJS
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('bpjs_number', 'like', "%{$search}%");
            });
        }
        
        $patients = $query->paginate($request->per_page ?? 15);
        
        return new PatientCollection($patients);
    }
    
    public function store(PatientRequest $request)
    {
        $data = $request->validated();
        $data['puskesmas_id'] = $request->user()->puskesmas->id;
        
        $patient = Patient::create($data);
        
        return response()->json([
            'message' => 'Pasien berhasil ditambahkan',
            'patient' => new PatientResource($patient),
        ], 201);
    }
    
    public function show(Request $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        return response()->json([
            'patient' => new PatientResource($patient),
        ]);
    }
    
    public function update(PatientRequest $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $data = $request->validated();
        
        $patient->update($data);
        
        return response()->json([
            'message' => 'Pasien berhasil diupdate',
            'patient' => new PatientResource($patient),
        ]);
    }
    
    public function destroy(Request $request, Patient $patient)
    {
        if ($patient->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $patient->delete();
        
        return response()->json([
            'message' => 'Pasien berhasil dihapus',
        ]);
    }
}