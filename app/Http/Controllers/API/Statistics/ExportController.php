<?php

namespace App\Http\Controllers\API\Statistic;

use App\Http\Controllers\Controller;
use App\Services\Export\ExcelExportService;
use App\Services\Export\PdfExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    protected $excelExportService;
    protected $pdfExportService;

    public function __construct(
        ExcelExportService $excelExportService,
        PdfExportService $pdfExportService
    ) {
        $this->excelExportService = $excelExportService;
        $this->pdfExportService = $pdfExportService;
    }

    /**
     * Export statistics data to Excel or PDF
     */
    public function exportStatistics(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'type' => 'nullable|in:all,ht,dm',
            'format' => 'nullable|in:excel,pdf',
            'puskesmas_id' => 'nullable|integer|exists:puskesmas,id',
        ]);

        $year = $request->year;
        $month = $request->month;
        $diseaseType = $request->type ?? 'all';
        $format = $request->format ?? 'excel';
        $puskesmasId = $request->puskesmas_id;

        // Check permissions if puskesmas_id is specified
        if ($puskesmasId && !Auth::user()->isAdmin() && Auth::user()->puskesmas_id != $puskesmasId) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses untuk mengekspor data puskesmas lain.',
            ], 403);
        }

        // Generate file name
        $fileName = $this->generateFileName($year, $month, $diseaseType, $puskesmasId);

        // Get statistics data based on parameters
        $statistics = $this->getStatisticsData($year, $month, $diseaseType, $puskesmasId);

        // Generate export file based on format
        if ($format === 'pdf') {
            return $this->exportToPdf($statistics, $year, $month, $diseaseType, $fileName);
        } else {
            return $this->exportToExcel($statistics, $year, $month, $diseaseType, $fileName);
        }
    }

    /**
     * Export monitoring report to Excel or PDF
     */
    public function exportMonitoringReport(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'type' => 'nullable|in:all,ht,dm',
            'format' => 'nullable|in:excel,pdf',
            'puskesmas_id' => 'nullable|integer|exists:puskesmas,id',
        ]);

        $year = $request->year;
        $month = $request->month;
        $diseaseType = $request->type ?? 'all';
        $format = $request->format ?? 'excel';
        
        // Determine puskesmas ID (admin can specify, puskesmas users are limited to their own)
        $puskesmasId = Auth::user()->isAdmin() 
            ? $request->puskesmas_id ?? null
            : Auth::user()->puskesmas_id;
            
        if (!$puskesmasId) {
            return response()->json([
                'message' => 'Puskesmas ID harus ditentukan.',
            ], 400);
        }

        // Generate file name
        $fileName = "monitoring_{$diseaseType}_{$year}_{$month}_" . str_slug(Auth::user()->puskesmas->name ?? 'puskesmas');

        // Get monitoring data
        $monitoringData = $this->getMonitoringData($puskesmasId, $year, $month, $diseaseType);

        // Generate export file based on format
        if ($format === 'pdf') {
            return $this->pdfExportService->exportMonitoringReport($monitoringData, $year, $month, $diseaseType, $fileName);
        } else {
            return $this->excelExportService->exportMonitoringReport($monitoringData, $year, $month, $diseaseType, $fileName);
        }
    }

    /**
     * Export to PDF
     */
    protected function exportToPdf($statistics, $year, $month, $diseaseType, $fileName)
    {
        return $this->pdfExportService->exportStatistics($statistics, $year, $month, $diseaseType, $fileName);
    }

    /**
     * Export to Excel
     */
    protected function exportToExcel($statistics, $year, $month, $diseaseType, $fileName)
    {
        return $this->excelExportService->exportStatistics($statistics, $year, $month, $diseaseType, $fileName);
    }

    /**
     * Generate file name for export
     */
    protected function generateFileName($year, $month, $diseaseType, $puskesmasId = null)
    {
        $fileName = "statistik";
        
        if ($diseaseType !== 'all') {
            $fileName .= "_{$diseaseType}";
        }
        
        $fileName .= "_{$year}";
        
        if ($month) {
            $fileName .= sprintf("_%02d", $month);
        }
        
        if ($puskesmasId) {
            $puskesmas = \App\Models\Puskesmas::find($puskesmasId);
            if ($puskesmas) {
                $fileName .= "_" . str_slug($puskesmas->name);
            }
        }
        
        return $fileName;
    }

    /**
     * Get statistics data for export
     */
    protected function getStatisticsData($year, $month, $diseaseType, $puskesmasId = null)
    {
        // This method would gather all necessary statistics data
        // Implementation depends on what's needed for export
        // Would call service methods or repository methods to get the data
        
        // Example placeholder implementation:
        // In a real app, this would use services/repositories to get all required data
        return [
            'year' => $year,
            'month' => $month,
            'type' => $diseaseType,
            // Additional data would be gathered here
        ];
    }

    /**
     * Get monitoring data for export
     */
    protected function getMonitoringData($puskesmasId, $year, $month, $diseaseType)
    {
        // This method would gather all necessary monitoring data
        // Implementation depends on what's needed for export
        // Would call service methods or repository methods to get the data
        
        // Example placeholder implementation:
        return [
            'puskesmas_id' => $puskesmasId,
            'year' => $year,
            'month' => $month,
            'type' => $diseaseType,
            // Additional data would be gathered here
        ];
    }
}