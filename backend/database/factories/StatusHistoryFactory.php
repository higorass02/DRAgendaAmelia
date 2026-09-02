<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusHistory>
 */
class StatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'from_status' => null,
            'to_status' => AppointmentStatus::Scheduled,
            'reason' => null,
            'changed_by' => User::factory(),
            'changed_at' => now(),
        ];
    }
}
