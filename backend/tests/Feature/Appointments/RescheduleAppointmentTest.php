<?php

namespace Tests\Feature\Appointments;

use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\ScheduleConflictException;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescheduleAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Professional $professional;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->professional = Professional::factory()->create();
        $this->professional->availabilities()->create([
            'weekday' => 1,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ]);
        $this->staff = User::factory()->create();
    }

    private function nextMonday(string $time): Carbon
    {
        return Carbon::parse('next monday')->setTimeFromTimeString($time);
    }

    public function test_reschedules_a_confirmed_appointment(): void
    {
        $original = Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Confirmed,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
        ]);

        $new = (new RescheduleAppointment)->handle(
            original: $original,
            newStartAt: $this->nextMonday('14:00:00'),
            newEndAt: $this->nextMonday('14:30:00'),
            actor: $this->staff,
            reason: 'Paciente pediu outro horário',
        );

        $original->refresh();

        $this->assertSame(AppointmentStatus::Rescheduled, $original->status);
        $this->assertSame(AppointmentStatus::Scheduled, $new->status);
        $this->assertSame($new->id, $original->rescheduled_to_id);
        $this->assertSame($original->id, $new->rescheduled_from_id);
        $this->assertSame($original->patient_id, $new->patient_id);
        $this->assertSame($original->professional_id, $new->professional_id);

        $this->assertDatabaseHas('status_histories', [
            'appointment_id' => $original->id,
            'from_status' => AppointmentStatus::Confirmed->value,
            'to_status' => AppointmentStatus::Rescheduled->value,
            'reason' => 'Paciente pediu outro horário',
        ]);
        $this->assertDatabaseHas('status_histories', [
            'appointment_id' => $new->id,
            'from_status' => null,
            'to_status' => AppointmentStatus::Scheduled->value,
        ]);
    }

    public function test_cannot_reschedule_a_merely_scheduled_appointment(): void
    {
        // A máquina de estados travada só permite RESCHEDULED a partir de
        // CONFIRMED — remarcar algo ainda não confirmado não é uma transição
        // válida no mapa do desafio.
        $original = Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Scheduled,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        (new RescheduleAppointment)->handle(
            original: $original,
            newStartAt: $this->nextMonday('14:00:00'),
            newEndAt: $this->nextMonday('14:30:00'),
            actor: $this->staff,
        );
    }

    public function test_cannot_reschedule_into_a_conflicting_slot(): void
    {
        $original = Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Confirmed,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
        ]);
        Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Confirmed,
            'start_at' => $this->nextMonday('14:00:00'),
            'end_at' => $this->nextMonday('14:30:00'),
        ]);

        $this->expectException(ScheduleConflictException::class);

        (new RescheduleAppointment)->handle(
            original: $original,
            newStartAt: $this->nextMonday('14:15:00'),
            newEndAt: $this->nextMonday('14:45:00'),
            actor: $this->staff,
        );
    }

    public function test_original_slot_is_freed_after_reschedule(): void
    {
        $original = Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Confirmed,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
        ]);

        (new RescheduleAppointment)->handle(
            original: $original,
            newStartAt: $this->nextMonday('14:00:00'),
            newEndAt: $this->nextMonday('14:30:00'),
            actor: $this->staff,
        );

        // Se o horário original foi liberado, dá pra agendar outro paciente nele.
        $another = Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Scheduled,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
        ]);

        $this->assertNotNull($another->id);
    }
}
