<?php

namespace App\Events;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;

class AppointmentStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly AppointmentStatus $from,
    ) {}
}
