import api from "./axios";
import type {
  LeaveBalance,
  LeaveRequest,
  LeaveRequestPayload,
  ApiListResponse,
  ApiResponse,
} from "@/types";

// ============================================================
// User - Leave Balances
// ============================================================

/**
 * GET /api/leave-balances
 * Returns the current user's leave quota for the current year.
 * Response shape per item:
 *   { leave_type, total_quota, used, remaining_quota }
 */
export async function getLeaveBalances(): Promise<LeaveBalance[]> {
  const response = await api.get<ApiListResponse<LeaveBalance>>("/leave-balances");
  return response.data.data;
}

// ============================================================
// User - Leave Requests
// ============================================================

/**
 * GET /api/leave-requests
 * Returns all leave request history for the currently logged-in user,
 * including status (pending | approved | rejected | canceled).
 */
export async function getLeaveRequests(): Promise<LeaveRequest[]> {
  const response = await api.get<ApiListResponse<LeaveRequest>>("/leave-requests");
  return response.data.data;
}

/**
 * POST /api/leave-requests
 * Submits a new leave request. Status starts as 'pending'.
 *
 * Backend will throw 422 if:
 *  - The date range overlaps with an existing pending/approved request.
 *  - The user does not have enough remaining quota for the selected leave type.
 *
 * @param payload - { leave_type_id, start_date, end_date, reason }
 *   leave_type_id: 1 = Annual Leave, 2 = Sick Leave
 *   dates must be formatted as "YYYY-MM-DD"
 */
export async function createLeaveRequest(
  payload: LeaveRequestPayload,
): Promise<LeaveRequest> {
  const response = await api.post<ApiResponse<LeaveRequest>>(
    "/leave-requests",
    payload,
  );
  return response.data.data;
}

/**
 * PATCH /api/leave-requests/:id/cancel
 * Cancels a leave request that is still in 'pending' status.
 * The backend will reject (422 / 403) if the request is not pending.
 *
 * @param id - Leave request ID to cancel
 */
export async function cancelLeaveRequest(id: number): Promise<LeaveRequest> {
  const response = await api.patch<ApiResponse<LeaveRequest>>(
    `/leave-requests/${id}/cancel`,
  );
  return response.data.data;
}

/**
 * DELETE /api/leave-requests/:id
 * Soft-deletes a leave request record from the user's history.
 * Only allowed when status is 'canceled' or 'rejected'.
 * The backend will reject (422 / 403) if the request is still pending or approved.
 *
 * @param id - Leave request ID to delete
 */
export async function deleteLeaveRequest(id: number): Promise<void> {
  await api.delete(`/leave-requests/${id}`);
}

// ============================================================
// Utility helpers (pure — no API calls)
// ============================================================

/**
 * Calculates the number of calendar days between two date strings (inclusive).
 * Returns 0 if start > end.
 *
 * @param startDate - "YYYY-MM-DD"
 * @param endDate   - "YYYY-MM-DD"
 */
export function calcDays(startDate: string, endDate: string): number {
  if (!startDate || !endDate) return 0;
  const start = new Date(startDate);
  const end = new Date(endDate);
  if (isNaN(start.getTime()) || isNaN(end.getTime())) return 0;
  const diffMs = end.getTime() - start.getTime();
  if (diffMs < 0) return 0;
  return Math.floor(diffMs / (1000 * 60 * 60 * 24)) + 1;
}

/**
 * Formats a date string "YYYY-MM-DD" to a short locale string.
 * E.g. "2026-03-20" → "20 Mar 2026"
 */
export function formatDate(dateStr: string): string {
  if (!dateStr) return "-";
  const date = new Date(dateStr + "T00:00:00");
  return date.toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

/**
 * Formats a date range into a compact display string.
 * If start === end, returns a single date.
 * E.g. "20 – 22 Mar 2026" or "5 Mar 2026"
 */
export function formatDateRange(startDate: string, endDate: string): string {
  if (!startDate || !endDate) return "-";

  const start = new Date(startDate + "T00:00:00");
  const end = new Date(endDate + "T00:00:00");

  if (startDate === endDate) {
    return formatDate(startDate);
  }

  const startMonth = start.toLocaleDateString("id-ID", { month: "short" });
  const endMonth = end.toLocaleDateString("id-ID", { month: "short" });
  const startYear = start.getFullYear();
  const endYear = end.getFullYear();

  const startDay = start.getDate();
  const endDay = end.getDate();

  // Same month and year → "20 – 22 Mar 2026"
  if (startMonth === endMonth && startYear === endYear) {
    return `${startDay} – ${endDay} ${endMonth} ${endYear}`;
  }

  // Different month, same year → "20 Mar – 5 Apr 2026"
  if (startYear === endYear) {
    return `${startDay} ${startMonth} – ${endDay} ${endMonth} ${endYear}`;
  }

  // Different years → "20 Dec 2025 – 5 Jan 2026"
  return `${formatDate(startDate)} – ${formatDate(endDate)}`;
}
