<?php

namespace App\Listeners;

use App\Events\DmExaminationCreated;
use App\Events\HtExaminationCreated;
use App\Notifications\ExaminationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendExaminationNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(DmExaminationCreated|HtExaminationCreated $event): void
    {
        $examination = $event->examination;
        $patient = $examination->patient;
        $puskesmas = $examination->puskesmas;
        
        // Get admin users to notify (could be implemented as needed)
        $adminUsers = \App\Models\User::where('role', 'admin')->get();
        
        // Send notification to admin users
        Notification::send($adminUsers, new ExaminationCreated(
            $patient,
            $puskesmas,
            $examination instanceof \App\Models\DmExamination ? 'dm' : 'ht'
        ));
    }
}