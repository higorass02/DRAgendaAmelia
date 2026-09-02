<?php

namespace Tests\Feature\Appointments;

use App\Actions\Appointments\TransitionAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionAppointmentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_transition_updates_status_and_records_history(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $updated = (new TransitionAppointmentStatus)->handle(
            appointment: $appointment,
            to: AppointmentStatus::Confirmed,
            actor: $staff,
        );

        $this->assertSame(AppointmentStatus::Confirmed, $updated->status);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->assertDatabaseHas('status_histories', [
            'appointment_id' => $appointment->id,
            'from_status' => AppointmentStatus::Scheduled->value,
            'to_status' => AppointmentStatus::Confirmed->value,
            'changed_by' => $staff->id,
        ]);
    }

    public function test_records_reason_when_given(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed]);

        (new TransitionAppointmentStatus)->handle(
            appointment: $appointment,
            to: AppointmentStatus::Cancelled,
            actor: $staff,
            reason: 'Paciente desistiu',
        );

        $this->assertDatabaseHas('status_histories', [
            'appointment_id' => $appointment->id,
            'to_status' => AppointmentStatus::Cancelled->value,
            'reason' => 'Paciente desistiu',
        ]);
    }

    public function test_invalid_transition_throws_and_changes_nothing(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            (new TransitionAppointmentStatus)->handle(
                appointment: $appointment,
                to: AppointmentStatus::InProgress,
                actor: $staff,
            );
        } finally {
            $this->assertDatabaseHas('appointments', [
                'id' => $appointment->id,
                'status' => AppointmentStatus::Scheduled->value,
            ]);
            $this->assertDatabaseCount('status_histories', 0);
        }
    }

    public function test_terminal_status_never_transitions_again(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Completed]);

        $this->expectException(InvalidStatusTransitionException::class);

        (new TransitionAppointmentStatus)->handle(
            appointment: $appointment,
            to: AppointmentStatus::Cancelled,
            actor: $staff,
        );
    }
}
