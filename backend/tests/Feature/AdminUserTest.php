<?php

namespace Tests\Feature;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create leave types
        LeaveType::create(['name' => 'Annual Leave', 'default_quota' => 12]);
        LeaveType::create(['name' => 'Sick Leave', 'default_quota' => 6]);

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@leavehub.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->token = $this->admin->createToken('auth-token')->plainTextToken;
    }

    private function authHeader(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Daftar user berhasil diambil.');
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/admin/users', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'User berhasil dibuat.')
            ->assertJsonPath('data.name', 'John Doe')
            ->assertJsonPath('data.email', 'john@example.com')
            ->assertJsonPath('data.role', 'user');

        // Verify leave balances were created
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $response->json('data.id'),
            'year' => now()->year,
        ]);

        // Should have 2 leave balances (Annual + Sick)
        $this->assertCount(2, $response->json('data.leave_balances'));
    }

    public function test_admin_cannot_create_more_than_2_users(): void
    {
        // Create first user (role=user count: 1)
        $this->withHeaders($this->authHeader())
            ->postJson('/api/admin/users', [
                'name' => 'User One',
                'email' => 'user1@example.com',
                'password' => 'password123',
            ])
            ->assertStatus(201);

        // Create second user (role=user count: 2)
        $this->withHeaders($this->authHeader())
            ->postJson('/api/admin/users', [
                'name' => 'User Two',
                'email' => 'user2@example.com',
                'password' => 'password123',
            ])
            ->assertStatus(201);

        // Try creating third user (role=user count would be 3 → blocked)
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/admin/users', [
                'name' => 'User Three',
                'email' => 'user3@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Maksimal hanya boleh ada 2 user. Tidak bisa menambah user baru.');
    }

    public function test_admin_cannot_create_user_with_duplicate_email(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/admin/users', [
                'name' => 'Duplicate',
                'email' => 'admin@leavehub.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'User berhasil diperbarui.')
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::create([
            'name' => 'Normal User',
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $userToken = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$userToken}")
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/admin/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_create_user_validates_password_min_length(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/admin/users', [
                'name' => 'Test',
                'email' => 'test@example.com',
                'password' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
