<?php

namespace App\Services\Patient;

use App\DataTransferObjects\HtExaminationData;
use App\Events\HtExaminationCreated;
use App\Exceptions\ExaminationAlreadyExistsException;
use App\Exceptions\PatientNotFoundException;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Repositories\Contracts\HtExaminationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HtService
{
    /**
     * Create a new HT service instance.
     */
    public function __construct(
        protected HtExaminationRepositoryInterface $htRepository
    ) {}

    /**
     * Get all HT examinations with filters and pagination
     */
    public function getAllExaminations(
        int $puskesmasId, 
        array $filters = [], 
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = HtExamination::where('puskesmas_id', $puskesmasId)
            ->with('patient');
        
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
        
        return $query->orderBy('examination_date', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new HT examination
     */
    public function createExamination(array $data): HtExamination
    {
        // Check if patient exists
        $patient = Patient::find($data['patient_id']);
        if (!$patient) {
            throw new PatientNotFoundException();
        }
        
        // Check if examination already exists for this date
        $existingExamination = HtExamination::where('patient_id', $data['patient_id'])
            ->where('examination_date', $data['examination_date'])
            ->first();
            
        if ($existingExamination) {
            throw new ExaminationAlreadyExistsException();
        }
        
        // Set year and month
        $date = Carbon::parse($data['examination_date']);
        $data['year'] = $date->year;
        $data['month'] = $date->month;
        
        // Set archived status
        $data['is_archived'] = $date->year < Carbon::now()->year;
        
        // Create examination
        DB::beginTransaction();
        try {
            $examination = HtExamination::create($data);
            
            // Add HT year to patient if needed
            if (!$patient->hasHtInYear($data['year'])) {
                $patient->addHtYear($data['year']);
                $patient->save();
            }
            
            // Dispatch event for statistics update
            event(new HtExaminationCreated($examination));
            
            DB::commit();
            
            return $examination;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating HT examination: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an HT examination
     */
    public function updateExamination(int $id, array $data): HtExamination
    {
        // Find examination
        $examination = HtExamination::findOrFail($id);
        
        // Set year and month if examination date changed
        if (isset($data['examination_date']) && $data['examination_date'] !== $examination->examination_date->format('Y-m-d')) {
            $date = Carbon::parse($data['examination_date']);
            $data['year'] = $date->year;
            $data['month'] = $date->month;
            
            // Set archived status
            $data['is_archived'] = $date->year < Carbon::now()->year;
        }
        
        // Update examination
        DB::beginTransaction();
        try {
            $examination->update($data);
            
            // If patient ID changed, update the HT years for both patients
            if (isset($data['patient_id']) && $data['patient_id'] !== $examination->patient_id) {
                $oldPatient = Patient::find($examination->patient_id);
                $newPatient = Patient::find($data['patient_id']);
                
                // Add HT year to new patient if needed
                if ($newPatient && !$newPatient->hasHtInYear($examination->year)) {
                    $newPatient->addHtYear($examination->year);
                    $newPatient->save();
                }
                
                // Check if old patient still has examinations in this year
                if ($oldPatient) {
                    $hasOtherExaminations = HtExamination::where('patient_id', $oldPatient->id)
                        ->where('year', $examination->year)
                        ->where('id', '!=', $examination->id)
                        ->exists();
                        
                    if (!$hasOtherExaminations) {
                        $oldPatient->removeHtYear($examination->year);
                        $oldPatient->save();
                    }
                }
            }
            
            DB::commit();
            
            return $examination->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating HT examination: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete an HT examination
     */
    public function deleteExamination(int $id): bool
    {
        // Find examination
        $examination = HtExamination::findOrFail($id);
        
        // Delete examination
        DB::beginTransaction();
        try {
            $patientId = $examination->patient_id;
            $year = $examination->year;
            
            $examination->delete();
            
            // Check if patient still has examinations in this year
            $hasOtherExaminations = HtExamination::where('patient_id', $patientId)
                ->where('year', $year)
                ->exists();
                
            if (!$hasOtherExaminations) {
                $patient = Patient::find($patientId);
                if ($patient) {
                    $patient->removeHtYear($year);
                    $patient->save();
                }
            }
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting HT examination: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if an examination is controlled based on systolic and diastolic values
     */
    public function isControlled(int $systolic, int $diastolic): bool
    {
        return $systolic >= 90 && $systolic <= 139 && 
               $diastolic >= 60 && $diastolic <= 89;
    }
}