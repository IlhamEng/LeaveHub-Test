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
     * View own leave request history.
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
     * Submit a new leave request.
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
     * Cancel own pending leave request.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pengajuan cuti ini.',
            ], 403);
        }

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
     * Soft delete own cancelled or rejected leave request.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pengajuan cuti ini.',
            ], 403);
        }

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
