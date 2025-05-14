<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Notifications\UserCredentials;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendUserCredentials implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        $user = $event->user;
        $plainPassword = $event->plainPassword;
        
        // Skip if no plain password was provided (password reset, for example)
        if (empty($plainPassword)) {
            return;
        }
        
        // Send notification to the user
        $user->notify(new UserCredentials($user, $plainPassword));
    }
}