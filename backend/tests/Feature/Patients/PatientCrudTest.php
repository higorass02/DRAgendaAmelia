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

    public function test_phone_is_normalized_to_digits_only_on_create(): void
    {
        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/v1/patients', [
            'name' => 'Maria Silva',
            'cpf' => '111.444.777-35',
            'phone' => '(11) 99999-8888',
            'birth_date' => '1990-05-10',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.phone', '11999998888');
        $this->assertDatabaseHas('patients', ['phone' => '11999998888']);
    }

    public function test_phone_is_normalized_to_digits_only_on_update(): void
    {
        $patient = Patient::factory()->create(['phone' => '11988887777']);

        $response = $this->withHeaders($this->staffHeaders())->putJson("/api/v1/patients/{$patient->id}", [
            'name' => $patient->name,
            'cpf' => $patient->cpf,
            'phone' => '(11) 99999-8888',
            'birth_date' => $patient->birth_date->format('Y-m-d'),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'phone' => '11999998888']);
    }

    public function test_can_search_by_masked_phone(): void
    {
        Patient::factory()->create(['phone' => '11999998888', 'name' => 'A']);
        Patient::factory()->create(['phone' => '21988887777', 'name' => 'B']);

        $response = $this->withHeaders($this->staffHeaders())
            ->getJson('/api/v1/patients?'.http_build_query(['phone' => '(11) 999']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'A');
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

    public function test_list_is_paginated(): void
    {
        Patient::factory()->count(20)->create();

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients');

        $response->assertOk();
        $response->assertJsonCount(15, 'data');
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('meta.last_page', 2);

        $second = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients?page=2');
        $second->assertJsonCount(5, 'data');
    }

    public function test_can_search_by_name(): void
    {
        Patient::factory()->create(['name' => 'Maria da Silva']);
        Patient::factory()->create(['name' => 'João Pereira']);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients?name=maria');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Maria da Silva');
    }

    public function test_can_search_by_cpf(): void
    {
        Patient::factory()->create(['cpf' => '11144477735', 'name' => 'A']);
        Patient::factory()->create(['cpf' => '22255588846', 'name' => 'B']);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients?cpf=111444');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'A');
    }

    public function test_can_search_by_phone(): void
    {
        Patient::factory()->create(['phone' => '11999998888', 'name' => 'A']);
        Patient::factory()->create(['phone' => '21988887777', 'name' => 'B']);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients?phone=11999');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'A');
    }

    public function test_can_search_by_email(): void
    {
        Patient::factory()->create(['email' => 'maria@example.com', 'name' => 'A']);
        Patient::factory()->create(['email' => 'joao@example.com', 'name' => 'B']);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients?email=maria@');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'A');
    }

    public function test_can_search_by_birth_date_range(): void
    {
        Patient::factory()->create(['birth_date' => '1990-05-10', 'name' => 'A']);
        Patient::factory()->create(['birth_date' => '2000-01-01', 'name' => 'B']);

        $response = $this->withHeaders($this->staffHeaders())
            ->getJson('/api/v1/patients?birth_date_from=1985-01-01&birth_date_to=1995-01-01');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'A');
    }

    public function test_page_size_can_be_customized(): void
    {
        Patient::factory()->count(20)->create();

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients?per_page=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.last_page', 4);
    }

    public function test_page_size_is_capped(): void
    {
        Patient::factory()->count(3)->create();

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/patients?per_page=99999');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
    }

    public function test_can_sort_by_name_descending(): void
    {
        Patient::factory()->create(['name' => 'Ana']);
        Patient::factory()->create(['name' => 'Bruno']);

        $response = $this->withHeaders($this->staffHeaders())
            ->getJson('/api/v1/patients?sort=name&direction=desc');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Bruno', 'Ana'], $names);
    }
}
