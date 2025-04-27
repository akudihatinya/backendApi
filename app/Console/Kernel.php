<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Schedule archive command to run daily at midnight
        $schedule->command('examinations:archive')->dailyAt('00:00');
        
        // Schedule cache rebuild if needed (optional)
        // $schedule->command('statistics:rebuild-cache')->weekly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ArchiveExaminations::class,
        \App\Console\Commands\RebuildStatisticsCache::class,
    ];
}