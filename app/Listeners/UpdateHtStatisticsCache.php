<?php

namespace App\Listeners;

use App\Events\HtExaminationCreated;
use App\Services\Cache\StatisticsCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateHtStatisticsCache implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected StatisticsCacheService $cacheService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(HtExaminationCreated $event): void
    {
        $this->cacheService->updateCacheOnExaminationCreate(
            $event->examination,
            'ht'
        );
    }
}