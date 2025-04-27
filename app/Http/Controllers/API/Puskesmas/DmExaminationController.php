<?php

namespace App\Http\Controllers\API\Puskesmas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Puskesmas\DmExaminationRequest;
use App\Http\Resources\DmExaminationResource;
use App\Models\DmExamination;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DmExaminationController extends Controller
{
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

        // Build the base query
        $baseQuery = DmExamination::where('puskesmas_id', $puskesmasId)
            ->with('patient');

        // Apply filters
        if ($request->has('year')) {
            $baseQuery->where('year', $request->year);
        }

        if ($request->has('month')) {
            $baseQuery->where('month', $request->month);
        }

        if ($request->has('is_archived')) {
            $baseQuery->where('is_archived', $request->is_archived);
        }

        if ($request->has('patient_id')) {
            $baseQuery->where('patient_id', $request->patient_id);
        }

        // Get unique date-patient combinations for pagination
        $uniqueExamDates = DB::table('dm_examinations')
            ->select('patient_id', 'examination_date')
            ->where('puskesmas_id', $puskesmasId)
            ->when($request->has('year'), function ($q) use ($request) {
                return $q->where('year', $request->year);
            })
            ->when($request->has('month'), function ($q) use ($request) {
                return $q->where('month', $request->month);
            })
            ->when($request->has('is_archived'), function ($q) use ($request) {
                return $q->where('is_archived', $request->is_archived);
            })
            ->when($request->has('patient_id'), function ($q) use ($request) {
                return $q->where('patient_id', $request->patient_id);
            })
            ->distinct()
            ->orderBy('examination_date', 'desc')
            ->paginate($request->per_page ?? 15);

        // Prepare results
        $result = [];
        $patientIds = [];

        // Collect all patient IDs
        foreach ($uniqueExamDates as $item) {
            $patientIds[] = $item->patient_id;
        }

        // Get all patients at once
        $patients = Patient::whereIn('id', $patientIds)->get()->keyBy('id');

        // Get all examinations at once
        $allExaminations = DmExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $patientIds)
            ->get();

        // Group examinations by patient and date
        $groupedExams = [];
        foreach ($allExaminations as $exam) {
            $key = $exam->patient_id . '_' . $exam->examination_date->format('Y-m-d');
            if (!isset($groupedExams[$key])) {
                $groupedExams[$key] = [
                    'id' => $exam->id,
                    'patient_id' => $exam->patient_id,
                    'patient_name' => $patients[$exam->patient_id]->name,
                    'puskesmas_id' => $exam->puskesmas_id,
                    'examination_date' => $exam->examination_date->format('Y-m-d'),
                    'examination_results' => [
                        'hba1c' => null,
                        'gdp' => null,
                        'gd2jpp' => null,
                        'gdsp' => null
                    ],
                    'year' => $exam->year,
                    'month' => $exam->month,
                    'is_archived' => $exam->is_archived
                ];
            }
            $groupedExams[$key]['examination_results'][$exam->examination_type] = $exam->result;
        }

        // Build result array following pagination order
        foreach ($uniqueExamDates as $item) {
            $key = $item->patient_id . '_' . Carbon::parse($item->examination_date)->format('Y-m-d');
            if (isset($groupedExams[$key])) {
                $result[] = $groupedExams[$key];
            }
        }

        // Return response with clean JSON format
        return response()->json([
            'data' => $result,
            'links' => [
                'first' => $uniqueExamDates->url(1),
                'last' => $uniqueExamDates->url($uniqueExamDates->lastPage()),
                'prev' => $uniqueExamDates->previousPageUrl(),
                'next' => $uniqueExamDates->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $uniqueExamDates->currentPage(),
                'from' => $uniqueExamDates->firstItem(),
                'last_page' => $uniqueExamDates->lastPage(),
                'path' => $request->url(),
                'per_page' => $uniqueExamDates->perPage(),
                'to' => $uniqueExamDates->lastItem(),
                'total' => $uniqueExamDates->total(),
            ],
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
                    $patient = \App\Models\Patient::find($value);

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

        $patientId = $request->patient_id;
        $examinationDate = $request->examination_date;
        $date = Carbon::parse($examinationDate);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;

        // Make sure patient has DM year added
        $patient = Patient::findOrFail($patientId);
        if (!$patient->hasDmInYear($year)) {
            $patient->addDmYear($year);
            $patient->save();
        }

        DB::beginTransaction();
        try {
            // Delete all examinations for this date first
            $existingTypes = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
            foreach ($existingTypes as $type) {
                if (array_key_exists($type, $request->examinations)) {
                    DmExamination::where('patient_id', $patientId)
                        ->where('puskesmas_id', $puskesmasId)
                        ->whereDate('examination_date', $examinationDate)
                        ->where('examination_type', $type)
                        ->delete();
                }
            }

            $createdExaminations = [];

            // Create new examinations only for non-null values
            foreach ($request->examinations as $type => $result) {
                if ($result !== null) {
                    $examination = DmExamination::create([
                        'patient_id' => $patientId,
                        'puskesmas_id' => $puskesmasId,
                        'examination_date' => $examinationDate,
                        'examination_type' => $type,
                        'result' => $result,
                        'year' => $year,
                        'month' => $month,
                        'is_archived' => $isArchived,
                    ]);

                    $createdExaminations[] = $examination;
                }
            }

            DB::commit();

            // Get all examinations for this date
            $allExaminations = DmExamination::where('patient_id', $patientId)
                ->where('puskesmas_id', $puskesmasId)
                ->whereDate('examination_date', $examinationDate)
                ->get();

            // Format response
            $examinationResults = [
                'hba1c' => null,
                'gdp' => null,
                'gd2jpp' => null,
                'gdsp' => null
            ];

            foreach ($allExaminations as $exam) {
                $examinationResults[$exam->examination_type] = $exam->result;
            }

            // Base ID for response
            $baseId = count($createdExaminations) > 0 ? $createdExaminations[0]->id : null;

            // Create response data
            $responseData = [
                'id' => $baseId,
                'patient_id' => $patientId,
                'patient_name' => $patient->name,
                'puskesmas_id' => $puskesmasId,
                'examination_date' => $examinationDate,
                'examination_results' => $examinationResults,
                'year' => $year,
                'month' => $month,
                'is_archived' => $isArchived
            ];

            return response()->json([
                'message' => 'Pemeriksaan Diabetes Mellitus berhasil ditambahkan',
                'examination' => $responseData,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
        $types = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
        $result = [];

        // Get examination years for this patient
        $years = $filterYear ? [$filterYear] : ($patient->dm_years ?? []);

        foreach ($years as $year) {
            $months = $filterMonth ? [$filterMonth] : range(1, 12);

            foreach ($months as $month) {
                $monthlyData = [];

                foreach ($types as $type) {
                    $exam = DmExamination::where('patient_id', $patientId)
                        ->where('year', $year)
                        ->where('month', $month)
                        ->where('examination_type', $type)
                        ->first();

                    $monthlyData[$type] = $exam ? $exam->result : null;
                }

                $result[$year][$month] = $monthlyData;
            }
        }

        return response()->json([
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'puskesmas_id' => $patient->puskesmas_id,
            'examinations_by_year' => $result
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
                    $patient = \App\Models\Patient::find($value);

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

        $patientId = $request->patient_id;
        $examinationDate = $request->examination_date;
        $puskesmasId = Auth::user()->puskesmas_id;
        $date = Carbon::parse($examinationDate);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;

        DB::beginTransaction();
        try {
            // Delete and recreate examinations
            $existingTypes = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];

            foreach ($existingTypes as $type) {
                if (array_key_exists($type, $request->examinations)) {
                    // Delete existing examination of this type
                    DmExamination::where('patient_id', $patientId)
                        ->where('puskesmas_id', $puskesmasId)
                        ->whereDate('examination_date', $examinationDate)
                        ->where('examination_type', $type)
                        ->delete();

                    // Create new if value is not null
                    if ($request->examinations[$type] !== null) {
                        DmExamination::create([
                            'patient_id' => $patientId,
                            'puskesmas_id' => $puskesmasId,
                            'examination_date' => $examinationDate,
                            'examination_type' => $type,
                            'result' => $request->examinations[$type],
                            'year' => $year,
                            'month' => $month,
                            'is_archived' => $isArchived,
                        ]);
                    }
                }
            }

            DB::commit();

            // Get all examinations for this date after update
            $allExaminations = DmExamination::where('patient_id', $patientId)
                ->where('puskesmas_id', $puskesmasId)
                ->whereDate('examination_date', $examinationDate)
                ->get();

            // Format response
            $examinationResults = [
                'hba1c' => null,
                'gdp' => null,
                'gd2jpp' => null,
                'gdsp' => null
            ];

            foreach ($allExaminations as $exam) {
                $examinationResults[$exam->examination_type] = $exam->result;
            }

            // Use original ID or new ID if exists
            $responseId = $dmExamination->id;
            if ($allExaminations->isNotEmpty()) {
                $responseId = $allExaminations->first()->id;
            }

            // Create response data
            $responseData = [
                'id' => $responseId,
                'patient_id' => $patientId,
                'patient_name' => Patient::find($patientId)->name,
                'puskesmas_id' => $puskesmasId,
                'examination_date' => $examinationDate,
                'examination_results' => $examinationResults,
                'year' => $year,
                'month' => $month,
                'is_archived' => $isArchived
            ];

            return response()->json([
                'message' => 'Pemeriksaan Diabetes Mellitus berhasil diupdate',
                'examination' => $responseData,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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

        $dmExamination->delete();

        return response()->json([
            'message' => 'Pemeriksaan Diabetes Mellitus berhasil dihapus',
        ]);
    }
}