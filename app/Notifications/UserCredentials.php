<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserCredentials extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly User $user,
        public readonly string $password
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Informasi Akun Aplikasi akudihatinya')
            ->greeting("Halo {$this->user->name},")
            ->line('Akun anda telah dibuat di aplikasi akudihatinya.')
            ->line('Berikut adalah informasi login anda:')
            ->line("Username: {$this->user->username}")
            ->line("Password: {$this->password}")
            ->action('Login ke Aplikasi', url('/login'))
            ->line('Silahkan ubah password anda setelah login untuk keamanan akun.')
            ->line('Terima kasih telah menggunakan aplikasi akudihatinya!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'username' => $this->user->username,
        ];
    }
}