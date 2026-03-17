import { describe, it, expect, beforeEach, vi, type Mock } from "vitest";
import * as adminService from "../admin.service";
import api from "../axios";
import type { AdminUser, LeaveRequest } from "@/types";

// Mock the axios instance
vi.mock("../axios", () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

describe("Admin Service", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("User Management", () => {
    it("fetches admin users correctly", async () => {
      const mockUsers: AdminUser[] = [
        { id: 1, name: "User 1", email: "user1@example.com", role: "user" },
      ];
      
      (api.get as Mock).mockResolvedValueOnce({
        data: { data: mockUsers, message: "Success" }
      });

      const result = await adminService.getAdminUsers();
      
      expect(api.get).toHaveBeenCalledWith("/admin/users");
      expect(result).toEqual(mockUsers);
    });

    it("creates a new user", async () => {
      const payload = { name: "Test User", email: "test@example.com", password: "password123" };
      const mockUser: AdminUser = { id: 2, ...payload, role: "user" };
      
      (api.post as Mock).mockResolvedValueOnce({
        data: { data: mockUser, message: "Success" }
      });

      const result = await adminService.createAdminUser(payload);
      
      expect(api.post).toHaveBeenCalledWith("/admin/users", payload);
      expect(result).toEqual(mockUser);
    });

    it("updates user, stripping empty password", async () => {
      const payloadWithEmptyPassword = { name: "Updated Name", email: "test@example.com", password: "" };
      const mockUser: AdminUser = { id: 2, name: "Updated Name", email: "test@example.com", role: "user" };
      
      (api.put as Mock).mockResolvedValueOnce({
        data: { data: mockUser, message: "Success" }
      });

      const result = await adminService.updateAdminUser(2, payloadWithEmptyPassword);
      
      // Expected payload should NOT include password because it was empty string
      const expectedPayload = { name: "Updated Name", email: "test@example.com" };
      
      expect(api.put).toHaveBeenCalledWith("/admin/users/2", expectedPayload);
      expect(result).toEqual(mockUser);
    });
  });

  describe("Leave Request Management", () => {
    it("approves a leave request with admin notes", async () => {
      const mockRequest = { id: 1, status: "approved" } as unknown as LeaveRequest;
      
      (api.patch as Mock).mockResolvedValueOnce({
        data: { data: mockRequest, message: "Success" }
      });

      const result = await adminService.approveLeaveRequest(1, "   Approved by Admin   ");
      
      expect(api.patch).toHaveBeenCalledWith("/admin/leave-requests/1/approve", {
        admin_notes: "Approved by Admin" // should be trimmed
      });
      expect(result).toEqual(mockRequest);
    });

    it("approves a leave request without admin notes", async () => {
      const mockRequest = { id: 1, status: "approved" } as unknown as LeaveRequest;
      
      (api.patch as Mock).mockResolvedValueOnce({
        data: { data: mockRequest, message: "Success" }
      });

      const result = await adminService.approveLeaveRequest(1, "");
      
      expect(api.patch).toHaveBeenCalledWith("/admin/leave-requests/1/approve", {});
      expect(result).toEqual(mockRequest);
    });

    it("rejects a leave request with mapped notes", async () => {
      const mockRequest = { id: 2, status: "rejected" } as unknown as LeaveRequest;
      
      (api.patch as Mock).mockResolvedValueOnce({
        data: { data: mockRequest, message: "Success" }
      });

      const result = await adminService.rejectLeaveRequest(2, "Kuota tidak cukup");
      
      expect(api.patch).toHaveBeenCalledWith("/admin/leave-requests/2/reject", {
        admin_notes: "Kuota tidak cukup"
      });
      expect(result).toEqual(mockRequest);
    });

    it("deletes a leave request", async () => {
      (api.delete as Mock).mockResolvedValueOnce({
        data: { message: "Success" }
      });

      await adminService.deleteAdminLeaveRequest(3);
      
      expect(api.delete).toHaveBeenCalledWith("/admin/leave-requests/3");
    });
  });
});
