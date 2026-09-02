<?php

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentScheduled;
use App\Events\AppointmentStatusChanged;
use App\Listeners\Notifications\SendAppointmentScheduledNotification;
use App\Listeners\Notifications\SendAppointmentStatusNotification;
use App\Models\Appointment;
use App\Models\Patient;
use App\Notifications\AppointmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Os listeners usam afterCommit=true de propósito (correto: evita notificar
 * por uma operação que ainda pode sofrer rollback). Isso significa que, sob
 * RefreshDatabase (que embrulha o teste numa transação nunca commitada de
 * verdade), o listener nunca dispararia se testado através da Action
 * completa — não é bug, é o próprio mecanismo funcionando como projetado.
 * Por isso aqui o listener é chamado diretamente: a Action realmente disparar
 * o evento já está coberto em AppointmentEventsTest.
 */

class AppointmentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_listeners_are_queued_on_the_notifications_queue(): void
    {
        // Sem connection fixa de propósito (usa o QUEUE_CONNECTION do
        // ambiente — rabbitmq em dev/prod, sync nos testes). Ver comentário
        // nas classes dos listeners.
        $scheduled = new SendAppointmentScheduledNotification;
        $statusChanged = new SendAppointmentStatusNotification;

        $this->assertInstanceOf(ShouldQueue::class, $scheduled);
        $this->assertSame('notifications', $scheduled->queue);

        $this->assertInstanceOf(ShouldQueue::class, $statusChanged);
        $this->assertSame('notifications', $statusChanged->queue);
    }

    public function test_scheduling_notifies_the_patient(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create(['email' => 'paciente@example.com']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled, 'patient_id' => $patient->id]);

        (new SendAppointmentScheduledNotification)->handle(new AppointmentScheduled($appointment));

        Notification::assertSentTo(
            $patient,
            AppointmentNotification::class,
            fn (AppointmentNotification $n) => $n->kind === 'scheduled' && $n->appointment->id === $appointment->id,
        );
    }

    public function test_does_not_notify_when_patient_has_no_email(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create(['email' => null]);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled, 'patient_id' => $patient->id]);

        (new SendAppointmentScheduledNotification)->handle(new AppointmentScheduled($appointment));

        Notification::assertNothingSent();
    }

    public function test_confirmation_notifies_the_patient(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create(['email' => 'paciente@example.com']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed, 'patient_id' => $patient->id]);

        (new SendAppointmentStatusNotification)->handle(new AppointmentStatusChanged($appointment, AppointmentStatus::Scheduled));

        Notification::assertSentTo(
            $patient,
            AppointmentNotification::class,
            fn (AppointmentNotification $n) => $n->kind === 'confirmed',
        );
    }

    public function test_cancellation_notifies_the_patient(): void
    {
        Notification::fake();

        $patient = Patient::factory()->create(['email' => 'paciente@example.com']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Cancelled, 'patient_id' => $patient->id]);

        (new SendAppointmentStatusNotification)->handle(new AppointmentStatusChanged($appointment, AppointmentStatus::Scheduled));

        Notification::assertSentTo(
            $patient,
            AppointmentNotification::class,
            fn (AppointmentNotification $n) => $n->kind === 'cancelled',
        );
    }

    public function test_internal_transitions_do_not_notify_the_patient(): void
    {
        // start/complete/no-show são fluxo interno da clínica — não geram
        // notificação pro paciente (só scheduled/confirmed/cancelled geram).
        Notification::fake();

        $patient = Patient::factory()->create(['email' => 'paciente@example.com']);
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::NoShow, 'patient_id' => $patient->id]);

        (new SendAppointmentStatusNotification)->handle(new AppointmentStatusChanged($appointment, AppointmentStatus::Confirmed));

        Notification::assertNothingSent();
    }
}
