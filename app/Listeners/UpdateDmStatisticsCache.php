<?php

namespace App\Listeners;

use App\Events\DmExaminationCreated;
use App\Services\Cache\StatisticsCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateDmStatisticsCache implements ShouldQueue
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
    public function handle(DmExaminationCreated $event): void
    {
        $this->cacheService->updateCacheOnExaminationCreate(
            $event->examination,
            'dm'
        );
    }
}