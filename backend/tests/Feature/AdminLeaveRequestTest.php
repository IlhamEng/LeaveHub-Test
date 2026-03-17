<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;
    private string $adminToken;
    private LeaveType $annualLeave;

    protected function setUp(): void
    {
        parent::setUp();

        // Create leave types
        $this->annualLeave = LeaveType::create(['name' => 'Annual Leave', 'default_quota' => 12]);
        LeaveType::create(['name' => 'Sick Leave', 'default_quota' => 6]);

        // Create admin
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@leavehub.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->adminToken = $this->admin->createToken('auth-token')->plainTextToken;

        // Create user with leave balance
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
        ]);

        LeaveBalance::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'year' => now()->year,
            'total_quota' => 12,
            'used' => 0,
        ]);
    }

    private function authHeader(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    public function test_admin_can_view_all_leave_requests(): void
    {
        LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Test',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/admin/leave-requests');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_approve_pending_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Test approve',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->patchJson("/api/admin/leave-requests/{$leaveRequest->id}/approve", [
                'admin_notes' => 'Disetujui, selamat berlibur.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pengajuan cuti berhasil disetujui.')
            ->assertJsonPath('data.status', 'approved');

        // Verify balance was deducted
        $balance = LeaveBalance::where('user_id', $this->user->id)
            ->where('leave_type_id', $this->annualLeave->id)
            ->first();
        $this->assertEquals(3, $balance->used);
    }

    public function test_admin_can_reject_pending_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Test reject',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->patchJson("/api/admin/leave-requests/{$leaveRequest->id}/reject", [
                'admin_notes' => 'Ditolak karena kebutuhan proyek.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pengajuan cuti berhasil ditolak.')
            ->assertJsonPath('data.status', 'rejected');

        // Verify balance was NOT changed
        $balance = LeaveBalance::where('user_id', $this->user->id)
            ->where('leave_type_id', $this->annualLeave->id)
            ->first();
        $this->assertEquals(0, $balance->used);
    }

    public function test_admin_cannot_approve_non_pending_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Already approved',
            'status' => 'approved',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->patchJson("/api/admin/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(422);
    }

    public function test_admin_can_soft_delete_approved_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Approved and delete',
            'status' => 'approved',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/admin/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Pengajuan cuti berhasil dihapus.');

        $this->assertSoftDeleted('leave_requests', ['id' => $leaveRequest->id]);
    }

    public function test_admin_cannot_soft_delete_pending_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Still pending',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/admin/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(422);
    }

    public function test_admin_can_soft_delete_rejected_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Rejected and delete',
            'status' => 'rejected',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/admin/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('leave_requests', ['id' => $leaveRequest->id]);
    }

    public function test_admin_can_soft_delete_cancelled_request(): void
    {
        $leaveRequest = LeaveRequest::create([
            'user_id' => $this->user->id,
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'total_days' => 3,
            'reason' => 'Cancelled and delete',
            'status' => 'canceled',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/admin/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('leave_requests', ['id' => $leaveRequest->id]);
    }
}
