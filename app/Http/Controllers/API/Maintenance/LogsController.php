<?php

namespace App\Http\Controllers\API\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class LogsController extends Controller
{
    /**
     * Get list of log files
     */
    public function index(): JsonResponse
    {
        $logPath = storage_path('logs');
        $logFiles = File::files($logPath);
        
        $logs = [];
        foreach ($logFiles as $file) {
            $logs[] = [
                'name' => $file->getFilename(),
                'size' => $this->formatBytes($file->getSize()),
                'last_modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }
        
        // Sort by last modified (newest first)
        usort($logs, function ($a, $b) {
            return strtotime($b['last_modified']) - strtotime($a['last_modified']);
        });
        
        return response()->json([
            'logs' => $logs,
        ]);
    }

    /**
     * View log file contents
     */
    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|string',
            'lines' => 'nullable|integer|min:10|max:1000',
        ]);
        
        $filename = $request->file;
        $lines = $request->lines ?? 100;
        
        // Security check: Ensure the filename doesn't contain path traversal
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return response()->json([
                'message' => 'Invalid filename',
            ], 400);
        }
        
        $logPath = storage_path('logs/' . $filename);
        
        if (!File::exists($logPath)) {
            return response()->json([
                'message' => 'Log file not found',
            ], 404);
        }
        
        // Read the last N lines of the file
        $content = $this->tailFile($logPath, $lines);
        
        return response()->json([
            'file' => $filename,
            'last_modified' => date('Y-m-d H:i:s', File::lastModified($logPath)),
            'size' => $this->formatBytes(File::size($logPath)),
            'content' => $content,
        ]);
    }

    /**
     * Download log file
     */
    public function download(Request $request)
    {
        $request->validate([
            'file' => 'required|string',
        ]);
        
        $filename = $request->file;
        
        // Security check: Ensure the filename doesn't contain path traversal
        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return response()->json([
                'message' => 'Invalid filename',
            ], 400);
        }
        
        $logPath = storage_path('logs/' . $filename);
        
        if (!File::exists($logPath)) {
            return response()->json([
                'message' => 'Log file not found',
            ], 404);
        }
        
        return response()->download($logPath, $filename);
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Read the last N lines of a file
     */
    private function tailFile(string $filepath, int $lines = 100): array
    {
        $result = [];
        
        $file = new \SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - $lines);
        
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = $file->current();
            $result[] = $line;
            $file->next();
        }
        
        return $result;
    }
}