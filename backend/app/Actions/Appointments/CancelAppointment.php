<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;

class CancelAppointment
{
    public function handle(Appointment $appointment, User $actor, ?string $reason = null): Appointment
    {
        return (new TransitionAppointmentStatus)->handle(
            appointment: $appointment,
            to: AppointmentStatus::Cancelled,
            actor: $actor,
            reason: $reason,
        );
    }
}
