<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('api')->plainTextToken];
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->putJson('/api/v1/me/password', [])->assertUnauthorized();
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->headers($user))
            ->putJson('/api/v1/me/password', [
                'current_password' => 'password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->headers($user))
            ->putJson('/api/v1/me/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_requires_confirmation_to_match(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->headers($user))
            ->putJson('/api/v1/me/password', [
                'current_password' => 'password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'does-not-match',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('password');
    }

    public function test_guest_cannot_delete_account(): void
    {
        $this->deleteJson('/api/v1/me', [])->assertUnauthorized();
    }

    public function test_user_can_delete_own_account(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->headers($user))
            ->deleteJson('/api/v1/me', ['password' => 'password']);

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_account_requires_correct_password(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->headers($user))
            ->deleteJson('/api/v1/me', ['password' => 'wrong-password']);

        $response->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_deleting_own_account_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->deleteJson('/api/v1/me', ['password' => 'password'])
            ->assertNoContent();

        // O AuthManager cacheia o guard resolvido entre chamadas HTTP
        // simuladas na mesma execução — não existe em produção (cada
        // request é um processo/app novo), só aqui no teste.
        $this->app['auth']->forgetGuards();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
