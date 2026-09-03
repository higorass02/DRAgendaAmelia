<?php

namespace Tests\Feature\Audit;

use App\Enums\AppointmentStatus;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('api')->plainTextToken];
    }

    public function test_guest_cannot_view_audit_logs(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
    }

    public function test_staff_cannot_view_audit_logs(): void
    {
        $staff = User::factory()->create();

        $this->withHeaders($this->headers($staff))->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->count(3)->create();

        $response = $this->withHeaders($this->headers($admin))->getJson('/api/v1/audit-logs');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [['id', 'action', 'action_label', 'subject_type', 'subject_label', 'actor', 'created_at']],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);
    }

    public function test_creating_a_patient_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withHeaders($this->headers($admin))->postJson('/api/v1/patients', [
            'name' => 'Maria Silva',
            'cpf' => '111.444.777-35',
            'phone' => '11999998888',
            'birth_date' => '1990-05-10',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'created',
            'subject_type' => 'patient',
            'subject_label' => 'Maria Silva',
        ]);
    }

    public function test_updating_a_patient_is_logged_with_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create(['name' => 'Nome Antigo']);

        $this->withHeaders($this->headers($admin))->putJson("/api/v1/patients/{$patient->id}", [
            'name' => 'Nome Novo',
            'cpf' => $patient->cpf,
            'phone' => $patient->phone,
            'birth_date' => $patient->birth_date->format('Y-m-d'),
        ])->assertOk();

        $log = AuditLog::query()->where('subject_type', 'patient')->where('action', 'updated')->firstOrFail();
        $this->assertSame($patient->id, $log->subject_id);
        $this->assertArrayHasKey('name', $log->changes);
        // assertEquals (não assertSame): o MySQL JSON não garante preservar a
        // ordem das chaves no round-trip — os valores é que importam aqui.
        $this->assertEquals(['from' => 'Nome Antigo', 'to' => 'Nome Novo'], $log->changes['name']);
    }

    public function test_creating_a_professional_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withHeaders($this->headers($admin))->postJson('/api/v1/professionals', [
            'name' => 'Dr. João',
            'specialty' => 'Cardiologia',
            'availabilities' => [['weekday' => 1, 'start_time' => '08:00', 'end_time' => '18:00']],
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'subject_type' => 'professional',
            'subject_label' => 'Dr. João',
        ]);
    }

    public function test_scheduling_and_transitioning_an_appointment_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create();
        $professional = Professional::factory()->create();
        foreach (range(1, 5) as $weekday) {
            $professional->availabilities()->create(['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '18:00:00']);
        }
        $start = \Carbon\Carbon::parse('next monday')->setTime(9, 0);

        $response = $this->withHeaders($this->headers($admin))->postJson('/api/v1/appointments', [
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'start_at' => $start->toIso8601String(),
            'end_at' => $start->copy()->addMinutes(30)->toIso8601String(),
        ]);
        $response->assertCreated();
        $appointmentId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'scheduled',
            'subject_type' => 'appointment',
            'subject_id' => $appointmentId,
        ]);

        $this->withHeaders($this->headers($admin))
            ->postJson("/api/v1/appointments/{$appointmentId}/confirm")
            ->assertOk();

        $log = AuditLog::query()
            ->where('subject_type', 'appointment')
            ->where('action', 'confirmed')
            ->where('subject_id', $appointmentId)
            ->firstOrFail();
        $this->assertSame('scheduled', $log->changes['from']);
        $this->assertSame('confirmed', $log->changes['to']);
    }

    public function test_login_and_logout_are_logged(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'login',
        ]);

        $this->withHeaders($this->headers($user))->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'logout',
        ]);
    }

    public function test_admin_creating_a_user_is_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withHeaders($this->headers($admin))->postJson('/api/v1/users', [
            'name' => 'Novo Membro',
            'email' => 'novo@dragenda.test',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'role' => 'staff',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'created',
            'subject_type' => 'user',
            'subject_label' => 'Novo Membro',
        ]);
    }

    public function test_can_filter_by_actor(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        AuditLog::factory()->create(['actor_id' => $admin->id]);
        AuditLog::factory()->create(['actor_id' => $other->id]);

        $response = $this->withHeaders($this->headers($admin))
            ->getJson("/api/v1/audit-logs?actor_id={$other->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.actor.id', $other->id);
    }

    public function test_can_filter_by_multiple_actors(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();
        AuditLog::factory()->create(['actor_id' => $userA->id]);
        AuditLog::factory()->create(['actor_id' => $userB->id]);
        AuditLog::factory()->create(['actor_id' => $userC->id]);

        $response = $this->withHeaders($this->headers($admin))
            ->getJson("/api/v1/audit-logs?".http_build_query(['actor_id' => [$userA->id, $userB->id]], '', '&'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('actor.id')->all();
        $this->assertEqualsCanonicalizing([$userA->id, $userB->id], $ids);
    }

    public function test_can_filter_by_actor_name(): void
    {
        $admin = User::factory()->admin()->create();
        $ana = User::factory()->create(['name' => 'Ana Beatriz']);
        $carlos = User::factory()->create(['name' => 'Carlos Souza']);
        AuditLog::factory()->create(['actor_id' => $ana->id, 'actor_name' => $ana->name]);
        AuditLog::factory()->create(['actor_id' => $carlos->id, 'actor_name' => $carlos->name]);

        $response = $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/audit-logs?actor_name=ana');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.actor.name', 'Ana Beatriz');
    }

    public function test_can_filter_by_action(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->create(['action' => 'created']);
        AuditLog::factory()->create(['action' => 'deleted']);

        $response = $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/audit-logs?action=deleted');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.action', 'deleted');
    }

    public function test_can_filter_by_subject_type(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->create(['subject_type' => 'patient']);
        AuditLog::factory()->create(['subject_type' => 'appointment']);

        $response = $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/audit-logs?subject_type=appointment');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.subject_type', 'appointment');
    }
}
