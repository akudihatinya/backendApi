<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\HtExaminationRequest;
use App\Http\Resources\HtExaminationResource;
use App\Models\HtExamination;
use App\Services\Patient\HtExaminationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HtExaminationController extends Controller
{
    protected $htExaminationService;

    public function __construct(HtExaminationService $htExaminationService)
    {
        $this->htExaminationService = $htExaminationService;
    }

    /**
     * Display a listing of HT examinations
     */
    public function index(Request $request): JsonResponse
    {
        // Get the authenticated user's puskesmas id
        $puskesmasId = Auth::user()->puskesmas_id;
        
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'User tidak terkait dengan puskesmas manapun.',
            ], 403);
        }
        
        // Get filters from request
        $filters = [
            'year' => $request->year,
            'month' => $request->month,
            'is_archived' => $request->is_archived,
            'patient_id' => $request->patient_id,
        ];
        
        $examinations = $this->htExaminationService->getAllExaminations(
            $puskesmasId, 
            $filters, 
            $request->per_page ?? 10
        );
        
        return response()->json([
            'data' => HtExaminationResource::collection($examinations->items()),
            'meta' => [
                'current_page' => $examinations->currentPage(),
                'from' => $examinations->firstItem(),
                'last_page' => $examinations->lastPage(),
                'per_page' => $examinations->perPage(),
                'to' => $examinations->lastItem(),
                'total' => $examinations->total(),
            ],
        ]);
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
        
        $examination = $this->htExaminationService->createExamination($data);
        
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
            $patient = \App\Models\Patient::findOrFail($data['patient_id']);
            if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
                return response()->json([
                    'message' => 'Pasien bukan milik puskesmas Anda',
                ], 403);
            }
        }
        
        $updatedExamination = $this->htExaminationService->updateExamination($htExamination->id, $data);
        
        return response()->json([
            'message' => 'Pemeriksaan Hipertensi berhasil diupdate',
            'examination' => new HtExaminationResource($updatedExamination),
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
        
        $this->htExaminationService->deleteExamination($htExamination->id);
        
        return response()->json([
            'message' => 'Pemeriksaan Hipertensi berhasil dihapus',
        ]);
    }
}