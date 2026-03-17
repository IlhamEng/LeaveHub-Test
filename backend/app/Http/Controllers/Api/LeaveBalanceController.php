<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/leave-balances",
     *     summary="View own leave balances",
     *     description="Get current user's leave balances for the current year",
     *     operationId="viewLeaveBalances",
     *     tags={"User - Leave Balance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sisa kuota cuti berhasil diambil."),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="leave_type", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(property="year", type="integer"),
     *                 @OA\Property(property="total_quota", type="integer"),
     *                 @OA\Property(property="used", type="integer"),
     *                 @OA\Property(property="remaining_quota", type="integer")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', $request->user()->id)
            ->where('year', now()->year)
            ->get()
            ->map(function ($balance) {
                return [
                    'id' => $balance->id,
                    'leave_type' => [
                        'id' => $balance->leaveType->id,
                        'name' => $balance->leaveType->name,
                    ],
                    'year' => $balance->year,
                    'total_quota' => $balance->total_quota,
                    'used' => $balance->used,
                    'remaining_quota' => $balance->remaining_quota,
                ];
            });

        return response()->json([
            'message' => 'Sisa kuota cuti berhasil diambil.',
            'data' => $balances,
        ]);
    }
}
