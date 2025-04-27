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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HtExaminationController extends Controller
{
    /**
     * Display a listing of HT examinations
     */
    public function index(Request $request): HtExaminationCollection|JsonResponse
    {
        // Get the authenticated user's puskesmas id
        $puskesmasId = Auth::user()->puskesmas_id;
        
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'User tidak terkait dengan puskesmas manapun.',
            ], 403);
        }
        
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
    
    /**
     * Store a newly created HT examination
     */
    public function store(HtExaminationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['puskesmas_id'] = Auth::user()->puskesmas_id;
        
        if (!$data['puskesmas_id']) {
            return response()->json([
                'message' => 'User tidak terkait dengan puskesmas manapun.',
            ], 403);
        }
        
        $date = Carbon::parse($data['examination_date']);
        $data['year'] = $date->year;
        $data['month'] = $date->month;
        
        // Set archived status based on year
        $data['is_archived'] = $date->year < Carbon::now()->year;
        
        // Make sure the patient belongs to this puskesmas
        $patient = Patient::findOrFail($data['patient_id']);
        if ($patient->puskesmas_id !== $data['puskesmas_id']) {
            return response()->json([
                'message' => 'Pasien bukan milik puskesmas Anda',
            ], 403);
        }
        
        // Add the year to patient's ht_years if not exists
        if (!$patient->hasHtInYear($data['year'])) {
            $patient->addHtYear($data['year']);
            $patient->save();
        }
        
        $examination = HtExamination::create($data);
        
        return response()->json([
            'message' => 'Pemeriksaan Hipertensi berhasil ditambahkan',
            'examination' => new HtExaminationResource($examination),
        ], 201);
    }
    
    /**
     * Display the specified HT examination
     */
    public function show(Request $request, HtExamination $htExamination): JsonResponse
    {
        if ($htExamination->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pemeriksaan bukan milik puskesmas Anda',
            ], 403);
        }
        
        return response()->json([
            'examination' => new HtExaminationResource($htExamination),
        ]);
    }
    
    /**
     * Update the specified HT examination
     */
    public function update(HtExaminationRequest $request, HtExamination $htExamination): JsonResponse
    {
        if ($htExamination->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pemeriksaan bukan milik puskesmas Anda',
            ], 403);
        }
        
        $data = $request->validated();
        
        // Check if patient belongs to this puskesmas
        if (isset($data['patient_id'])) {
            $patient = Patient::findOrFail($data['patient_id']);
            if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
                return response()->json([
                    'message' => 'Pasien bukan milik puskesmas Anda',
                ], 403);
            }
        }
        
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
    
    /**
     * Remove the specified HT examination
     */
    public function destroy(Request $request, HtExamination $htExamination): JsonResponse
    {
        if ($htExamination->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pemeriksaan bukan milik puskesmas Anda',
            ], 403);
        }
        
        $htExamination->delete();
        
        return response()->json([
            'message' => 'Pemeriksaan Hipertensi berhasil dihapus',
        ]);
    }
}