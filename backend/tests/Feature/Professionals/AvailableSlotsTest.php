<?php

namespace Tests\Feature\Professionals;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailableSlotsTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $staff = User::factory()->create();

        return ['Authorization' => 'Bearer '.$staff->createToken('api')->plainTextToken];
    }

    private function nextMonday(): Carbon
    {
        return Carbon::parse('next monday')->startOfDay();
    }

    public function test_guest_cannot_access_available_slots(): void
    {
        $professional = Professional::factory()->create();

        $this->getJson("/api/v1/professionals/{$professional->id}/available-slots?date=2026-01-05")
            ->assertUnauthorized();
    }

    public function test_requires_a_date(): void
    {
        $professional = Professional::factory()->create();

        $this->withHeaders($this->headers())
            ->getJson("/api/v1/professionals/{$professional->id}/available-slots")
            ->assertUnprocessable();
    }

    public function test_returns_slots_within_the_availability_window(): void
    {
        $professional = Professional::factory()->create();
        $monday = $this->nextMonday();
        $professional->availabilities()->create(['weekday' => $monday->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);

        $response = $this->withHeaders($this->headers())->getJson(
            "/api/v1/professionals/{$professional->id}/available-slots?date={$monday->toDateString()}&duration_minutes=30"
        );

        $response->assertOk();
        $response->assertJsonPath('slots', ['09:00', '09:30']);
    }

    public function test_returns_empty_when_professional_has_no_availability_that_weekday(): void
    {
        $professional = Professional::factory()->create();
        $monday = $this->nextMonday();
        // Só disponível terça (weekday != segunda).
        $professional->availabilities()->create(['weekday' => $monday->copy()->addDay()->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);

        $response = $this->withHeaders($this->headers())->getJson(
            "/api/v1/professionals/{$professional->id}/available-slots?date={$monday->toDateString()}"
        );

        $response->assertOk();
        $response->assertJsonPath('slots', []);
    }

    public function test_excludes_slots_conflicting_with_existing_appointments(): void
    {
        $professional = Professional::factory()->create();
        $monday = $this->nextMonday();
        $professional->availabilities()->create(['weekday' => $monday->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);

        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'status' => AppointmentStatus::Confirmed,
            'start_at' => $monday->copy()->setTime(9, 30),
            'end_at' => $monday->copy()->setTime(10, 0),
        ]);

        $response = $this->withHeaders($this->headers())->getJson(
            "/api/v1/professionals/{$professional->id}/available-slots?date={$monday->toDateString()}&duration_minutes=30"
        );

        $response->assertOk();
        $response->assertJsonPath('slots', ['09:00']);
    }

    public function test_cancelled_appointments_do_not_block_a_slot(): void
    {
        $professional = Professional::factory()->create();
        $monday = $this->nextMonday();
        $professional->availabilities()->create(['weekday' => $monday->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);

        Appointment::factory()->create([
            'professional_id' => $professional->id,
            'status' => AppointmentStatus::Cancelled,
            'start_at' => $monday->copy()->setTime(9, 30),
            'end_at' => $monday->copy()->setTime(10, 0),
        ]);

        $response = $this->withHeaders($this->headers())->getJson(
            "/api/v1/professionals/{$professional->id}/available-slots?date={$monday->toDateString()}&duration_minutes=30"
        );

        $response->assertOk();
        $response->assertJsonPath('slots', ['09:00', '09:30']);
    }

    public function test_can_exclude_an_appointment_being_rescheduled(): void
    {
        $professional = Professional::factory()->create();
        $monday = $this->nextMonday();
        $professional->availabilities()->create(['weekday' => $monday->dayOfWeek, 'start_time' => '09:00:00', 'end_time' => '10:00:00']);

        $appointment = Appointment::factory()->create([
            'professional_id' => $professional->id,
            'status' => AppointmentStatus::Confirmed,
            'start_at' => $monday->copy()->setTime(9, 30),
            'end_at' => $monday->copy()->setTime(10, 0),
        ]);

        $response = $this->withHeaders($this->headers())->getJson(
            "/api/v1/professionals/{$professional->id}/available-slots?date={$monday->toDateString()}"
            ."&duration_minutes=30&exclude_appointment_id={$appointment->id}"
        );

        $response->assertOk();
        $response->assertJsonPath('slots', ['09:00', '09:30']);
    }
}
