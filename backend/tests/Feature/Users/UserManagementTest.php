<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('api')->plainTextToken];
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_staff_cannot_manage_users(): void
    {
        $staff = User::factory()->create();

        $this->withHeaders($this->headers($staff))->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_patient_cannot_manage_users(): void
    {
        $patient = User::factory()->patient()->create();

        $this->withHeaders($this->headers($patient))->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $response = $this->withHeaders($this->headers($admin))->getJson('/api/v1/users');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    }

    public function test_admin_can_search_users_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Ana Beatriz']);
        User::factory()->create(['name' => 'Carlos Souza']);

        $response = $this->withHeaders($this->headers($admin))
            ->getJson('/api/v1/users?name=Ana');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Ana Beatriz', $names);
        $this->assertNotContains('Carlos Souza', $names);
    }

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->withHeaders($this->headers($admin))->postJson('/api/v1/users', [
            'name' => 'Novo Membro',
            'email' => 'novo@dragenda.test',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'role' => 'staff',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.role', 'staff');
        $this->assertDatabaseHas('users', ['email' => 'novo@dragenda.test', 'role' => 'staff']);
    }

    public function test_creating_a_user_requires_a_valid_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->withHeaders($this->headers($admin))->postJson('/api/v1/users', [
            'name' => 'Novo Membro',
            'email' => 'novo@dragenda.test',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'role' => 'super-root',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('role');
    }

    public function test_admin_can_update_a_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->withHeaders($this->headers($admin))
            ->putJson("/api/v1/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'admin',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.role', 'admin');

        $log = \App\Models\AuditLog::query()
            ->where('subject_type', 'user')
            ->where('subject_id', $user->id)
            ->where('action', 'updated')
            ->firstOrFail();
        // assertEquals (não assertSame): o MySQL JSON não garante preservar a
        // ordem das chaves no round-trip — os valores é que importam aqui.
        $this->assertEquals(['from' => 'staff', 'to' => 'admin'], $log->changes['role']);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $response = $this->withHeaders($this->headers($admin))
            ->deleteJson("/api/v1/users/{$user->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_themselves_through_this_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->withHeaders($this->headers($admin))
            ->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_staff_cannot_create_users(): void
    {
        $staff = User::factory()->create();

        $this->withHeaders($this->headers($staff))->postJson('/api/v1/users', [
            'name' => 'X',
            'email' => 'x@dragenda.test',
            'password' => 'secret-123',
            'password_confirmation' => 'secret-123',
            'role' => 'staff',
        ])->assertForbidden();
    }
}
