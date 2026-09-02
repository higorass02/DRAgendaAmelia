<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rede final anti-overbooking a nível de banco (independe da Action/lock de
 * aplicação). Insere direto via Eloquent/DB pra provar que a constraint em si
 * segura o caso mesmo se alguém pular a camada de Action.
 */
class ScheduleConflictUniqueIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_rejects_two_occupying_appointments_at_the_exact_same_slot(): void
    {
        $professional = Professional::factory()->create();
        $staff = User::factory()->create();
        $slot = now()->addDay()->setTime(9, 0);

        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'patient_id' => Patient::factory(),
            'start_at' => $slot,
            'end_at' => (clone $slot)->addMinutes(30),
            'status' => AppointmentStatus::Scheduled,
            'created_by' => $staff->id,
        ]);

        $this->expectException(QueryException::class);

        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'patient_id' => Patient::factory(),
            'start_at' => $slot,
            'end_at' => (clone $slot)->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
            'created_by' => $staff->id,
        ]);
    }

    public function test_db_allows_two_cancelled_appointments_at_the_same_slot(): void
    {
        $professional = Professional::factory()->create();
        $staff = User::factory()->create();
        $slot = now()->addDay()->setTime(9, 0);

        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'patient_id' => Patient::factory(),
            'start_at' => $slot,
            'end_at' => (clone $slot)->addMinutes(30),
            'status' => AppointmentStatus::Cancelled,
            'created_by' => $staff->id,
        ]);

        $second = Appointment::factory()->create([
            'professional_id' => $professional->id,
            'patient_id' => Patient::factory(),
            'start_at' => $slot,
            'end_at' => (clone $slot)->addMinutes(30),
            'status' => AppointmentStatus::Cancelled,
            'created_by' => $staff->id,
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_db_allows_scheduled_after_previous_was_cancelled_at_same_slot(): void
    {
        $professional = Professional::factory()->create();
        $staff = User::factory()->create();
        $slot = now()->addDay()->setTime(9, 0);

        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'patient_id' => Patient::factory(),
            'start_at' => $slot,
            'end_at' => (clone $slot)->addMinutes(30),
            'status' => AppointmentStatus::Cancelled,
            'created_by' => $staff->id,
        ]);

        $second = Appointment::factory()->create([
            'professional_id' => $professional->id,
            'patient_id' => Patient::factory(),
            'start_at' => $slot,
            'end_at' => (clone $slot)->addMinutes(30),
            'status' => AppointmentStatus::Scheduled,
            'created_by' => $staff->id,
        ]);

        $this->assertNotNull($second->id);
    }
}
