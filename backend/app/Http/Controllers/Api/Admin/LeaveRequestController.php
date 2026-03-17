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
     * @OA\Get(
     *     path="/admin/leave-requests",
     *     summary="View all leave requests",
     *     description="Get all leave requests from all users (admin only)",
     *     operationId="adminListLeaveRequests",
     *     tags={"Admin - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
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
     * @OA\Patch(
     *     path="/admin/leave-requests/{id}/approve",
     *     summary="Approve leave request",
     *     description="Approve a pending leave request (admin only). Deducts user's leave balance.",
     *     operationId="adminApproveLeaveRequest",
     *     tags={"Admin - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="admin_notes", type="string", example="Disetujui, selamat berlibur.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Request approved",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Cannot approve")
     * )
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
            ->where('year', $leaveRequest->start_date->year)
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
     * @OA\Patch(
     *     path="/admin/leave-requests/{id}/reject",
     *     summary="Reject leave request",
     *     description="Reject a pending leave request (admin only). Balance unchanged.",
     *     operationId="adminRejectLeaveRequest",
     *     tags={"Admin - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="admin_notes", type="string", example="Ditolak karena kebutuhan proyek.")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Request rejected"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Cannot reject")
     * )
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
     * @OA\Delete(
     *     path="/admin/leave-requests/{id}",
     *     summary="Soft delete leave request",
     *     description="Soft delete a final-status leave request (approved, rejected, cancelled). Admin only.",
     *     operationId="adminDeleteLeaveRequest",
     *     tags={"Admin - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Request deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Cannot delete")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Admin can only soft delete final-status requests
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
