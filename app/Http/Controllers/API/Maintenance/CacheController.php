<?php

namespace App\Http\Controllers\API\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\RebuildCacheRequest;
use App\Services\Cache\StatisticsCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CacheController extends Controller
{
    protected $statisticsCacheService;

    public function __construct(StatisticsCacheService $statisticsCacheService)
    {
        $this->statisticsCacheService = $statisticsCacheService;
    }

    /**
     * Rebuild statistics cache
     */
    public function rebuildStatisticsCache(RebuildCacheRequest $request): JsonResponse
    {
        $cacheType = $request->cache_type ?? 'all';
        $year = $request->year ?? date('Y');
        
        $startTime = microtime(true);
        
        if ($cacheType === 'all') {
            $result = $this->statisticsCacheService->rebuildAllCache();
        } else {
            $result = $this->statisticsCacheService->rebuildYearlyCache($year);
        }
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        return response()->json([
            'message' => 'Cache statistik berhasil dibangun ulang',
            'execution_time' => $executionTime . ' detik',
            'results' => $result,
        ]);
    }

    /**
     * Clear all application cache
     */
    public function clearAllCache(): JsonResponse
    {
        $startTime = microtime(true);
        
        // Clear Laravel cache
        Cache::flush();
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        return response()->json([
            'message' => 'Semua cache berhasil dihapus',
            'execution_time' => $executionTime . ' detik',
        ]);
    }

    /**
     * Refresh monthly cache for current month
     */
    public function refreshCurrentMonthCache(): JsonResponse
    {
        $startTime = microtime(true);
        
        $year = date('Y');
        $month = date('n');
        
        $this->statisticsCacheService->rebuildMonthlyCache(null, 'ht', $year, $month);
        $this->statisticsCacheService->rebuildMonthlyCache(null, 'dm', $year, $month);
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        return response()->json([
            'message' => "Cache untuk bulan $month tahun $year berhasil diperbarui",
            'execution_time' => $executionTime . ' detik',
        ]);
    }
}