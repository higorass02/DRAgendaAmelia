<?php

namespace Database\Factories;

use App\Models\Professional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    private const SPECIALTIES = [
        'Clínica Geral',
        'Cardiologia',
        'Dermatologia',
        'Pediatria',
        'Ginecologia',
        'Ortopedia',
        'Psiquiatria',
        'Endocrinologia',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr(a). '.$this->faker->name(),
            'specialty' => $this->faker->randomElement(self::SPECIALTIES),
        ];
    }
}
