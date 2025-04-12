<?php

namespace App\Console\Commands;

use App\Services\ArchiveService;
use Illuminate\Console\Command;

class ArchiveExaminations extends Command
{
    protected $signature = 'examinations:archive';
    
    protected $description = 'Archive examinations from previous year';
    
    public function handle(ArchiveService $archiveService)
    {
        $this->info('Starting archiving process...');
        
        $result = $archiveService->archiveExaminations();
        
        $this->info('Archived ' . $result['archived_ht'] . ' HT examinations.');
        $this->info('Archived ' . $result['archived_dm'] . ' DM examinations.');
        
        $this->info('Archiving completed successfully.');
        
        return 0;
    }
}
