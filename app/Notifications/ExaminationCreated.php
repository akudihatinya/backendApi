<?php

namespace App\Notifications;

use App\Models\Patient;
use App\Models\Puskesmas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExaminationCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly Patient $patient,
        public readonly Puskesmas $puskesmas,
        public readonly string $examinationType
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $examinationTypeName = $this->examinationType === 'ht' 
            ? 'Hipertensi' 
            : 'Diabetes Mellitus';
            
        return (new MailMessage)
            ->subject("Pemeriksaan {$examinationTypeName} Baru")
            ->greeting("Halo {$notifiable->name},")
            ->line("Pemeriksaan {$examinationTypeName} baru telah dibuat untuk:")
            ->line("Pasien: {$this->patient->name}")
            ->line("Puskesmas: {$this->puskesmas->name}")
            ->action('Lihat Pemeriksaan', url('/'))
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
            'patient_id' => $this->patient->id,
            'patient_name' => $this->patient->name,
            'puskesmas_id' => $this->puskesmas->id,
            'puskesmas_name' => $this->puskesmas->name,
            'examination_type' => $this->examinationType,
        ];
    }
}