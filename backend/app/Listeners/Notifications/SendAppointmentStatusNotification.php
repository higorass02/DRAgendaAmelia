<?php

namespace App\Listeners\Notifications;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentStatusChanged;
use App\Notifications\AppointmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAppointmentStatusNotification implements ShouldQueue
{
    // Sem $connection fixo de propósito — ver SendAppointmentScheduledNotification.
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    // Só essas transições são relevantes pro paciente. start/complete são
    // fluxo interno da clínica; no-show não faz sentido notificar quem já
    // não compareceu.
    private const NOTIFIABLE = [
        AppointmentStatus::Confirmed->value => 'confirmed',
        AppointmentStatus::Cancelled->value => 'cancelled',
    ];

    public function handle(AppointmentStatusChanged $event): void
    {
        $kind = self::NOTIFIABLE[$event->appointment->status->value] ?? null;

        if ($kind === null) {
            return;
        }

        $patient = $event->appointment->patient;

        if (! $patient->email) {
            return;
        }

        $patient->notify(new AppointmentNotification($kind, $event->appointment));
    }
}
