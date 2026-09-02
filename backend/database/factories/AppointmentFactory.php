<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startAt = $this->faker->dateTimeBetween('+1 day', '+30 days');

        return [
            'patient_id' => Patient::factory(),
            'professional_id' => Professional::factory(),
            'start_at' => $startAt,
            'end_at' => (clone $startAt)->modify('+30 minutes'),
            'status' => AppointmentStatus::Scheduled,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => AppointmentStatus::Confirmed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => AppointmentStatus::Cancelled]);
    }
}
