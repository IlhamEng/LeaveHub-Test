import api from "./axios";
import type {
  AdminUser,
  CreateUserPayload,
  UpdateUserPayload,
  LeaveRequest,
  AdminActionPayload,
  ApiListResponse,
  ApiResponse,
} from "@/types";

// ============================================================
// Admin - User Management
// ============================================================

/**
 * GET /api/admin/users
 * Returns all regular users (role = 'user'). Admin is excluded.
 */
export async function getAdminUsers(): Promise<AdminUser[]> {
  const response = await api.get<ApiListResponse<AdminUser>>("/admin/users");
  return response.data.data;
}

/**
 * POST /api/admin/users
 * Creates a new regular user.
 * Backend auto-assigns default leave balances:
 *   Annual Leave = 12 days, Sick Leave = 6 days.
 * Throws 422 if the max-user limit (2) has been reached.
 */
export async function createAdminUser(
  payload: CreateUserPayload,
): Promise<AdminUser> {
  const response = await api.post<ApiResponse<AdminUser>>(
    "/admin/users",
    payload,
  );
  return response.data.data;
}

/**
 * PUT /api/admin/users/:id
 * Updates an existing user's details.
 * Password is optional — omit (or pass empty string) to leave it unchanged.
 */
export async function updateAdminUser(
  id: number,
  payload: UpdateUserPayload,
): Promise<AdminUser> {
  // Strip empty password so the backend doesn't try to hash an empty string
  const body: UpdateUserPayload = { ...payload };
  if (!body.password) {
    delete body.password;
  }

  const response = await api.put<ApiResponse<AdminUser>>(
    `/admin/users/${id}`,
    body,
  );
  return response.data.data;
}

// ============================================================
// Admin - Leave Request Management
// ============================================================

/**
 * GET /api/admin/leave-requests
 * Returns all leave requests from every user,
 * with nested `user` and `leave_type` relations.
 */
export async function getAdminLeaveRequests(): Promise<LeaveRequest[]> {
  const response = await api.get<ApiListResponse<LeaveRequest>>(
    "/admin/leave-requests",
  );
  return response.data.data;
}

/**
 * PATCH /api/admin/leave-requests/:id/approve
 * Approves a pending leave request.
 * Also deducts the used days from the user's leave balance on the backend.
 *
 * @param id          - Leave request ID
 * @param adminNotes  - Optional approval note visible to the user
 */
export async function approveLeaveRequest(
  id: number,
  adminNotes?: string,
): Promise<LeaveRequest> {
  const payload: AdminActionPayload = {};
  if (adminNotes && adminNotes.trim().length > 0) {
    payload.admin_notes = adminNotes.trim();
  }

  const response = await api.patch<ApiResponse<LeaveRequest>>(
    `/admin/leave-requests/${id}/approve`,
    payload,
  );
  return response.data.data;
}

/**
 * PATCH /api/admin/leave-requests/:id/reject
 * Rejects a pending leave request.
 * Leave balance is NOT deducted.
 *
 * @param id          - Leave request ID
 * @param adminNotes  - Optional rejection reason visible to the user
 */
export async function rejectLeaveRequest(
  id: number,
  adminNotes?: string,
): Promise<LeaveRequest> {
  const payload: AdminActionPayload = {};
  if (adminNotes && adminNotes.trim().length > 0) {
    payload.admin_notes = adminNotes.trim();
  }

  const response = await api.patch<ApiResponse<LeaveRequest>>(
    `/admin/leave-requests/${id}/reject`,
    payload,
  );
  return response.data.data;
}

/**
 * DELETE /api/admin/leave-requests/:id
 * Soft-deletes a leave request history record.
 * Only allowed for requests that are NOT pending
 * (i.e., approved | rejected | canceled).
 */
export async function deleteAdminLeaveRequest(id: number): Promise<void> {
  await api.delete(`/admin/leave-requests/${id}`);
}
