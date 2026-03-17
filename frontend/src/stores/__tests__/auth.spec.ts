import { setActivePinia, createPinia } from "pinia";
import { describe, it, expect, beforeEach, vi } from "vitest";
import { useAuthStore } from "../auth";
import * as authService from "@/services/auth.service";
import * as axiosService from "@/services/axios";

// Mock the external services
vi.mock("@/services/auth.service", () => ({
  login: vi.fn(),
  logout: vi.fn(),
  getStoredUser: vi.fn(),
}));

vi.mock("@/services/axios", () => ({
  getToken: vi.fn(),
  setToken: vi.fn(),
  removeToken: vi.fn(),
}));

describe("Auth Store", () => {
  beforeEach(() => {
    // Reset mocks
    vi.clearAllMocks();
    
    // Set up a fresh Pinia instance for each test
    setActivePinia(createPinia());
  });

  it("initializes with empty state when no token/user in storage", () => {
    // Setup mock returns for initial load
    vi.mocked(authService.getStoredUser).mockReturnValue(null);
    vi.mocked(axiosService.getToken).mockReturnValue(null);

    const store = useAuthStore();
    
    expect(store.user).toBeNull();
    expect(store.token).toBeNull();
    expect(store.isAuthenticated).toBe(false);
    expect(store.isAdmin).toBe(false);
  });

  it("initializes with user state when credentials exist in storage", () => {
    const mockUser = { id: 1, name: "Admin", email: "admin@leavehub.com", role: "admin" as const };
    vi.mocked(authService.getStoredUser).mockReturnValue(mockUser);
    vi.mocked(axiosService.getToken).mockReturnValue("fake-token");

    const store = useAuthStore();
    
    expect(store.user).toEqual(mockUser);
    expect(store.token).toBe("fake-token");
    expect(store.isAuthenticated).toBe(true);
    expect(store.isAdmin).toBe(true);
  });

  it("updates state correctly on successful login", async () => {
    const mockUser = { id: 2, name: "User", email: "user@leavehub.com", role: "user" as const };
    vi.mocked(authService.getStoredUser).mockReturnValue(null);
    vi.mocked(axiosService.getToken).mockReturnValue(null);
    
    // Mock the login API call
    vi.mocked(authService.login).mockResolvedValue({
      user: mockUser,
      token: "new-token",
    });

    const store = useAuthStore();
    
    await store.login({ email: "user@leavehub.com", password: "password123" });
    
    expect(authService.login).toHaveBeenCalledWith({ email: "user@leavehub.com", password: "password123" });
    expect(store.user).toEqual(mockUser);
    expect(store.token).toBe("new-token");
    expect(store.isAuthenticated).toBe(true);
    expect(store.isAdmin).toBe(false);
    expect(store.isUser).toBe(true);
  });

  it("handles login failure and sets error message", async () => {
    vi.mocked(authService.getStoredUser).mockReturnValue(null);
    vi.mocked(axiosService.getToken).mockReturnValue(null);
    
    // Mock a 401 error response
    const mockError = {
      response: {
        status: 401,
        data: { message: "Invalid credentials" }
      }
    };
    vi.mocked(authService.login).mockRejectedValue(mockError);

    const store = useAuthStore();
    
    try {
      await store.login({ email: "user@leavehub.com", password: "wrong" });
    } catch (e) {
      // Expected to throw
    }
    
    expect(store.user).toBeNull();
    expect(store.token).toBeNull();
    expect(store.error).toBe("Invalid credentials");
  });

  it("clears state on logout", async () => {
    const mockUser = { id: 1, name: "Admin", email: "admin@leavehub.com", role: "admin" as const };
    vi.mocked(authService.getStoredUser).mockReturnValue(mockUser);
    vi.mocked(axiosService.getToken).mockReturnValue("fake-token");

    const store = useAuthStore();
    
    // Mock successful logout
    vi.mocked(authService.logout).mockResolvedValue();

    await store.logout();
    
    expect(authService.logout).toHaveBeenCalled();
    expect(store.user).toBeNull();
    expect(store.token).toBeNull();
    expect(store.isAuthenticated).toBe(false);
  });
});
