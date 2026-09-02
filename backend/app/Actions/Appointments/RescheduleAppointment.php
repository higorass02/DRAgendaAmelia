<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RescheduleAppointment
{
    public function handle(
        Appointment $original,
        Carbon $newStartAt,
        Carbon $newEndAt,
        User $actor,
        ?string $reason = null,
    ): Appointment {
        return DB::transaction(function () use ($original, $newStartAt, $newEndAt, $actor, $reason) {
            // Cria a nova consulta reaproveitando a mesma validação de
            // disponibilidade + conflito de agenda do agendamento normal.
            $new = (new ScheduleAppointment)->handle(
                patient: $original->patient,
                professional: $original->professional,
                startAt: $newStartAt,
                endAt: $newEndAt,
                actor: $actor,
            );

            $new->update(['rescheduled_from_id' => $original->id]);
            $original->update(['rescheduled_to_id' => $new->id]);

            // Só transiciona a original DEPOIS de garantir que a nova consulta
            // existe — se o novo horário conflitar, nada aqui é executado
            // (rollback da transação) e a original permanece intacta.
            (new TransitionAppointmentStatus)->handle(
                appointment: $original,
                to: AppointmentStatus::Rescheduled,
                actor: $actor,
                reason: $reason,
            );

            return $new->fresh();
        });
    }
}
