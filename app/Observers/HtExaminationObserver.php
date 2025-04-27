<?php

namespace App\Observers;

use App\Models\HtExamination;
use App\Services\StatisticsCacheService;

class HtExaminationObserver
{
    private StatisticsCacheService $cacheService;

    public function __construct(StatisticsCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function created(HtExamination $examination)
    {
        $this->cacheService->updateCacheOnExaminationCreate($examination, 'ht');
    }
}