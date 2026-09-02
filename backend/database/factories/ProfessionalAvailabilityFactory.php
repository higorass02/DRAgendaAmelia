<?php

namespace Database\Factories;

use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfessionalAvailability>
 */
class ProfessionalAvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professional_id' => Professional::factory(),
            // 1 = segunda ... 5 = sexta (convenção Carbon::dayOfWeek()).
            'weekday' => $this->faker->numberBetween(1, 5),
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
        ];
    }
}
