import axios from 'axios'
import type { AxiosInstance, InternalAxiosRequestConfig, AxiosResponse, AxiosError } from 'axios'

// ─── Constants ────────────────────────────────────────────────────────────────

export const TOKEN_KEY = 'leavehub_token'
export const USER_KEY = 'leavehub_user'

// ─── Token helpers ────────────────────────────────────────────────────────────

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

export function removeToken(): void {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

// ─── Axios instance ───────────────────────────────────────────────────────────

const api: AxiosInstance = axios.create({
  /**
   * All requests go to /api/... which Vite dev-server proxies to
   * http://127.0.0.1:8000/api/... (see vite.config.ts).
   * In production build point VITE_API_BASE_URL at your real domain.
   */
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  timeout: 15_000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    // Laravel Sanctum SPA: tell it we want JSON, not a redirect
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// ─── Request interceptor — attach Bearer token ────────────────────────────────

api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getToken()
    if (token) {
      config.headers.set('Authorization', `Bearer ${token}`)
    }
    return config
  },
  (error: AxiosError) => Promise.reject(error),
)

// ─── Response interceptor — global error handling ────────────────────────────

api.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error: AxiosError) => {
    if (error.response) {
      const status = error.response.status

      /**
       * 401 Unauthorized — token expired or missing.
       * Clear storage and redirect to /login if not already there.
       */
      if (status === 401) {
        removeToken()
        if (window.location.pathname !== '/login') {
          window.location.href = '/login'
        }
      }

      /**
       * 403 Forbidden — user is authenticated but not authorised
       * for the resource (e.g. a regular user hitting /api/admin/*).
       * We let the caller handle this, but we don't auto-redirect.
       */

      /**
       * 422 Unprocessable Content — Laravel validation errors.
       * Shape: { message: string, errors: Record<string, string[]> }
       * We re-throw so each call-site / the toast plugin can handle it.
       */
    }

    // Always re-throw so individual service functions can catch & react
    return Promise.reject(error)
  },
)

export default api
