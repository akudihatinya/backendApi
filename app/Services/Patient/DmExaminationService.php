<?php

namespace App\Services\Patient;

use App\Repositories\Contracts\DmExaminationRepositoryInterface;
use App\Models\DmExamination;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DmExaminationService
{
    protected $dmExaminationRepository;

    public function __construct(DmExaminationRepositoryInterface $dmExaminationRepository)
    {
        $this->dmExaminationRepository = $dmExaminationRepository;
    }

    /**
     * Get all examinations with filters and pagination
     * 
     * @param int $puskesmasId
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllExaminations(int $puskesmasId, array $filters = [], int $perPage = 15)
    {
        // Build the base query for getting unique dates and patients
        $baseQuery = DB::table('dm_examinations')
            ->select('patient_id', 'examination_date')
            ->where('puskesmas_id', $puskesmasId);

        // Apply filters
        if (isset($filters['year'])) {
            $baseQuery->where('year', $filters['year']);
        }

        if (isset($filters['month'])) {
            $baseQuery->where('month', $filters['month']);
        }

        if (isset($filters['is_archived'])) {
            $baseQuery->where('is_archived', $filters['is_archived']);
        }

        if (isset($filters['patient_id'])) {
            $baseQuery->where('patient_id', $filters['patient_id']);
        }

        // Get unique date-patient combinations for pagination
        $uniqueExamDates = $baseQuery->distinct()
            ->orderBy('examination_date', 'desc')
            ->paginate($perPage);

        // Prepare results and collect patient IDs
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
                    'patient_name' => $patients[$exam->patient_id]->name ?? 'Unknown',
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

        return [
            'data' => $result,
            'pagination' => [
                'current_page' => $uniqueExamDates->currentPage(),
                'from' => $uniqueExamDates->firstItem(),
                'last_page' => $uniqueExamDates->lastPage(),
                'per_page' => $uniqueExamDates->perPage(),
                'to' => $uniqueExamDates->lastItem(),
                'total' => $uniqueExamDates->total(),
            ]
        ];
    }

    /**
     * Create new DM examinations for a specific date
     * 
     * @param int $patientId
     * @param int $puskesmasId
     * @param string $examinationDate
     * @param array $examinations
     * @return array
     */
    public function createExaminations(int $patientId, int $puskesmasId, string $examinationDate, array $examinations)
    {
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
                if (array_key_exists($type, $examinations)) {
                    DmExamination::where('patient_id', $patientId)
                        ->where('puskesmas_id', $puskesmasId)
                        ->whereDate('examination_date', $examinationDate)
                        ->where('examination_type', $type)
                        ->delete();
                }
            }

            $createdExaminations = [];

            // Create new examinations only for non-null values
            foreach ($examinations as $type => $result) {
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
            return [
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
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update DM examinations for a specific date
     * 
     * @param int $patientId
     * @param int $puskesmasId
     * @param string $examinationDate
     * @param array $examinations
     * @return array
     */
    public function updateExaminations(int $patientId, int $puskesmasId, string $examinationDate, array $examinations)
    {
        $date = Carbon::parse($examinationDate);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;

        DB::beginTransaction();
        try {
            // Delete and recreate examinations
            $existingTypes = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];

            foreach ($existingTypes as $type) {
                if (array_key_exists($type, $examinations)) {
                    // Delete existing examination of this type
                    DmExamination::where('patient_id', $patientId)
                        ->where('puskesmas_id', $puskesmasId)
                        ->whereDate('examination_date', $examinationDate)
                        ->where('examination_type', $type)
                        ->delete();

                    // Create new if value is not null
                    if ($examinations[$type] !== null) {
                        DmExamination::create([
                            'patient_id' => $patientId,
                            'puskesmas_id' => $puskesmasId,
                            'examination_date' => $examinationDate,
                            'examination_type' => $type,
                            'result' => $examinations[$type],
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

            // Use first ID if there are examinations
            $responseId = $allExaminations->isNotEmpty() ? $allExaminations->first()->id : null;

            // Create response data
            return [
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
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete all DM examinations for a specific date
     * 
     * @param int $patientId
     * @param int $puskesmasId
     * @param string $examinationDate
     * @return bool
     */
    public function deleteExaminations(int $patientId, int $puskesmasId, string $examinationDate)
    {
        return DmExamination::where('patient_id', $patientId)
            ->where('puskesmas_id', $puskesmasId)
            ->whereDate('examination_date', $examinationDate)
            ->delete();
    }

    /**
     * Get patient examinations by year and month
     * 
     * @param int $patientId
     * @param int $year
     * @param int|null $month
     * @return array
     */
    public function getPatientExaminationsByYearMonth(int $patientId, int $year, int $month = null)
    {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return [];
        }

        $types = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
        $result = [];

        // Get examination years for this patient
        $years = $year ? [$year] : ($patient->dm_years ?? []);

        foreach ($years as $y) {
            $months = $month ? [$month] : range(1, 12);

            foreach ($months as $m) {
                $monthlyData = [];

                foreach ($types as $type) {
                    $exam = DmExamination::where('patient_id', $patientId)
                        ->where('year', $y)
                        ->where('month', $m)
                        ->where('examination_type', $type)
                        ->first();

                    $monthlyData[$type] = $exam ? $exam->result : null;
                }

                $result[$y][$m] = $monthlyData;
            }
        }

        return $result;
    }

    /**
     * Check if an examination result is controlled
     * 
     * @param string $examinationType
     * @param float $result
     * @return bool
     */
    public function isControlled(string $examinationType, float $result)
    {
        return match($examinationType) {
            'hba1c' => $result < 7,
            'gdp' => $result < 126,
            'gd2jpp' => $result < 200,
            'gdsp' => false, // GDSP is not used for control determination
            default => false,
        };
    }
}