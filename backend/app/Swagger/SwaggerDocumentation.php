<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'LeaveHub API',
    description: 'Leave Request Management API Documentation',
    contact: new OA\Contact(email: 'admin@leavehub.com')
)]
#[OA\Server(url: '/api', description: 'API Server')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token',
    description: 'Enter your Sanctum token'
)]

// ==========================================
// Authentication
// ==========================================
#[OA\Post(
    path: '/login',
    summary: 'Login user',
    description: 'Authenticate user and return access token',
    operationId: 'login',
    tags: ['Authentication'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@leavehub.com'),
                new OA\Property(property: 'password', type: 'string', example: 'password123'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Login successful', content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Login berhasil.'),
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'user', type: 'object', properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'role', type: 'string'),
                    ]),
                    new OA\Property(property: 'token', type: 'string'),
                ]),
            ]
        )),
        new OA\Response(response: 401, description: 'Invalid credentials'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
#[OA\Post(
    path: '/logout',
    summary: 'Logout user',
    description: 'Revoke current access token',
    operationId: 'logout',
    tags: ['Authentication'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Logout successful'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
)]

// ==========================================
// Admin - User Management
// ==========================================
#[OA\Get(
    path: '/admin/users',
    summary: 'List all users',
    operationId: 'adminListUsers',
    tags: ['Admin - User Management'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Success'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Forbidden'),
    ]
)]
#[OA\Post(
    path: '/admin/users',
    summary: 'Create new user',
    description: 'Create user (max 2). Leave balances auto-assigned.',
    operationId: 'adminCreateUser',
    tags: ['Admin - User Management'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'password123'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'User created'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 422, description: 'Validation error or max users reached'),
    ]
)]
#[OA\Put(
    path: '/admin/users/{id}',
    summary: 'Update user',
    operationId: 'adminUpdateUser',
    tags: ['Admin - User Management'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'User updated'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 404, description: 'Not found'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]

// ==========================================
// User - Leave Balance
// ==========================================
#[OA\Get(
    path: '/leave-balances',
    summary: 'View own leave balances',
    description: 'Get current year leave balances',
    operationId: 'viewLeaveBalances',
    tags: ['User - Leave Balance'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Success', content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'leave_type', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                        ]),
                        new OA\Property(property: 'year', type: 'integer'),
                        new OA\Property(property: 'total_quota', type: 'integer'),
                        new OA\Property(property: 'used', type: 'integer'),
                        new OA\Property(property: 'remaining_quota', type: 'integer'),
                    ]
                )),
            ]
        )),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
)]

// ==========================================
// User - Leave Request
// ==========================================
#[OA\Get(
    path: '/leave-requests',
    summary: 'View own leave requests',
    operationId: 'viewLeaveRequests',
    tags: ['User - Leave Request'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Success'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
    ]
)]
#[OA\Post(
    path: '/leave-requests',
    summary: 'Submit leave request',
    description: 'Submit a new leave request. Validates quota and overlap.',
    operationId: 'submitLeaveRequest',
    tags: ['User - Leave Request'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['leave_type_id', 'start_date', 'end_date', 'reason'],
            properties: [
                new OA\Property(property: 'leave_type_id', type: 'integer', example: 1),
                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-04-01'),
                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-04-03'),
                new OA\Property(property: 'reason', type: 'string', example: 'Liburan keluarga'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Leave request created'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 422, description: 'Validation error'),
    ]
)]
#[OA\Patch(
    path: '/leave-requests/{id}/cancel',
    summary: 'Cancel own leave request',
    operationId: 'cancelLeaveRequest',
    tags: ['User - Leave Request'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Request cancelled'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Not your request'),
        new OA\Response(response: 404, description: 'Not found'),
        new OA\Response(response: 422, description: 'Cannot cancel'),
    ]
)]
#[OA\Delete(
    path: '/leave-requests/{id}',
    summary: 'Soft delete own leave request',
    description: 'Soft delete own cancelled or rejected leave request',
    operationId: 'deleteLeaveRequest',
    tags: ['User - Leave Request'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Request deleted'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Not your request'),
        new OA\Response(response: 422, description: 'Cannot delete'),
    ]
)]

// ==========================================
// Admin - Leave Request
// ==========================================
#[OA\Get(
    path: '/admin/leave-requests',
    summary: 'View all leave requests',
    operationId: 'adminListLeaveRequests',
    tags: ['Admin - Leave Request'],
    security: [['bearerAuth' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Success'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Forbidden'),
    ]
)]
#[OA\Patch(
    path: '/admin/leave-requests/{id}/approve',
    summary: 'Approve leave request',
    description: 'Approve a pending leave request. Deducts user leave balance.',
    operationId: 'adminApproveLeaveRequest',
    tags: ['Admin - Leave Request'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'admin_notes', type: 'string', example: 'Disetujui.'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Request approved'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 422, description: 'Cannot approve'),
    ]
)]
#[OA\Patch(
    path: '/admin/leave-requests/{id}/reject',
    summary: 'Reject leave request',
    operationId: 'adminRejectLeaveRequest',
    tags: ['Admin - Leave Request'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    requestBody: new OA\RequestBody(
        required: false,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'admin_notes', type: 'string', example: 'Ditolak.'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Request rejected'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 422, description: 'Cannot reject'),
    ]
)]
#[OA\Delete(
    path: '/admin/leave-requests/{id}',
    summary: 'Soft delete leave request',
    description: 'Soft delete a final-status leave request (admin only)',
    operationId: 'adminDeleteLeaveRequest',
    tags: ['Admin - Leave Request'],
    security: [['bearerAuth' => []]],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
    responses: [
        new OA\Response(response: 200, description: 'Request deleted'),
        new OA\Response(response: 401, description: 'Unauthenticated'),
        new OA\Response(response: 403, description: 'Forbidden'),
        new OA\Response(response: 422, description: 'Cannot delete'),
    ]
)]
class SwaggerDocumentation
{
    // This class serves only as a Swagger documentation container.
    // All API annotations are defined as PHP 8 Attributes above.
}
