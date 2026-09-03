<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransitionAppointmentStatus
{
    public function handle(
        Appointment $appointment,
        AppointmentStatus $to,
        User $actor,
        ?string $reason = null,
    ): Appointment {
        $from = $appointment->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransitionException::make($from, $to);
        }

        return DB::transaction(function () use ($appointment, $from, $to, $actor, $reason) {
            $appointment->update(['status' => $to]);

            StatusHistory::create([
                'appointment_id' => $appointment->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            $updated = $appointment->fresh(['patient', 'professional']);

            AppointmentStatusChanged::dispatch($updated, $from);

            AuditLog::record(
                actor: $actor,
                action: $to->value,
                subjectType: 'appointment',
                subjectId: $updated->id,
                subjectLabel: "{$updated->patient->name} com {$updated->professional->name}",
                changes: array_filter(['from' => $from->value, 'to' => $to->value, 'reason' => $reason]),
            );

            return $updated;
        });
    }
}
