<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /**
     * List all users.
     */
    public function index(): JsonResponse
    {
        $users = User::where('role', 'user')->get();

        return response()->json([
            'message' => 'Daftar user berhasil diambil.',
            'data' => $users,
        ]);
    }

    /**
     * Create a new user. Max 2 users validation. Auto-assign leave balances.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        if (User::where('role', 'user')->count() >= 2) {
            return response()->json([
                'message' => 'Maksimal hanya boleh ada 2 user. Tidak bisa menambah user baru.',
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'user',
        ]);

        // Auto-assign leave balances for all leave types
        $currentYear = now()->year;
        $leaveTypes = LeaveType::all();

        foreach ($leaveTypes as $leaveType) {
            $user->leaveBalances()->create([
                'leave_type_id' => $leaveType->id,
                'year' => $currentYear,
                'total_quota' => $leaveType->default_quota,
                'used' => 0,
            ]);
        }

        $user->load('leaveBalances.leaveType');

        return response()->json([
            'message' => 'User berhasil dibuat.',
            'data' => $user,
        ], 201);
    }

    /**
     * Update user data.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $userModel = User::findOrFail($id);

        $data = $request->only(['name', 'email', 'password']);

        // Remove empty/null values so we don't overwrite with nothing
        $data = array_filter($data, fn($value) => !is_null($value) && $value !== '');

        $userModel->update($data);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'data' => $userModel->fresh(),
        ]);
    }
}
