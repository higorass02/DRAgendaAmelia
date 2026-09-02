<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'cpf' => $this->faker->unique()->cpf(false),
            'phone' => $this->faker->cellphoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'birth_date' => $this->faker->dateTimeBetween('-90 years', '-1 years'),
            'user_id' => null,
        ];
    }
}
