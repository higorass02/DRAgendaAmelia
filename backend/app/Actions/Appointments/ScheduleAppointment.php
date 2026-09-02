<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\OutsideAvailabilityException;
use App\Exceptions\ScheduleConflictException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\StatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleAppointment
{
    /**
     * Status que "ocupam" o horário do profissional pra fins de conflito.
     * Inclui SCHEDULED (não só CONFIRMED) — decisão da Fase 2: evita que duas
     * recepções marquem o mesmo horário e só descubram o conflito ao confirmar.
     */
    private const OCCUPYING_STATUSES = [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed];

    public function handle(
        Patient $patient,
        Professional $professional,
        Carbon $startAt,
        Carbon $endAt,
        User $actor,
    ): Appointment {
        $this->assertWithinAvailability($professional, $startAt, $endAt);

        return DB::transaction(function () use ($patient, $professional, $startAt, $endAt, $actor) {
            // SELECT ... FOR UPDATE: dentro da transação, InnoDB toma gap/next-key
            // lock nesse intervalo — uma segunda transação concorrente tentando
            // reservar o mesmo horário bloqueia aqui até esta transação commitar.
            $this->assertNoConflict($professional, $startAt, $endAt);

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'professional_id' => $professional->id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => AppointmentStatus::Scheduled,
                'created_by' => $actor->id,
            ]);

            StatusHistory::create([
                'appointment_id' => $appointment->id,
                'from_status' => null,
                'to_status' => AppointmentStatus::Scheduled,
                'reason' => null,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            return $appointment;
        });
    }

    private function assertWithinAvailability(Professional $professional, Carbon $startAt, Carbon $endAt): void
    {
        $fits = $professional->availabilities()
            ->where('weekday', $startAt->dayOfWeek)
            ->where('start_time', '<=', $startAt->format('H:i:s'))
            ->where('end_time', '>=', $endAt->format('H:i:s'))
            ->exists();

        if (! $fits) {
            throw new OutsideAvailabilityException;
        }
    }

    private function assertNoConflict(Professional $professional, Carbon $startAt, Carbon $endAt): void
    {
        $conflict = Appointment::query()
            ->where('professional_id', $professional->id)
            ->whereIn('status', array_map(fn (AppointmentStatus $s) => $s->value, self::OCCUPYING_STATUSES))
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            throw new ScheduleConflictException;
        }
    }
}
