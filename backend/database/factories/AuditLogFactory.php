<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'actor_name' => fake()->name(),
            'action' => 'updated',
            'subject_type' => 'patient',
            'subject_id' => fake()->numberBetween(1, 1000),
            'subject_label' => fake()->name(),
            'changes' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
