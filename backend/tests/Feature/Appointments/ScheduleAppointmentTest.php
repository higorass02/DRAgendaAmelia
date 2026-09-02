<?php

namespace Tests\Feature\Appointments;

use App\Actions\Appointments\ScheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\OutsideAvailabilityException;
use App\Exceptions\ScheduleConflictException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Professional $professional;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->professional = Professional::factory()->create();
        // Segunda-feira (weekday=1), 08:00-18:00.
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

    public function test_schedules_within_availability_window(): void
    {
        $patient = Patient::factory()->create();
        $start = $this->nextMonday('09:00:00');
        $end = $this->nextMonday('09:30:00');

        $appointment = (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $this->professional,
            startAt: $start,
            endAt: $end,
            actor: $this->staff,
        );

        $this->assertSame(AppointmentStatus::Scheduled, $appointment->status);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'patient_id' => $patient->id,
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Scheduled->value,
            'created_by' => $this->staff->id,
        ]);
        $this->assertDatabaseHas('status_histories', [
            'appointment_id' => $appointment->id,
            'from_status' => null,
            'to_status' => AppointmentStatus::Scheduled->value,
            'changed_by' => $this->staff->id,
        ]);
    }

    public function test_rejects_outside_professional_hours(): void
    {
        $patient = Patient::factory()->create();

        $this->expectException(OutsideAvailabilityException::class);

        (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $this->professional,
            startAt: $this->nextMonday('19:00:00'),
            endAt: $this->nextMonday('19:30:00'),
            actor: $this->staff,
        );
    }

    public function test_rejects_on_day_without_availability(): void
    {
        $patient = Patient::factory()->create();
        $sunday = Carbon::parse('next sunday')->setTimeFromTimeString('10:00:00');

        $this->expectException(OutsideAvailabilityException::class);

        (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $this->professional,
            startAt: $sunday,
            endAt: (clone $sunday)->addMinutes(30),
            actor: $this->staff,
        );
    }

    public static function overlappingSlots(): array
    {
        return [
            'mesmo horario exato' => ['09:00:00', '09:30:00'],
            'sobreposicao parcial no inicio' => ['08:45:00', '09:15:00'],
            'sobreposicao parcial no fim' => ['09:15:00', '09:45:00'],
            'contida dentro da existente' => ['09:05:00', '09:10:00'],
            'contem a existente inteira' => ['08:50:00', '09:40:00'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('overlappingSlots')]
    public function test_rejects_overlap_with_existing_scheduled_appointment(string $start, string $end): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $this->expectException(ScheduleConflictException::class);

        (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $this->professional,
            startAt: $this->nextMonday($start),
            endAt: $this->nextMonday($end),
            actor: $this->staff,
        );
    }

    public function test_rejects_overlap_with_confirmed_appointment_too(): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->expectException(ScheduleConflictException::class);

        (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $this->professional,
            startAt: $this->nextMonday('09:15:00'),
            endAt: $this->nextMonday('09:45:00'),
            actor: $this->staff,
        );
    }

    public function test_allows_back_to_back_appointments(): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
            'status' => AppointmentStatus::Scheduled,
        ]);

        $appointment = (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $this->professional,
            startAt: $this->nextMonday('09:30:00'),
            endAt: $this->nextMonday('10:00:00'),
            actor: $this->staff,
        );

        $this->assertNotNull($appointment->id);
    }

    public function test_ignores_cancelled_appointments_for_conflict(): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'start_at' => $this->nextMonday('09:00:00'),
            'end_at' => $this->nextMonday('09:30:00'),
            'status' => AppointmentStatus::Cancelled,
        ]);

        $appointment = (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $this->professional,
            startAt: $this->nextMonday('09:00:00'),
            endAt: $this->nextMonday('09:30:00'),
            actor: $this->staff,
        );

        $this->assertNotNull($appointment->id);
    }
}
