<?php

namespace Tests\Feature\Appointments;

use App\Actions\Appointments\CancelAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_a_scheduled_appointment_with_reason(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $result = (new CancelAppointment)->handle($appointment, $staff, 'Paciente remarcará depois');

        $this->assertSame(AppointmentStatus::Cancelled, $result->status);
        $this->assertDatabaseHas('status_histories', [
            'appointment_id' => $appointment->id,
            'to_status' => AppointmentStatus::Cancelled->value,
            'reason' => 'Paciente remarcará depois',
            'changed_by' => $staff->id,
        ]);
    }

    public function test_cancels_a_confirmed_appointment(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed]);

        $result = (new CancelAppointment)->handle($appointment, $staff);

        $this->assertSame(AppointmentStatus::Cancelled, $result->status);
    }

    public function test_cannot_cancel_a_terminal_appointment(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Completed]);

        $this->expectException(InvalidStatusTransitionException::class);

        (new CancelAppointment)->handle($appointment, $staff);
    }

    public function test_frees_the_slot_for_a_new_booking_after_cancellation(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        (new CancelAppointment)->handle($appointment, $staff);

        $stillOccupying = Appointment::where('professional_id', $appointment->professional_id)
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
            ->count();

        $this->assertSame(0, $stillOccupying);
    }
}
