<?php

namespace Tests\Feature\Patients;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientCrudTest extends TestCase
{
    use RefreshDatabase;

    private function staffHeaders(): array
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('api')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_guest_cannot_access_patients(): void
    {
        $this->getJson('/api/v1/patients')->assertUnauthorized();
    }

    public function test_patient_role_user_cannot_access_staff_patient_endpoints(): void
    {
        $patientUser = User::factory()->create();
        $patientUser->forceFill(['role' => UserRole::Patient])->save();
        $token = $patientUser->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/patients')
            ->assertForbidden();
    }

    public function test_staff_can_list_patients(): void
    {
        Patient::factory()->count(3)->create();

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure(['data' => [['id', 'name', 'cpf', 'phone', 'email', 'birth_date']]]);
    }

    public function test_staff_can_create_a_patient(): void
    {
        $payload = [
            'name' => 'Maria Silva',
            'cpf' => '111.444.777-35',
            'phone' => '11999998888',
            'email' => 'maria@example.com',
            'birth_date' => '1990-05-10',
        ];

        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/v1/patients', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.cpf', '11144477735');
        $this->assertDatabaseHas('patients', ['cpf' => '11144477735', 'name' => 'Maria Silva']);
    }

    public function test_cpf_must_be_11_digits(): void
    {
        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/v1/patients', [
            'name' => 'Maria Silva',
            'cpf' => '123',
            'phone' => '11999998888',
            'birth_date' => '1990-05-10',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cpf']);
    }

    public function test_cpf_must_be_unique(): void
    {
        $existing = Patient::factory()->create();

        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/v1/patients', [
            'name' => 'Outro Nome',
            'cpf' => $existing->cpf,
            'phone' => '11999998888',
            'birth_date' => '1990-05-10',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cpf']);
    }

    public function test_staff_can_view_a_single_patient(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->withHeaders($this->staffHeaders())->getJson("/api/v1/patients/{$patient->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $patient->id);
    }

    public function test_staff_can_update_a_patient(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->withHeaders($this->staffHeaders())->putJson("/api/v1/patients/{$patient->id}", [
            'name' => 'Nome Atualizado',
            'cpf' => $patient->cpf,
            'phone' => $patient->phone,
            'birth_date' => $patient->birth_date->format('Y-m-d'),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'name' => 'Nome Atualizado']);
    }

    public function test_updating_keeps_own_cpf_valid(): void
    {
        $patient = Patient::factory()->create(['cpf' => '11144477735']);

        $response = $this->withHeaders($this->staffHeaders())->putJson("/api/v1/patients/{$patient->id}", [
            'name' => $patient->name,
            'cpf' => '111.444.777-35',
            'phone' => $patient->phone,
            'birth_date' => $patient->birth_date->format('Y-m-d'),
        ]);

        $response->assertOk();
    }
}
