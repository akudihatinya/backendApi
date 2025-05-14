<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\PatientRequest;
use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\Patient\PatientService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PatientController extends Controller
{
    protected $patientService;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }

    /**
     * Display a listing of patients
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
            'disease_type' => $request->disease_type,
            'search' => $request->search,
            'year' => $request->year,
            'page' => $request->page,
        ];
        
        $patients = $this->patientService->getAllPatients(
            $puskesmasId, 
            $filters, 
            $request->per_page ?? 15
        );
        
        return response()->json([
            'data' => PatientResource::collection($patients->items()),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'from' => $patients->firstItem(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'to' => $patients->lastItem(),
                'total' => $patients->total(),
            ],
        ]);
    }
    
    /**
     * Store a newly created patient
     */
    public function store(PatientRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['puskesmas_id'] = Auth::user()->puskesmas_id;
        
        if (!$data['puskesmas_id']) {
            return response()->json([
                'message' => 'User tidak terkait dengan puskesmas manapun.',
            ], 403);
        }
        
        $patient = $this->patientService->createPatient($data);
        
        return response()->json([
            'message' => 'Pasien berhasil ditambahkan',
            'patient' => new PatientResource($patient),
        ], 201);
    }
    
    /**
     * Display the specified patient
     */
    public function show(Request $request, Patient $patient): JsonResponse
    {
        if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pasien bukan milik puskesmas Anda',
            ], 403);
        }
        
        return response()->json([
            'patient' => new PatientResource($patient),
        ]);
    }
    
    /**
     * Update the specified patient
     */
    public function update(PatientRequest $request, Patient $patient): JsonResponse
    {
        if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pasien bukan milik puskesmas Anda',
            ], 403);
        }
        
        $data = $request->validated();
        $updatedPatient = $this->patientService->updatePatient($patient->id, $data);
        
        return response()->json([
            'message' => 'Pasien berhasil diupdate',
            'patient' => new PatientResource($updatedPatient),
        ]);
    }
    
    /**
     * Add examination year to patient
     */
    public function addExaminationYear(Request $request, Patient $patient): JsonResponse
    {
        if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pasien bukan milik puskesmas Anda',
            ], 403);
        }
        
        $request->validate([
            'year' => 'required|integer',
            'examination_type' => 'required|in:ht,dm',
        ]);
        
        $patient = $this->patientService->addExaminationYear(
            $patient,
            $request->year,
            $request->examination_type
        );
        
        return response()->json([
            'message' => 'Tahun pemeriksaan berhasil ditambahkan',
            'patient' => new PatientResource($patient),
        ]);
    }
    
    /**
     * Remove examination year from patient
     */
    public function removeExaminationYear(Request $request, Patient $patient): JsonResponse
    {
        if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pasien bukan milik puskesmas Anda',
            ], 403);
        }
        
        $request->validate([
            'year' => 'required|integer',
            'examination_type' => 'required|in:ht,dm',
        ]);
        
        $patient = $this->patientService->removeExaminationYear(
            $patient,
            $request->year,
            $request->examination_type
        );
        
        return response()->json([
            'message' => 'Tahun pemeriksaan berhasil dihapus',
            'patient' => new PatientResource($patient),
        ]);
    }
    
    /**
     * Remove the specified patient
     */
    public function destroy(Request $request, Patient $patient): JsonResponse
    {
        if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pasien bukan milik puskesmas Anda',
            ], 403);
        }
        
        $this->patientService->deletePatient($patient->id);
        
        return response()->json([
            'message' => 'Pasien berhasil dihapus',
        ]);
    }
}