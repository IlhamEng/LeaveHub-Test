<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'name' => 'Admin',
            'email' => 'admin@leavehub.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@leavehub.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.email', 'admin@leavehub.com')
            ->assertJsonPath('data.user.role', 'admin');
    }

    public function test_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@leavehub.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Email atau password salah.');
    }

    public function test_login_with_missing_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::where('email', 'admin@leavehub.com')->first();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Verify token exists before logout
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logout berhasil.');

        // Verify token is revoked (deleted from database)
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_access_protected_route_without_token(): void
    {
        $response = $this->getJson('/api/leave-balances');

        $response->assertStatus(401);
    }
}
