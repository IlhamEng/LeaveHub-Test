<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private LeaveType $annualLeave;
    private LeaveType $sickLeave;

    protected function setUp(): void
    {
        parent::setUp();

        // Create leave types
        $this->annualLeave = LeaveType::create(['name' => 'Annual Leave', 'default_quota' => 12]);
        $this->sickLeave = LeaveType::create(['name' => 'Sick Leave', 'default_quota' => 6]);

        // Create user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        // Create leave balances
        LeaveBalance::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'year' => now()->year,
            'total_quota' => 12,
            'used' => 0,
        ]);

        LeaveBalance::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->sickLeave->id,
            'year' => now()->year,
            'total_quota' => 6,
            'used' => 0,
        ]);

        $this->token = $this->user->createToken('auth-token')->plainTextToken;
    }

    private function authHeader(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_user_can_view_leave_balances(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/leave-balances');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('message', 'Sisa kuota cuti berhasil diambil.');
    }

    public function test_user_can_view_leave_requests(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/leave-requests');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Riwayat cuti berhasil diambil.');
    }

    public function test_user_can_submit_leave_request(): void
    {
        $startDate = now()->addDays(5)->format('Y-m-d');
        $endDate = now()->addDays(7)->format('Y-m-d');

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/leave-requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => 'Liburan keluarga',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Pengajuan cuti berhasil dibuat.')
            ->assertJsonPath('data.total_days', 3)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_user_cannot_submit_with_insufficient_quota(): void
    {
        // Use up most of the quota
        $balance = LeaveBalance::where('user_id', $this->user->id)
            ->where('leave_type_id', $this->annualLeave->id)
            ->first();
        $balance->update(['used' => 11]);

        $startDate = now()->addDays(5)->format('Y-m-d');
        $endDate = now()->addDays(7)->format('Y-m-d');

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/leave-requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => 'Too many days',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_submit_with_overlapping_dates(): void
    {
        $startDate = now()->addDays(5)->format('Y-m-d');
        $endDate = now()->addDays(7)->format('Y-m-d');

        // Create first request
        LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => 3,
            'reason' => 'First request',
            'status' => 'pending',
        ]);

        // Try overlapping request
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/leave-requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date' => now()->addDays(6)->format('Y-m-d'),
                'end_date' => now()->addDays(8)->format('Y-m-d'),
                'reason' => 'Overlapping request',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tanggal cuti bentrok dengan pengajuan cuti yang masih pending atau sudah disetujui.');
    }

    public function test_user_cannot_submit_with_past_date(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/leave-requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date' => now()->subDays(1)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'reason' => 'Past date',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_user_cannot_submit_with_end_date_before_start_date(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/leave-requests', [
                'leave_type_id' => $this->annualLeave->id,
                'start_date' => now()->addDays(5)->format('Y-m-d'),
                'end_date' => now()->addDays(3)->format('Y-m-d'),
                'reason' => 'Invalid dates',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_user_can_cancel_pending_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Test cancel',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->patchJson("/api/leave-requests/{$leaveRequest->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pengajuan cuti berhasil dibatalkan.')
            ->assertJsonPath('data.status', 'canceled');
    }

    public function test_user_cannot_cancel_approved_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Approved request',
            'status' => 'approved',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->patchJson("/api/leave-requests/{$leaveRequest->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_user_can_soft_delete_cancelled_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Cancelled request',
            'status' => 'canceled',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pengajuan cuti berhasil dihapus.');

        $this->assertSoftDeleted('leave_requests', ['id' => $leaveRequest->id]);
    }

    public function test_user_can_soft_delete_rejected_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Rejected request',
            'status' => 'rejected',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('leave_requests', ['id' => $leaveRequest->id]);
    }

    public function test_user_cannot_soft_delete_pending_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Pending request',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(422);
    }

    public function test_user_cannot_delete_other_users_request(): void
    {
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        $leaveRequest = LeaveRequest::create([
            'user_id' => $otherUser->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Other user request',
            'status' => 'canceled',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(403);
    }
}
