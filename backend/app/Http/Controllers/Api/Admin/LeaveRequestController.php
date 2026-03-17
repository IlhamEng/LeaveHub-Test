<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    /**
     * View all leave requests from all users.
     */
    public function index(): JsonResponse
    {
        $leaveRequests = LeaveRequest::with(['user', 'leaveType', 'responder'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Daftar pengajuan cuti berhasil diambil.',
            'data' => $leaveRequests,
        ]);
    }

    /**
     * Approve a pending leave request. Deducts user's leave balance.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        if (!$leaveRequest->isPending()) {
            return response()->json([
                'message' => 'Hanya pengajuan cuti berstatus pending yang bisa disetujui.',
            ], 422);
        }

        // Deduct balance
        $balance = LeaveBalance::where('user_id', $leaveRequest->user_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', \Carbon\Carbon::parse($leaveRequest->start_date)->year)
            ->first();

        if (!$balance) {
            return response()->json([
                'message' => 'Kuota cuti user tidak ditemukan.',
            ], 422);
        }

        $remainingQuota = $balance->total_quota - $balance->used;
        if ($remainingQuota < $leaveRequest->total_days) {
            return response()->json([
                'message' => "Kuota cuti user tidak mencukupi. Sisa: {$remainingQuota} hari, dibutuhkan: {$leaveRequest->total_days} hari.",
            ], 422);
        }

        $balance->increment('used', $leaveRequest->total_days);

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_APPROVED,
            'responded_by' => $request->user()->id,
            'admin_notes' => $request->input('admin_notes'),
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pengajuan cuti berhasil disetujui.',
            'data' => $leaveRequest->fresh()->load(['user', 'leaveType', 'responder']),
        ]);
    }

    /**
     * Reject a pending leave request. Balance unchanged.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        if (!$leaveRequest->isPending()) {
            return response()->json([
                'message' => 'Hanya pengajuan cuti berstatus pending yang bisa ditolak.',
            ], 422);
        }

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_REJECTED,
            'responded_by' => $request->user()->id,
            'admin_notes' => $request->input('admin_notes'),
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pengajuan cuti berhasil ditolak.',
            'data' => $leaveRequest->fresh()->load(['user', 'leaveType', 'responder']),
        ]);
    }

    /**
     * Soft delete a final-status leave request (admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->isPending()) {
            return response()->json([
                'message' => 'Tidak bisa menghapus pengajuan cuti yang masih berstatus pending. Harus di-cancel atau diproses terlebih dahulu.',
            ], 422);
        }

        $leaveRequest->update(['deleted_by' => $request->user()->id]);
        $leaveRequest->delete();

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dihapus.',
        ]);
    }
}
