<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_the_status_history(): void
    {
        $staff = User::factory()->create();
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed]);

        StatusHistory::factory()->create([
            'appointment_id' => $appointment->id,
            'from_status' => null,
            'to_status' => AppointmentStatus::Scheduled,
            'changed_by' => $staff->id,
            'changed_at' => now()->subHour(),
        ]);
        StatusHistory::factory()->create([
            'appointment_id' => $appointment->id,
            'from_status' => AppointmentStatus::Scheduled,
            'to_status' => AppointmentStatus::Confirmed,
            'reason' => null,
            'changed_by' => $staff->id,
            'changed_at' => now(),
        ]);

        $token = $staff->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data.status_history');
        $response->assertJsonPath('data.status_history.0.to_status.value', 'scheduled');
        $response->assertJsonPath('data.status_history.1.to_status.value', 'confirmed');
        $response->assertJsonPath('data.status_history.1.changed_by.name', $staff->name);
    }

    public function test_index_does_not_load_status_history(): void
    {
        // Lista não carrega o histórico de propósito (evita N+1/payload
        // desnecessário) — só o detalhe (show) precisa disso.
        $staff = User::factory()->create();
        Appointment::factory()->create();

        $token = $staff->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/appointments');

        $response->assertOk();
        $response->assertJsonMissingPath('data.0.status_history');
    }
}
