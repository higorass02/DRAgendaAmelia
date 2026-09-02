<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotification extends Notification
{
    /**
     * @param  'scheduled'|'confirmed'|'cancelled'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly Appointment $appointment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->appointment->start_at->translatedFormat('d/m/Y \à\s H:i');
        $professional = $this->appointment->professional->name;

        return match ($this->kind) {
            'scheduled' => (new MailMessage)
                ->subject('Consulta agendada')
                ->greeting('Olá, '.$this->appointment->patient->name.'!')
                ->line("Sua consulta com {$professional} foi agendada para {$when}."),
            'confirmed' => (new MailMessage)
                ->subject('Consulta confirmada')
                ->greeting('Olá, '.$this->appointment->patient->name.'!')
                ->line("Sua consulta com {$professional} em {$when} foi confirmada."),
            'cancelled' => (new MailMessage)
                ->subject('Consulta cancelada')
                ->greeting('Olá, '.$this->appointment->patient->name.'!')
                ->line("Sua consulta com {$professional} em {$when} foi cancelada."),
        };
    }
}
