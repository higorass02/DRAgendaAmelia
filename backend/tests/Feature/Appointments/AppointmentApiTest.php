<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testa a camada HTTP (rotas, Policy, FormRequest, mapeamento de exceptions).
 * As regras de negócio em si (conflito, disponibilidade, transições) já têm
 * cobertura própria e rigorosa nos testes das Actions (Fase 2) — aqui não se
 * repete tudo, só confirma que a API expõe isso corretamente.
 */
class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    private Professional $professional;

    private Patient $patient;

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
        $this->patient = Patient::factory()->create();
        $this->staff = User::factory()->create();
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->staff->createToken('api')->plainTextToken];
    }

    private function nextMonday(string $time): string
    {
        return Carbon::parse('next monday')->setTimeFromTimeString($time)->toDateTimeString();
    }

    public function test_guest_cannot_schedule(): void
    {
        $this->postJson('/api/v1/appointments', [])->assertUnauthorized();
    }

    public function test_staff_can_schedule_an_appointment(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/v1/appointments', [
            'patient_id' => $this->patient->id,
            'professional_id' => $this->professional->id,
            'start_at' => $this->nextMonday('09:00'),
            'end_at' => $this->nextMonday('09:30'),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status.value', 'scheduled');
        $response->assertJsonPath('data.patient.id', $this->patient->id);
        $response->assertJsonPath('data.professional.id', $this->professional->id);
    }

    public function test_scheduling_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/v1/appointments', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['patient_id', 'professional_id', 'start_at', 'end_at']);
    }

    public function test_scheduling_conflict_returns_409(): void
    {
        Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Scheduled,
            'start_at' => $this->nextMonday('09:00'),
            'end_at' => $this->nextMonday('09:30'),
        ]);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/appointments', [
            'patient_id' => $this->patient->id,
            'professional_id' => $this->professional->id,
            'start_at' => $this->nextMonday('09:15'),
            'end_at' => $this->nextMonday('09:45'),
        ]);

        $response->assertStatus(409);
    }

    public function test_scheduling_outside_availability_returns_422(): void
    {
        $response = $this->withHeaders($this->headers())->postJson('/api/v1/appointments', [
            'patient_id' => $this->patient->id,
            'professional_id' => $this->professional->id,
            'start_at' => $this->nextMonday('20:00'),
            'end_at' => $this->nextMonday('20:30'),
        ]);

        $response->assertStatus(422);
    }

    public function test_confirm_transitions_status(): void
    {
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->withHeaders($this->headers())->postJson("/api/v1/appointments/{$appointment->id}/confirm");

        $response->assertOk();
        $response->assertJsonPath('data.status.value', 'confirmed');
    }

    public function test_confirm_on_already_confirmed_returns_422(): void
    {
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed]);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/appointments/{$appointment->id}/confirm")
            ->assertStatus(422);
    }

    public function test_full_happy_path_confirm_start_complete(): void
    {
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);
        $h = $this->headers();

        $this->withHeaders($h)->postJson("/api/v1/appointments/{$appointment->id}/confirm")->assertOk();
        $this->withHeaders($h)->postJson("/api/v1/appointments/{$appointment->id}/start")
            ->assertOk()->assertJsonPath('data.status.value', 'in_progress');
        $this->withHeaders($h)->postJson("/api/v1/appointments/{$appointment->id}/complete")
            ->assertOk()->assertJsonPath('data.status.value', 'completed');
    }

    public function test_no_show_transitions_from_confirmed(): void
    {
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Confirmed]);

        $this->withHeaders($this->headers())
            ->postJson("/api/v1/appointments/{$appointment->id}/no-show")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'no_show');
    }

    public function test_cancel_with_reason(): void
    {
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);

        $response = $this->withHeaders($this->headers())->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'Paciente desistiu']
        );

        $response->assertOk();
        $response->assertJsonPath('data.status.value', 'cancelled');
        $this->assertDatabaseHas('status_histories', [
            'appointment_id' => $appointment->id,
            'reason' => 'Paciente desistiu',
        ]);
    }

    public function test_reschedule_creates_linked_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'professional_id' => $this->professional->id,
            'status' => AppointmentStatus::Confirmed,
            'start_at' => $this->nextMonday('09:00'),
            'end_at' => $this->nextMonday('09:30'),
        ]);

        $response = $this->withHeaders($this->headers())->postJson(
            "/api/v1/appointments/{$appointment->id}/reschedule",
            [
                'start_at' => $this->nextMonday('14:00'),
                'end_at' => $this->nextMonday('14:30'),
                'reason' => 'Pediu outro horário',
            ]
        );

        $response->assertCreated();
        $response->assertJsonPath('data.rescheduled_from_id', $appointment->id);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'rescheduled']);
    }

    public function test_patient_role_cannot_transition_appointments(): void
    {
        $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);
        $patientUser = User::factory()->create();
        $patientUser->forceFill(['role' => \App\Enums\UserRole::Patient])->save();
        $token = $patientUser->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/appointments/{$appointment->id}/confirm")
            ->assertForbidden();
    }

    public function test_index_filters_by_status(): void
    {
        Appointment::factory()->create(['status' => AppointmentStatus::Scheduled]);
        Appointment::factory()->create(['status' => AppointmentStatus::Cancelled]);

        $response = $this->withHeaders($this->headers())->getJson('/api/v1/appointments?status=cancelled');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status.value', 'cancelled');
    }

    public function test_index_filters_by_professional(): void
    {
        $other = Professional::factory()->create();
        Appointment::factory()->create(['professional_id' => $this->professional->id]);
        Appointment::factory()->create(['professional_id' => $other->id]);

        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/appointments?professional_id={$this->professional->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }
}
