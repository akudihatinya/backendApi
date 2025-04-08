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
        // Generate reports at the beginning of each month for the previous month
        $schedule->command('app:generate-reports')
                 ->monthlyOn(1, '00:01')
                 ->appendOutputTo(storage_path('logs/reports.log'));
                 
        // Archive data at the end of the year (December 31)
        $schedule->command('app:archive-data')
                 ->yearlyOn(12, 31, '23:55')
                 ->appendOutputTo(storage_path('logs/archive.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}