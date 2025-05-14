<?php

namespace App\Services\Export;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PdfExportService
{
    /**
     * Export statistics data to PDF
     */
    public function exportStatistics(
        array $data, 
        string $filename, 
        string $title, 
        ?string $subtitle = null
    ): string {
        // Prepare data for the view
        $viewData = [
            'title' => $title,
            'subtitle' => $subtitle,
            'generated_at' => Carbon::now()->format('d F Y H:i:s'),
            'data' => $data,
            'is_recap' => isset($data[0]['puskesmas_name']),
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('exports.statistics_pdf', $viewData);
        $pdf->setPaper('a4', 'landscape');
        
        // Save PDF
        $path = storage_path('app/public/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $pdf->output());
        
        return $path;
    }
    
    /**
     * Export monitoring data to PDF
     */
    public function exportMonitoring(
        array $data, 
        string $filename, 
        string $title, 
        string $subtitle
    ): string {
        // Prepare data for the view
        $viewData = [
            'title' => $title,
            'subtitle' => $subtitle,
            'generated_at' => Carbon::now()->format('d F Y H:i:s'),
            'puskesmas_name' => $data['puskesmas_name'],
            'year' => $data['year'],
            'month' => $data['month'],
            'month_name' => $data['month_name'],
            'days_in_month' => $data['days_in_month'],
            'patients' => $data['patients'],
            'disease_type' => $data['disease_type'],
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('exports.monitoring_pdf', $viewData);
        $pdf->setPaper('a4', 'landscape');
        
        // Save PDF
        $path = storage_path('app/public/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $pdf->output());
        
        return $path;
    }
    
    /**
     * Export dashboard data to PDF
     */
    public function exportDashboard(
        array $data, 
        string $filename, 
        string $title, 
        string $subtitle
    ): string {
        // Prepare data for the view
        $viewData = [
            'title' => $title,
            'subtitle' => $subtitle,
            'generated_at' => Carbon::now()->format('d F Y H:i:s'),
            'data' => $data,
        ];
        
        // Generate PDF
        $pdf = Pdf::loadView('exports.dashboard_pdf', $viewData);
        $pdf->setPaper('a4', 'landscape');
        
        // Save PDF
        $path = storage_path('app/public/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $pdf->output());
        
        return $path;
    }
}