<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-correta')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-correta',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-correta')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_nonexistent_email_gets_the_exact_same_response_as_wrong_password(): void
    {
        // Anti-enumeração (CLAUDE.md, seção 7): não dá pra saber pela resposta
        // se o e-mail existe ou não.
        User::factory()->create(['email' => 'existente@dragenda.test', 'password' => Hash::make('senha-correta')]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'existente@dragenda.test',
            'password' => 'senha-errada',
        ]);

        $nonexistentEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nao-existe@dragenda.test',
            'password' => 'qualquer-coisa',
        ]);

        $this->assertSame($wrongPassword->getStatusCode(), $nonexistentEmail->getStatusCode());
        $this->assertSame($wrongPassword->json('message'), $nonexistentEmail->json('message'));
    }

    public function test_missing_fields_are_validated(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_sixth_login_attempt_within_a_minute_is_throttled(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-correta')]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'senha-errada',
            ])->assertStatus(422);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'senha-errada',
        ]);

        $response->assertStatus(429);
    }
}
