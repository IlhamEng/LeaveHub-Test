import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { login as loginService, logout as logoutService, getStoredUser } from "@/services/auth.service";
import { getToken } from "@/services/axios";
import type { User, LoginPayload } from "@/types";

export const useAuthStore = defineStore("auth", () => {
  // ─── State ───────────────────────────────────────────────────────────────────

  const user = ref<User | null>(getStoredUser());
  const token = ref<string | null>(getToken());
  const loading = ref(false);
  const error = ref<string | null>(null);

  // ─── Getters ─────────────────────────────────────────────────────────────────

  const isAuthenticated = computed(() => Boolean(token.value && user.value));
  const isAdmin = computed(() => user.value?.role === "admin");
  const isUser = computed(() => user.value?.role === "user");

  const userInitial = computed(() => {
    if (!user.value?.name) return "?";
    return user.value.name.charAt(0).toUpperCase();
  });

  // ─── Actions ─────────────────────────────────────────────────────────────────

  /**
   * Calls POST /api/login, persists token + user to localStorage via the
   * auth service, then hydrates the store's reactive state.
   *
   * Throws the raw Axios error so the Login view can handle 422 / 401 etc.
   */
  async function login(payload: LoginPayload): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
      const data = await loginService(payload);
      token.value = data.token;
      user.value = data.user;
    } catch (err: unknown) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const axiosErr = err as any;
      if (axiosErr?.response?.status === 422 || axiosErr?.response?.status === 401) {
        error.value =
          axiosErr.response.data?.message ??
          "Email atau password salah. Silakan coba lagi.";
      } else {
        error.value = "Tidak dapat terhubung ke server. Periksa koneksi Anda.";
      }
      throw err;
    } finally {
      loading.value = false;
    }
  }

  /**
   * Calls POST /api/logout (revokes the Sanctum PAT on the server),
   * then clears all local state regardless of whether the API call succeeded.
   */
  async function logout(): Promise<void> {
    loading.value = true;
    try {
      await logoutService();
    } finally {
      token.value = null;
      user.value = null;
      loading.value = false;
    }
  }

  /**
   * Rehydrates the store from localStorage.
   * Call this once in App.vue or the router guard on app startup
   * to restore the session without a full re-login.
   */
  function rehydrate(): void {
    const storedUser = getStoredUser();
    const storedToken = getToken();

    if (storedUser && storedToken) {
      user.value = storedUser;
      token.value = storedToken;
    } else {
      user.value = null;
      token.value = null;
    }
  }

  /**
   * Clears any lingering auth error message (e.g. after the user
   * starts typing again in the login form).
   */
  function clearError(): void {
    error.value = null;
  }

  return {
    // state
    user,
    token,
    loading,
    error,
    // getters
    isAuthenticated,
    isAdmin,
    isUser,
    userInitial,
    // actions
    login,
    logout,
    rehydrate,
    clearError,
  };
});
