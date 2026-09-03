<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_unexpected_exceptions_never_leak_internal_details_to_the_client(): void
    {
        Route::get('/api/v1/__test-explode', function () {
            throw new \RuntimeException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'dragenda.secret_table' doesn't exist");
        });

        $response = $this->getJson('/api/v1/__test-explode');

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Algo deu errado no servidor. Tente novamente.');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('secret_table', $response->getContent());
    }

    public function test_model_not_found_still_returns_a_plain_404(): void
    {
        $staff = \App\Models\User::factory()->create();
        $token = $staff->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/patients/999999');

        $response->assertStatus(404);
    }
}
