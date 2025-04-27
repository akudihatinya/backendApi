<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ArchiveExaminations;
use App\Console\Commands\RebuildStatisticsCache;

// Default inspire command
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register commands without needing to be in Kernel.php
Artisan::command('examinations:archive', function () {
    return (new ArchiveExaminations())->handle(app(\App\Services\ArchiveService::class));
})->purpose('Archive examinations from previous year');

Artisan::command('statistics:rebuild-cache', function () {
    return (new RebuildStatisticsCache())->handle(app(\App\Services\StatisticsCacheService::class));
})->purpose('Rebuild the monthly statistics cache from existing examination data');