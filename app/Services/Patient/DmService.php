<?php

namespace App\Services\Patient;

use App\DataTransferObjects\DmExaminationData;
use App\Events\DmExaminationCreated;
use App\Exceptions\ExaminationAlreadyExistsException;
use App\Exceptions\PatientNotFoundException;
use App\Models\DmExamination;
use App\Models\Patient;
use App\Repositories\Contracts\DmExaminationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DmService
{
    /**
     * Create a new DM service instance.
     */
    public function __construct(
        protected DmExaminationRepositoryInterface $dmRepository
    ) {}

    /**
     * Get all DM examinations with filters and pagination
     */
    public function getAllExaminations(
        int $puskesmasId, 
        array $filters = [], 
        int $perPage = 15
    ): array {
        // Base query for unique date-patient combinations
        $query = DB::table('dm_examinations')
            ->select('patient_id', 'examination_date')
            ->where('puskesmas_id', $puskesmasId);
        
        // Apply filters
        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        
        if (isset($filters['month'])) {
            $query->where('month', $filters['month']);
        }
        
        if (isset($filters['is_archived'])) {
            $query->where('is_archived', $filters['is_archived']);
        }
        
        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
        
        // Get unique examination dates for pagination
        $uniqueExaminations = $query->distinct()
            ->orderBy('examination_date', 'desc')
            ->paginate($perPage);
        
        // Prepare results
        $result = [];
        $patientIds = [];
        
        // Collect patient IDs
        foreach ($uniqueExaminations as $item) {
            $patientIds[] = $item->patient_id;
        }
        
        // Get all patients in one query
        $patients = Patient::whereIn('id', $patientIds)->get()->keyBy('id');
        
        // Get all relevant examinations in one query
        $allExaminations = DmExamination::where('puskesmas_id', $puskesmasId)
            ->whereIn('patient_id', $patientIds)
            ->get();
        
        // Group examinations by patient_id and date
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
                        'gdsp' => null,
                    ],
                    'year' => $exam->year,
                    'month' => $exam->month,
                    'is_archived' => $exam->is_archived,
                ];
            }
            
            $groupedExams[$key]['examination_results'][$exam->examination_type] = $exam->result;
        }
        
        // Build result array based on pagination order
        foreach ($uniqueExaminations as $item) {
            $key = $item->patient_id . '_' . Carbon::parse($item->examination_date)->format('Y-m-d');
            
            if (isset($groupedExams[$key])) {
                $result[] = $groupedExams[$key];
            }
        }
        
        return [
            'data' => $result,
            'pagination' => [
                'current_page' => $uniqueExaminations->currentPage(),
                'from' => $uniqueExaminations->firstItem(),
                'last_page' => $uniqueExaminations->lastPage(),
                'per_page' => $uniqueExaminations->perPage(),
                'to' => $uniqueExaminations->lastItem(),
                'total' => $uniqueExaminations->total(),
            ],
        ];
    }

    /**
     * Create new DM examinations
     */
    public function createExaminations(
        int $patientId, 
        int $puskesmasId, 
        string $examinationDate, 
        array $examinations
    ): array {
        // Check if patient exists
        $patient = Patient::find($patientId);
        if (!$patient) {
            throw new PatientNotFoundException();
        }
        
        // Parse examination date
        $date = Carbon::parse($examinationDate);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;
        
        // Start transaction
        DB::beginTransaction();
        
        try {
            // Delete existing examinations for this date
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
            
            // Create new examinations for non-null values
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
                    
                    // Dispatch event for statistics update
                    event(new DmExaminationCreated($examination));
                }
            }
            
            // Add DM year to patient if needed
            if (!$patient->hasDmInYear($year)) {
                $patient->addDmYear($year);
                $patient->save();
            }
            
            DB::commit();
            
            // Format response
            return [
                'id' => $createdExaminations[0]->id ?? null,
                'patient_id' => $patientId,
                'patient_name' => $patient->name,
                'puskesmas_id' => $puskesmasId,
                'examination_date' => $examinationDate,
                'examination_results' => $examinations,
                'year' => $year,
                'month' => $month,
                'is_archived' => $isArchived,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating DM examinations: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update DM examinations
     */
    public function updateExaminations(
        int $patientId, 
        int $puskesmasId, 
        string $examinationDate, 
        array $examinations
    ): array {
        // Parse examination date
        $date = Carbon::parse($examinationDate);
        $year = $date->year;
        $month = $date->month;
        $isArchived = $date->year < Carbon::now()->year;
        
        // Start transaction
        DB::beginTransaction();
        
        try {
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
            
            // Add DM year to patient if needed
            $patient = Patient::find($patientId);
            if ($patient && !$patient->hasDmInYear($year)) {
                $patient->addDmYear($year);
                $patient->save();
            }
            
            DB::commit();
            
            // Get all examinations for this date after update
            $updatedExaminations = DmExamination::where('patient_id', $patientId)
                ->where('puskesmas_id', $puskesmasId)
                ->whereDate('examination_date', $examinationDate)
                ->get();
            
            // Format examination results for response
            $examinationResults = [
                'hba1c' => null,
                'gdp' => null,
                'gd2jpp' => null,
                'gdsp' => null,
            ];
            
            foreach ($updatedExaminations as $exam) {
                $examinationResults[$exam->examination_type] = $exam->result;
            }
            
            // Format response
            return [
                'id' => $updatedExaminations->first()->id ?? null,
                'patient_id' => $patientId,
                'patient_name' => $patient ? $patient->name : 'Unknown',
                'puskesmas_id' => $puskesmasId,
                'examination_date' => $examinationDate,
                'examination_results' => $examinationResults,
                'year' => $year,
                'month' => $month,
                'is_archived' => $isArchived,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating DM examinations: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete DM examinations for a specific date
     */
    public function deleteExaminations(int $patientId, int $puskesmasId, string $examinationDate): bool
    {
        // Start transaction
        DB::beginTransaction();
        
        try {
            // Get the year of the examinations
            $date = Carbon::parse($examinationDate);
            $year = $date->year;
            
            // Delete examinations
            DmExamination::where('patient_id', $patientId)
                ->where('puskesmas_id', $puskesmasId)
                ->whereDate('examination_date', $examinationDate)
                ->delete();
            
            // Check if patient still has examinations in this year
            $hasOtherExaminations = DmExamination::where('patient_id', $patientId)
                ->where('year', $year)
                ->exists();
            
            if (!$hasOtherExaminations) {
                // Remove year from patient's DM years
                $patient = Patient::find($patientId);
                if ($patient) {
                    $patient->removeDmYear($year);
                    $patient->save();
                }
            }
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting DM examinations: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get patient examinations by year and month
     */
    public function getPatientExaminationsByYearMonth(
        int $patientId, 
        ?int $year = null, 
        ?int $month = null
    ): array {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return [];
        }
        
        $types = ['hba1c', 'gdp', 'gd2jpp', 'gdsp'];
        $result = [];
        
        // Get examination years for this patient
        $years = $year ? [$year] : ($patient->dm_years ?? []);
        
        foreach ($years as $y) {
            $monthQuery = DmExamination::where('patient_id', $patientId)
                ->where('year', $y);
                
            if ($month) {
                $monthQuery->where('month', $month);
                $months = [$month];
            } else {
                $months = $monthQuery->distinct('month')->pluck('month')->toArray();
                sort($months);
            }
            
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
     */
    public function isControlled(string $examinationType, float $result): bool
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