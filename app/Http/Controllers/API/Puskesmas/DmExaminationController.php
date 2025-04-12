<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\DmExaminationRequest;
use App\Http\Resources\DmExaminationCollection;
use App\Http\Resources\DmExaminationResource;
use App\Models\DmExamination;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DmExaminationController extends Controller
{
    public function index(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;
        
        $query = DmExamination::where('puskesmas_id', $puskesmasId)
            ->with('patient');
        
        // Filter by year
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }
        
        // Filter by month
        if ($request->has('month')) {
            $query->where('month', $request->month);
        }
        
        // Filter by archived status
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->is_archived);
        }
        
        // Filter by patient
        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        
        // Filter by examination type
        if ($request->has('examination_type')) {
            $query->where('examination_type', $request->examination_type);
        }
        
        $examinations = $query->orderBy('examination_date', 'desc')
            ->paginate($request->per_page ?? 15);
        
        return new DmExaminationCollection($examinations);
    }
    
    public function store(DmExaminationRequest $request)
    {
        $data = $request->validated();
        $data['puskesmas_id'] = $request->user()->puskesmas->id;
        
        $date = Carbon::parse($data['examination_date']);
        $data['year'] = $date->year;
        $data['month'] = $date->month;
        
        // Set archived status based on year
        $data['is_archived'] = $date->year < Carbon::now()->year;
        
        // Make sure the patient has DM
        $patient = Patient::findOrFail($data['patient_id']);
        if (!$patient->has_dm) {
            $patient->update(['has_dm' => true]);
        }
        
        $examination = DmExamination::create($data);
        
        return response()->json([
            'message' => 'Pemeriksaan Diabetes Mellitus berhasil ditambahkan',
            'examination' => new DmExaminationResource($examination),
        ], 201);
    }
    
    public function show(Request $request, DmExamination $dmExamination)
    {
        if ($dmExamination->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        return response()->json([
            'examination' => new DmExaminationResource($dmExamination),
        ]);
    }
    
    public function update(DmExaminationRequest $request, DmExamination $dmExamination)
    {
        if ($dmExamination->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $data = $request->validated();
        
        $date = Carbon::parse($data['examination_date']);
        $data['year'] = $date->year;
        $data['month'] = $date->month;
        
        // Set archived status based on year
        $data['is_archived'] = $date->year < Carbon::now()->year;
        
        $dmExamination->update($data);
        
        return response()->json([
            'message' => 'Pemeriksaan Diabetes Mellitus berhasil diupdate',
            'examination' => new DmExaminationResource($dmExamination),
        ]);
    }
    
    public function destroy(Request $request, DmExamination $dmExamination)
    {
        if ($dmExamination->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $dmExamination->delete();
        
        return response()->json([
            'message' => 'Pemeriksaan Diabetes Mellitus berhasil dihapus',
        ]);
    }
}
