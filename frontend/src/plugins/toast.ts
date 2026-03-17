import { ref, readonly } from 'vue'
import type { Toast, ToastType } from '@/types'

// ─── State ───────────────────────────────────────────────────────────────────

const toasts = ref<Toast[]>([])

// ─── Helpers ─────────────────────────────────────────────────────────────────

function generateId(): string {
  return `toast-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

// ─── Core add function ────────────────────────────────────────────────────────

function addToast(
  type: ToastType,
  title: string,
  message?: string,
  duration = 4000,
): string {
  const id = generateId()

  const toast: Toast = { id, type, title, message, duration }
  toasts.value.push(toast)

  if (duration > 0) {
    setTimeout(() => removeToast(id), duration)
  }

  return id
}

// ─── Remove ───────────────────────────────────────────────────────────────────

function removeToast(id: string): void {
  const index = toasts.value.findIndex((t) => t.id === id)
  if (index !== -1) {
    toasts.value.splice(index, 1)
  }
}

function clearAll(): void {
  toasts.value = []
}

// ─── Typed convenience helpers ────────────────────────────────────────────────

function success(title: string, message?: string, duration?: number): string {
  return addToast('success', title, message, duration)
}

function error(title: string, message?: string, duration?: number): string {
  return addToast('error', title, message, duration ?? 6000)
}

function warning(title: string, message?: string, duration?: number): string {
  return addToast('warning', title, message, duration)
}

function info(title: string, message?: string, duration?: number): string {
  return addToast('info', title, message, duration)
}

/**
 * Parse a Laravel 422 validation error response and surface the messages
 * as a single error toast.
 */
function apiError(err: unknown, fallback = 'Terjadi kesalahan.'): void {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const axiosErr = err as any

  if (axiosErr?.response) {
    const status: number = axiosErr.response.status
    const data = axiosErr.response.data

    if (status === 422 && data?.errors) {
      // Flatten all validation messages into a readable list
      const messages: string[] = Object.values(data.errors as Record<string, string[]>).flat()
      error('Validasi Gagal', messages.join(' • '))
      return
    }

    if (status === 403) {
      error('Akses Ditolak', data?.message ?? 'Anda tidak memiliki izin.')
      return
    }

    if (status === 401) {
      error('Sesi Berakhir', 'Silakan login kembali.')
      return
    }

    if (data?.message) {
      error('Error', data.message)
      return
    }
  }

  error('Error', fallback)
}

// ─── Composable ───────────────────────────────────────────────────────────────

export function useToast() {
  return {
    toasts: readonly(toasts),
    addToast,
    removeToast,
    clearAll,
    success,
    error,
    warning,
    info,
    apiError,
  }
}

// ─── Default export (singleton-style direct usage) ───────────────────────────

export const toast = {
  success,
  error,
  warning,
  info,
  apiError,
  remove: removeToast,
  clear: clearAll,
}

export default toast
