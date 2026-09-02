<?php

namespace Tests\Feature\Notifications;

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\ScheduleAppointment;
use App\Actions\Appointments\TransitionAppointmentStatus;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentScheduled;
use App\Events\AppointmentStatusChanged;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AppointmentEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_dispatches_appointment_scheduled_event(): void
    {
        Event::fake();

        $professional = Professional::factory()->create();
        $professional->availabilities()->create(['weekday' => 1, 'start_time' => '08:00:00', 'end_time' => '18:00:00']);
        $patient = Patient::factory()->create();
        $staff = User::factory()->create();

        $appointment = (new ScheduleAppointment)->handle(
            patient: $patient,
            professional: $professional,
            startAt: Carbon::parse('next monday 09:00'),
            endAt: Carbon::parse('next monday 09:30'),
            actor: $staff,
        );

        Event::assertDispatched(AppointmentScheduled::class, function (AppointmentScheduled $event) use ($appointment) {
            return $event->appointment->id === $appointment->id;
        });
    }

    public function test_transition_dispatches_appointment_status_changed_event(): void
    {
        Event::fake();

        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        (new TransitionAppointmentStatus)->handle($appointment, AppointmentStatus::Confirmed, $staff);

        Event::assertDispatched(AppointmentStatusChanged::class, function (AppointmentStatusChanged $event) use ($appointment) {
            return $event->appointment->id === $appointment->id
                && $event->from === AppointmentStatus::Scheduled
                && $event->appointment->status === AppointmentStatus::Confirmed;
        });
    }

    public function test_cancel_dispatches_appointment_status_changed_event(): void
    {
        Event::fake();

        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        (new CancelAppointment)->handle($appointment, $staff, 'motivo');

        Event::assertDispatched(AppointmentStatusChanged::class);
    }
}
