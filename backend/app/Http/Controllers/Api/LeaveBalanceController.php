<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    /**
     * View own leave balances for the current year.
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
