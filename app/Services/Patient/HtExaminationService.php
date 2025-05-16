<?php

namespace App\Services\Patient;

use App\Events\HtExaminationCreated;
use App\Exceptions\ExaminationAlreadyExistsException;
use App\Models\HtExamination;
use App\Models\Patient;
use App\Repositories\Contracts\HtExaminationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HtExaminationService
{
    protected $htExaminationRepository;

    public function __construct(HtExaminationRepositoryInterface $htExaminationRepository)
    {
        $this->htExaminationRepository = $htExaminationRepository;
    }

    /**
     * Get all examinations with filtering
     */
    public function getAllExaminations(int $puskesmasId, array $filters = [], int $perPage = 15)
    {
        return $this->htExaminationRepository->getAllWithFilters($puskesmasId, $filters, $perPage);
    }

    /**
     * Create a new HT examination
     */
    public function createExamination(array $data): HtExamination
    {
        try {
            DB::beginTransaction();
            
            // Parse examination date
            $examinationDate = Carbon::parse($data['examination_date']);
            
            // Check if there's already an examination for this patient on this date
            $existingExamination = HtExamination::where('patient_id', $data['patient_id'])
                ->where('examination_date', $examinationDate->format('Y-m-d'))
                ->first();
                
            if ($existingExamination) {
                throw new ExaminationAlreadyExistsException();
            }
            
            // Extract year and month from examination date
            $data['year'] = $examinationDate->year;
            $data['month'] = $examinationDate->month;
            
            // Set archived status based on year
            $data['is_archived'] = $data['year'] < Carbon::now()->year;
            
            // Create examination
            $examination = $this->htExaminationRepository->create($data);
            
            // Check if patient has the examination year in ht_years
            $patient = Patient::findOrFail($data['patient_id']);
            if (!$patient->hasHtInYear($data['year'])) {
                $patient->addHtYear($data['year']);
                $patient->save();
            }
            
            // Fire event
            event(new HtExaminationCreated($examination));
            
            DB::commit();
            
            return $examination;
            
        } catch (ExaminationAlreadyExistsException $e) {
            DB::rollback();
            throw $e;
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error creating HT examination: " . $e->getMessage(), [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Update an existing HT examination
     */
    public function updateExamination(int $id, array $data): HtExamination
    {
        try {
            DB::beginTransaction();
            
            // Get the examination
            $examination = HtExamination::findOrFail($id);
            
            // Parse examination date if provided
            if (isset($data['examination_date'])) {
                $examinationDate = Carbon::parse($data['examination_date']);
                
                // Extract year and month from examination date
                $data['year'] = $examinationDate->year;
                $data['month'] = $examinationDate->month;
                
                // Set archived status based on year
                $data['is_archived'] = $data['year'] < Carbon::now()->year;
            }
            
            // Update examination
            $this->htExaminationRepository->update($id, $data);
            
            // Refresh examination data
            $examination = HtExamination::findOrFail($id);
            
            DB::commit();
            
            return $examination;
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error updating HT examination: " . $e->getMessage(), [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete an HT examination
     */
    public function deleteExamination(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            // Get the examination
            $examination = HtExamination::findOrFail($id);
            
            // Delete examination
            $result = $this->htExaminationRepository->delete($id);
            
            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error("Error deleting HT examination: " . $e->getMessage(), [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}