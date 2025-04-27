<?php

namespace App\Observers;

use App\Models\DmExamination;
use App\Services\StatisticsCacheService;

class DmExaminationObserver
{
    private StatisticsCacheService $cacheService;

    public function __construct(StatisticsCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function created(DmExamination $examination)
    {
        $this->cacheService->updateCacheOnExaminationCreate($examination, 'dm');
    }
}