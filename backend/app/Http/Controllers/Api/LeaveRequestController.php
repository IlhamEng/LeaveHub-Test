<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    /**
     * @OA\Get(
     *     path="/leave-requests",
     *     summary="View own leave requests",
     *     description="Get current user's leave request history (non-deleted)",
     *     operationId="viewLeaveRequests",
     *     tags={"User - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $leaveRequests = LeaveRequest::with(['leaveType', 'responder'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Riwayat cuti berhasil diambil.',
            'data' => $leaveRequests,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/leave-requests",
     *     summary="Submit leave request",
     *     description="Submit a new leave request. Validates quota and overlap.",
     *     operationId="submitLeaveRequest",
     *     tags={"User - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type_id","start_date","end_date","reason"},
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-04-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-04-03"),
     *             @OA\Property(property="reason", type="string", example="Liburan keluarga")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave request created",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $user = $request->user();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // Check quota
        $balance = LeaveBalance::where('user_id', $user->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $startDate->year)
            ->first();

        if (!$balance) {
            return response()->json([
                'message' => 'Kuota cuti tidak ditemukan untuk tipe cuti dan tahun ini.',
            ], 422);
        }

        $remainingQuota = $balance->total_quota - $balance->used;
        if ($remainingQuota < $totalDays) {
            return response()->json([
                'message' => "Kuota cuti tidak mencukupi. Sisa kuota: {$remainingQuota} hari, dibutuhkan: {$totalDays} hari.",
            ], 422);
        }

        // Check overlap with pending or approved requests
        $overlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', [LeaveRequest::STATUS_PENDING, LeaveRequest::STATUS_APPROVED])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Tanggal cuti bentrok dengan pengajuan cuti yang masih pending atau sudah disetujui.',
            ], 422);
        }

        $leaveRequest = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'reason' => $request->reason,
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        $leaveRequest->load('leaveType');

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dibuat.',
            'data' => $leaveRequest,
        ], 201);
    }

    /**
     * @OA\Patch(
     *     path="/leave-requests/{id}/cancel",
     *     summary="Cancel own leave request",
     *     description="Cancel a pending leave request owned by the current user",
     *     operationId="cancelLeaveRequest",
     *     tags={"User - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Request cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Not your request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Cannot cancel")
     * )
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Check ownership
        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pengajuan cuti ini.',
            ], 403);
        }

        // Check status
        if (!$leaveRequest->isPending()) {
            return response()->json([
                'message' => 'Hanya pengajuan cuti berstatus pending yang bisa dibatalkan.',
            ], 422);
        }

        $leaveRequest->update([
            'status' => LeaveRequest::STATUS_CANCELED,
        ]);

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dibatalkan.',
            'data' => $leaveRequest->fresh()->load('leaveType'),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/leave-requests/{id}",
     *     summary="Soft delete own leave request",
     *     description="Soft delete own cancelled or rejected leave request",
     *     operationId="deleteLeaveRequest",
     *     tags={"User - Leave Request"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Request deleted"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Not your request"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Cannot delete")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Check ownership
        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pengajuan cuti ini.',
            ], 403);
        }

        // User can only soft delete cancelled or rejected requests
        if (!in_array($leaveRequest->status, [LeaveRequest::STATUS_CANCELED, LeaveRequest::STATUS_REJECTED])) {
            return response()->json([
                'message' => 'User hanya bisa menghapus pengajuan cuti yang berstatus cancelled atau rejected.',
            ], 422);
        }

        $leaveRequest->update(['deleted_by' => $request->user()->id]);
        $leaveRequest->delete();

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dihapus.',
        ]);
    }
}
