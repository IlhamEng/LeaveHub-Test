import api, { setToken, removeToken, getToken, USER_KEY } from "./axios";
import type {
  LoginPayload,
  LoginResponse,
  LogoutResponse,
  User,
} from "@/types";

// ─── Auth Service ─────────────────────────────────────────────────────────────

/**
 * POST /api/login
 * Authenticates the user and persists the Sanctum PAT + user object
 * to localStorage so they survive page refreshes.
 */
export async function login(
  payload: LoginPayload,
): Promise<LoginResponse["data"]> {
  const response = await api.post<LoginResponse>("/login", payload);
  const { token, user } = response.data.data;

  // Persist token and user to localStorage
  setToken(token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));

  return { token, user };
}

/**
 * POST /api/logout
 * Revokes the current Sanctum PAT on the server, then clears local storage.
 */
export async function logout(): Promise<void> {
  try {
    await api.post<LogoutResponse>("/logout");
  } finally {
    // Always clear local state even if the API call fails
    // (e.g. token already expired / network error)
    removeToken();
  }
}

// ─── Local-storage helpers ────────────────────────────────────────────────────

/**
 * Returns the currently persisted user object from localStorage,
 * or null if no session exists.
 */
export function getStoredUser(): User | null {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) return null;
  try {
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

/**
 * Returns true when a token is present in localStorage.
 * Does NOT validate the token against the server.
 */
export function isAuthenticated(): boolean {
  return Boolean(getToken());
}
