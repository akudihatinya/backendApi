<?php

namespace App\Http\Controllers\API\Maintenance;

use App\Http\Controllers\Controller;
use App\Services\System\ArchiveService;
use App\Services\System\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    protected $archiveService;
    protected $maintenanceService;

    public function __construct(
        ArchiveService $archiveService,
        MaintenanceService $maintenanceService
    ) {
        $this->archiveService = $archiveService;
        $this->maintenanceService = $maintenanceService;
    }

    /**
     * Get system status
     */
    public function getStatus(): JsonResponse
    {
        $status = $this->maintenanceService->getSystemStatus();
        
        return response()->json([
            'status' => $status['status'],
            'message' => $status['message'],
            'server_time' => now()->toDateTimeString(),
            'environment' => app()->environment(),
            'version' => config('app.version', '1.0.0'),
            'details' => $status['details'],
        ]);
    }

    /**
     * Run system maintenance tasks
     */
    public function runMaintenance(): JsonResponse
    {
        $startTime = microtime(true);
        
        $result = $this->maintenanceService->runMaintenanceTasks();
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        return response()->json([
            'message' => 'Maintenance tasks executed successfully',
            'execution_time' => $executionTime . ' detik',
            'results' => $result,
        ]);
    }

    /**
     * Run archive process
     */
    public function runArchive(): JsonResponse
    {
        $result = $this->archiveService->archiveExaminations();
        
        return response()->json([
            'message' => 'Archive process completed',
            'results' => $result,
        ]);
    }

    /**
     * Unarchive examinations for a year
     */
    public function unarchiveYear(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:' . (date('Y') - 1),
        ]);
        
        $year = $request->year;
        
        $result = $this->archiveService->unarchiveExaminations($year);
        
        return response()->json([
            'message' => "Unarchive process for year $year completed",
            'results' => $result,
        ]);
    }
}