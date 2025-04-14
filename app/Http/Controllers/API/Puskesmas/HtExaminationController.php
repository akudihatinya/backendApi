<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\HtExaminationRequest;
use App\Http\Resources\HtExaminationCollection;
use App\Http\Resources\HtExaminationResource;
use App\Models\HtExamination;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HtExaminationController extends Controller
{
    public function index(Request $request)
    {
        $puskesmasId = $request->user()->puskesmas->id;
        
        $query = HtExamination::where('puskesmas_id', $puskesmasId)
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
        
        $examinations = $query->orderBy('examination_date', 'desc')
            ->paginate($request->per_page ?? 10);
        
        return new HtExaminationCollection($examinations);
    }
    
    public function store(HtExaminationRequest $request)
    {
        $data = $request->validated();
        $data['puskesmas_id'] = $request->user()->puskesmas->id;
        
        $date = Carbon::parse($data['examination_date']);
        $data['year'] = $date->year;
        $data['month'] = $date->month;
        
        // Set archived status based on year
        $data['is_archived'] = $date->year < Carbon::now()->year;
        
        // Make sure the patient has HT
        $patient = Patient::findOrFail($data['patient_id']);
        if (!$patient->has_ht) {
            $patient->update(['has_ht' => true]);
        }
        
        $examination = HtExamination::create($data);
        
        return response()->json([
            'message' => 'Pemeriksaan Hipertensi berhasil ditambahkan',
            'examination' => new HtExaminationResource($examination),
        ], 201);
    }
    
    public function show(Request $request, HtExamination $htExamination)
    {
        if ($htExamination->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        return response()->json([
            'examination' => new HtExaminationResource($htExamination),
        ]);
    }
    
    public function update(HtExaminationRequest $request, HtExamination $htExamination)
    {
        if ($htExamination->puskesmas_id !== $request->user()->puskesmas->id) {
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
        
        $htExamination->update($data);
        
        return response()->json([
            'message' => 'Pemeriksaan Hipertensi berhasil diupdate',
            'examination' => new HtExaminationResource($htExamination),
        ]);
    }
    
    public function destroy(Request $request, HtExamination $htExamination)
    {
        if ($htExamination->puskesmas_id !== $request->user()->puskesmas->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $htExamination->delete();
        
        return response()->json([
            'message' => 'Pemeriksaan Hipertensi berhasil dihapus',
        ]);
    }
}
