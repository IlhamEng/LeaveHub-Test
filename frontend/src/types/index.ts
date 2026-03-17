// ============================================================
// Auth Types
// ============================================================

export interface User {
  id: number
  name: string
  email: string
  role: 'admin' | 'user'
}

export interface LoginPayload {
  email: string
  password: string
}

export interface LoginResponse {
  data: {
    user: User
    token: string
  }
  message?: string
}

export interface LogoutResponse {
  message: string
}

// ============================================================
// Leave Type
// ============================================================

export interface LeaveType {
  id: number
  name: string // "Annual Leave" | "Sick Leave"
}

// ============================================================
// Leave Balance Types
// ============================================================

export interface LeaveBalance {
  id: number
  user_id: number
  leave_type_id: number
  year: number
  total_quota: number
  used: number
  remaining_quota: number
  leave_type: LeaveType
}

// ============================================================
// Leave Request Types
// ============================================================

export type LeaveStatus = 'pending' | 'approved' | 'rejected' | 'canceled'

export interface LeaveRequest {
  id: number
  user_id: number
  leave_type_id: number
  start_date: string       // YYYY-MM-DD
  end_date: string         // YYYY-MM-DD
  total_days: number
  reason: string
  status: LeaveStatus
  admin_notes: string | null
  responded_at: string | null
  created_at: string
  updated_at: string
  user?: User
  leave_type?: LeaveType
}

export interface LeaveRequestPayload {
  leave_type_id: number
  start_date: string
  end_date: string
  reason: string
}

export interface AdminActionPayload {
  admin_notes?: string
}

// ============================================================
// Admin - User Management Types
// ============================================================

export interface AdminUser {
  id: number
  name: string
  email: string
  role: 'user'
  leave_balances?: LeaveBalance[]
  created_at?: string
  updated_at?: string
}

export interface CreateUserPayload {
  name: string
  email: string
  password: string
}

export interface UpdateUserPayload {
  name: string
  email: string
  password?: string
}

// ============================================================
// API Response Wrappers
// ============================================================

export interface ApiResponse<T> {
  data: T
  message?: string
}

export interface ApiListResponse<T> {
  data: T[]
  message?: string
}

/**
 * Laravel 422 Unprocessable Content validation error shape.
 * errors is a map of field name -> array of error messages.
 */
export interface ValidationError {
  message: string
  errors: Record<string, string[]>
}

// ============================================================
// Toast / Notification Types
// ============================================================

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: string
  type: ToastType
  title: string
  message?: string
  duration?: number // ms, default 4000
}

// ============================================================
// Router Meta Types
// ============================================================

export type RequiredRole = 'admin' | 'user' | 'any'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    role?: RequiredRole
    title?: string
  }
}

// ============================================================
// UI Helper Types
// ============================================================

export type ModalMode = 'create' | 'edit' | null

export interface ConfirmAction {
  leaveRequestId: number
  type: 'approve' | 'reject' | 'cancel' | 'delete'
  userName?: string
  leaveSummary?: string
}
