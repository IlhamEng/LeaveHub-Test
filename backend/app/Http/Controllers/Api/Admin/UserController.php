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
     * @OA\Get(
     *     path="/admin/users",
     *     summary="List all users",
     *     description="Get list of all users (admin only)",
     *     operationId="adminListUsers",
     *     tags={"Admin - User Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Daftar user berhasil diambil."),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="role", type="string"),
     *                 @OA\Property(property="created_at", type="string"),
     *                 @OA\Property(property="updated_at", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(): JsonResponse
    {
        $users = User::all();

        return response()->json([
            'message' => 'Daftar user berhasil diambil.',
            'data' => $users,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/users",
     *     summary="Create new user",
     *     description="Create a new user with role 'user'. Maximum 2 users allowed. Leave balances auto-assigned.",
     *     operationId="adminCreateUser",
     *     tags={"Admin - User Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User berhasil dibuat."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or max users reached",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        // Check max 2 users
        if (User::count() >= 2) {
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
     * @OA\Put(
     *     path="/admin/users/{id}",
     *     summary="Update user",
     *     description="Update user data (admin only)",
     *     operationId="adminUpdateUser",
     *     tags={"Admin - User Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Updated"),
     *             @OA\Property(property="email", type="string", format="email", example="john.updated@example.com"),
     *             @OA\Property(property="password", type="string", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User berhasil diperbarui."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateUserRequest $request, int $user): JsonResponse
    {
        $userModel = User::findOrFail($user);

        $data = $request->only(['name', 'email', 'password']);
        $userModel->update($data);

        return response()->json([
            'message' => 'User berhasil diperbarui.',
            'data' => $userModel->fresh(),
        ]);
    }
}
