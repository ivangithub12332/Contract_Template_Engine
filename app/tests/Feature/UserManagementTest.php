<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create(['role' => 'user']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users');

        $response->assertOk()->assertJsonCount(3);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/users');

        $response->assertForbidden();
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$target->id}", [
            'role' => 'methodologist',
        ]);

        $response->assertOk()->assertJsonPath('role', 'methodologist');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'methodologist']);
    }

    public function test_role_update_rejects_an_invalid_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/users/{$target->id}", [
            'role' => 'superadmin',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$target->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/users/{$admin->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_user_list_can_be_filtered_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'methodologist']);
        User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/users?role=methodologist');

        $response->assertOk()->assertJsonCount(1);
    }
}
