import { createRouter, createWebHistory } from "vue-router";
import type { RouteRecordRaw } from "vue-router";
import { useAuthStore } from "@/stores/auth";

// ─── Lazy-loaded views ────────────────────────────────────────────────────────

const LoginView = () => import("@/views/LoginView.vue");

// Admin views
const AdminManageUsersView = () => import("@/views/admin/ManageUsersView.vue");
const AdminLeaveRequestsView = () =>
  import("@/views/admin/LeaveRequestsView.vue");

// User views
const UserLeaveBalanceView = () => import("@/views/user/LeaveBalanceView.vue");
const UserApplyLeaveView = () => import("@/views/user/ApplyLeaveView.vue");
const UserLeaveHistoryView = () => import("@/views/user/LeaveHistoryView.vue");

// ─── Route definitions ────────────────────────────────────────────────────────

const routes: RouteRecordRaw[] = [
  // ── Public ──────────────────────────────────────────────────────────────────
  {
    path: "/login",
    name: "login",
    component: LoginView,
    meta: {
      requiresAuth: false,
      title: "Login — LeaveHub",
    },
  },

  // ── Root redirect ────────────────────────────────────────────────────────────
  {
    path: "/",
    redirect: () => {
      // Will be handled by the navigation guard below
      return "/login";
    },
  },

  // ── Admin routes ─────────────────────────────────────────────────────────────
  {
    path: "/admin",
    redirect: { name: "admin.users" },
    meta: { requiresAuth: true, role: "admin" },
    children: [
      {
        path: "users",
        name: "admin.users",
        component: AdminManageUsersView,
        meta: {
          requiresAuth: true,
          role: "admin",
          title: "Kelola User — LeaveHub",
        },
      },
      {
        path: "leave-requests",
        name: "admin.leave-requests",
        component: AdminLeaveRequestsView,
        meta: {
          requiresAuth: true,
          role: "admin",
          title: "Leave Requests — LeaveHub",
        },
      },
    ],
  },

  // ── User routes ──────────────────────────────────────────────────────────────
  {
    path: "/user",
    redirect: { name: "user.balance" },
    meta: { requiresAuth: true, role: "user" },
    children: [
      {
        path: "balance",
        name: "user.balance",
        component: UserLeaveBalanceView,
        meta: {
          requiresAuth: true,
          role: "user",
          title: "Sisa Kuota Cuti — LeaveHub",
        },
      },
      {
        path: "apply",
        name: "user.apply",
        component: UserApplyLeaveView,
        meta: {
          requiresAuth: true,
          role: "user",
          title: "Ajukan Cuti — LeaveHub",
        },
      },
      {
        path: "history",
        name: "user.history",
        component: UserLeaveHistoryView,
        meta: {
          requiresAuth: true,
          role: "user",
          title: "Riwayat Cuti — LeaveHub",
        },
      },
    ],
  },

  // ── 404 catch-all ────────────────────────────────────────────────────────────
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    redirect: "/login",
  },
];

// ─── Router instance ──────────────────────────────────────────────────────────

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) return savedPosition;
    return { top: 0, behavior: "smooth" };
  },
});

// ─── Navigation Guard ─────────────────────────────────────────────────────────

router.beforeEach((to, _from, next) => {
  // Update document title
  if (to.meta?.title) {
    document.title = to.meta.title as string;
  }

  // We access the store inside the guard (after pinia is installed)
  const auth = useAuthStore();

  const requiresAuth = to.meta?.requiresAuth !== false;
  const requiredRole = to.meta?.role as string | undefined;

  // ── 1. Not authenticated → send to /login ──────────────────────────────────
  if (requiresAuth && !auth.isAuthenticated) {
    return next({ name: "login", query: { redirect: to.fullPath } });
  }

  // ── 2. Already authenticated → redirect away from /login ──────────────────
  if (to.name === "login" && auth.isAuthenticated) {
    if (auth.isAdmin) return next({ name: "admin.users" });
    return next({ name: "user.balance" });
  }

  // ── 3. Root path "/" → redirect based on role ─────────────────────────────
  if (to.path === "/" && auth.isAuthenticated) {
    if (auth.isAdmin) return next({ name: "admin.users" });
    return next({ name: "user.balance" });
  }

  // ── 4. Role mismatch → redirect to the correct dashboard ──────────────────
  if (requiredRole && auth.isAuthenticated) {
    if (requiredRole === "admin" && !auth.isAdmin) {
      // A regular user trying to access an admin route
      return next({ name: "user.balance" });
    }
    if (requiredRole === "user" && !auth.isUser) {
      // An admin trying to access a user route
      return next({ name: "admin.users" });
    }
  }

  next();
});

export default router;
