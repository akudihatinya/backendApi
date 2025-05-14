<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\DmExaminationRequest;
use App\Models\DmExamination;
use App\Models\Patient;
use App\Services\Patient\DmExaminationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DmExaminationController extends Controller
{
    protected $dmExaminationService;

    public function __construct(DmExaminationService $dmExaminationService)
    {
        $this->dmExaminationService = $dmExaminationService;
    }

    /**
     * Display a listing of DM examinations grouped by date
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
        
        $result = $this->dmExaminationService->getAllExaminations(
            $puskesmasId, 
            $filters, 
            $request->per_page ?? 15
        );
        
        return response()->json([
            'data' => $result['data'],
            'meta' => $result['pagination'],
        ]);
    }

    /**
     * Store a newly created DM examination in storage
     */
    public function store(Request $request): JsonResponse
    {
        // Get the authenticated user's puskesmas id
        $puskesmasId = Auth::user()->puskesmas_id;
        
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'User tidak terkait dengan puskesmas manapun.',
            ], 403);
        }

        // Validate request
        $request->validate([
            'patient_id' => [
                'required',
                'exists:patients,id',
                function ($attribute, $value, $fail) use ($puskesmasId) {
                    $patient = Patient::find($value);

                    if (!$patient) {
                        $fail('Pasien tidak ditemukan.');
                        return;
                    }

                    if ($patient->puskesmas_id !== $puskesmasId) {
                        $fail('Pasien bukan milik Puskesmas ini.');
                    }
                },
            ],
            'examination_date' => 'required|date|before_or_equal:today',
            'examinations' => 'required|array',
            'examinations.hba1c' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gd2jpp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdsp' => 'nullable|numeric|min:0|max:1000',
        ]);

        try {
            $examination = $this->dmExaminationService->createExaminations(
                $request->patient_id,
                $puskesmasId,
                $request->examination_date,
                $request->examinations
            );

            return response()->json([
                'message' => 'Pemeriksaan Diabetes Mellitus berhasil ditambahkan',
                'examination' => $examination,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan pemeriksaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified DM examination grouped by year/month
     */
    public function show(Request $request, $patientId): JsonResponse
    {
        $patient = Patient::find($patientId);

        if (!$patient) {
            return response()->json([
                'message' => 'Pasien tidak ditemukan.'
            ], 404);
        }

        // Check if patient belongs to user's puskesmas
        if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pasien bukan milik puskesmas Anda'
            ], 403);
        }

        $filterYear = $request->query('year');
        $filterMonth = $request->query('month');
        
        $examinations = $this->dmExaminationService->getPatientExaminationsByYearMonth(
            $patientId,
            $filterYear ?? null,
            $filterMonth ?? null
        );

        return response()->json([
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'puskesmas_id' => $patient->puskesmas_id,
            'examinations_by_year' => $examinations
        ]);
    }

    /**
     * Update the specified DM examination in storage
     */
    public function update(Request $request, DmExamination $dmExamination): JsonResponse
    {
        if ($dmExamination->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pemeriksaan bukan milik puskesmas Anda',
            ], 403);
        }

        // Validate request
        $request->validate([
            'patient_id' => [
                'required',
                'exists:patients,id',
                function ($attribute, $value, $fail) {
                    $patient = Patient::find($value);

                    if (!$patient) {
                        $fail('Pasien tidak ditemukan.');
                        return;
                    }

                    if ($patient->puskesmas_id !== Auth::user()->puskesmas_id) {
                        $fail('Pasien bukan milik Puskesmas ini.');
                    }
                },
            ],
            'examination_date' => 'required|date|before_or_equal:today',
            'examinations' => 'required|array',
            'examinations.hba1c' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gd2jpp' => 'nullable|numeric|min:0|max:1000',
            'examinations.gdsp' => 'nullable|numeric|min:0|max:1000',
        ]);

        try {
            $examination = $this->dmExaminationService->updateExaminations(
                $request->patient_id,
                Auth::user()->puskesmas_id,
                $request->examination_date,
                $request->examinations
            );

            return response()->json([
                'message' => 'Pemeriksaan Diabetes Mellitus berhasil diupdate',
                'examination' => $examination,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui pemeriksaan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified DM examination from storage
     */
    public function destroy(Request $request, DmExamination $dmExamination): JsonResponse
    {
        if ($dmExamination->puskesmas_id !== Auth::user()->puskesmas_id) {
            return response()->json([
                'message' => 'Unauthorized - Pemeriksaan bukan milik puskesmas Anda',
            ], 403);
        }

        // Get patient_id and date to delete all related examinations for this date
        $patientId = $dmExamination->patient_id;
        $examinationDate = $dmExamination->examination_date;
        $puskesmasId = $dmExamination->puskesmas_id;

        $this->dmExaminationService->deleteExaminations(
            $patientId,
            $puskesmasId,
            $examinationDate
        );

        return response()->json([
            'message' => 'Pemeriksaan Diabetes Mellitus berhasil dihapus',
        ]);
    }
}