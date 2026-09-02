<?php

namespace Tests\Feature\Professionals;

use App\Models\Professional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalCrudTest extends TestCase
{
    use RefreshDatabase;

    private function staffHeaders(): array
    {
        $staff = User::factory()->create();
        $token = $staff->createToken('api')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_guest_cannot_access_professionals(): void
    {
        $this->getJson('/api/v1/professionals')->assertUnauthorized();
    }

    public function test_staff_can_list_professionals_with_availabilities(): void
    {
        $professional = Professional::factory()->create();
        $professional->availabilities()->create(['weekday' => 1, 'start_time' => '08:00:00', 'end_time' => '18:00:00']);

        $response = $this->withHeaders($this->staffHeaders())->getJson('/api/v1/professionals');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure(['data' => [['id', 'name', 'specialty', 'availabilities']]]);
    }

    public function test_staff_can_create_a_professional_with_availabilities(): void
    {
        $payload = [
            'name' => 'Dr. João',
            'specialty' => 'Cardiologia',
            'availabilities' => [
                ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
                ['weekday' => 3, 'start_time' => '14:00', 'end_time' => '18:00'],
            ],
        ];

        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/v1/professionals', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('professionals', ['name' => 'Dr. João', 'specialty' => 'Cardiologia']);
        $this->assertDatabaseCount('professional_availabilities', 2);
        $response->assertJsonCount(2, 'data.availabilities');
    }

    public function test_requires_at_least_one_availability_window(): void
    {
        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/v1/professionals', [
            'name' => 'Dr. João',
            'specialty' => 'Cardiologia',
            'availabilities' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['availabilities']);
    }

    public function test_rejects_availability_where_start_is_after_end(): void
    {
        $response = $this->withHeaders($this->staffHeaders())->postJson('/api/v1/professionals', [
            'name' => 'Dr. João',
            'specialty' => 'Cardiologia',
            'availabilities' => [
                ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '08:00'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_staff_can_view_a_professional(): void
    {
        $professional = Professional::factory()->create();

        $response = $this->withHeaders($this->staffHeaders())->getJson("/api/v1/professionals/{$professional->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $professional->id);
    }

    public function test_updating_replaces_availabilities(): void
    {
        $professional = Professional::factory()->create();
        $professional->availabilities()->create(['weekday' => 1, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);

        $response = $this->withHeaders($this->staffHeaders())->putJson("/api/v1/professionals/{$professional->id}", [
            'name' => $professional->name,
            'specialty' => $professional->specialty,
            'availabilities' => [
                ['weekday' => 5, 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('professional_availabilities', 1);
        $this->assertDatabaseHas('professional_availabilities', ['professional_id' => $professional->id, 'weekday' => 5]);
    }
}
