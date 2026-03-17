<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/plugins/toast'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// ─── Nav items per role ──────────────────────────────────────────────────────

interface NavItem {
  label: string
  routeName: string
  emoji: string
}

const adminNav: NavItem[] = [
  { label: 'Kelola User',     routeName: 'admin.users',          emoji: '👤' },
  { label: 'Leave Requests',  routeName: 'admin.leave-requests', emoji: '📋' },
]

const userNav: NavItem[] = [
  { label: 'Sisa Kuota',   routeName: 'user.balance', emoji: '📊' },
  { label: 'Ajukan Cuti',  routeName: 'user.apply',   emoji: '🖊️' },
  { label: 'Riwayat Cuti', routeName: 'user.history', emoji: '🗂️' },
]

const navItems = computed<NavItem[]>(() =>
  auth.isAdmin ? adminNav : userNav,
)

const menuLabel = computed<string>(() =>
  auth.isAdmin ? 'ADMIN MENU' : 'USER MENU',
)

// ─── Active check ────────────────────────────────────────────────────────────

function isActive(routeName: string): boolean {
  return route.name === routeName
}

// ─── Logout ──────────────────────────────────────────────────────────────────

async function handleLogout() {
  try {
    await auth.logout()
    router.push({ name: 'login' })
    toast.success('Logout berhasil', 'Sampai jumpa!')
  } catch {
    // Even on error, local state is cleared; redirect anyway
    router.push({ name: 'login' })
  }
}
</script>

<template>
  <aside class="sidebar">
    <!-- ── Brand ──────────────────────────────────────────────────────────── -->
    <div class="sidebar-brand">
      <div class="brand-logo">
        <span class="brand-leave">Leave</span><span class="brand-hub">Hub</span>
      </div>
      <p class="brand-tagline">Leave Request Management</p>
    </div>

    <!-- ── Navigation ────────────────────────────────────────────────────── -->
    <nav class="sidebar-nav">
      <p class="nav-section-label">{{ menuLabel }}</p>

      <ul class="nav-list">
        <li v-for="item in navItems" :key="item.routeName">
          <RouterLink
            :to="{ name: item.routeName }"
            class="nav-link"
            :class="{ 'nav-link--active': isActive(item.routeName) }"
          >
            <span class="nav-emoji" role="img" aria-hidden="true">{{ item.emoji }}</span>
            <span class="nav-label">{{ item.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <!-- ── Spacer ─────────────────────────────────────────────────────────── -->
    <div class="sidebar-spacer" />

    <!-- ── User profile & logout ──────────────────────────────────────────── -->
    <div class="sidebar-footer">
      <div class="user-info">
        <!-- Avatar -->
        <div class="user-avatar">
          {{ auth.userInitial }}
        </div>

        <!-- Name + email -->
        <div class="user-details">
          <p class="user-name">{{ auth.user?.name ?? '—' }}</p>
          <p class="user-email">{{ auth.user?.email ?? '—' }}</p>
        </div>
      </div>

      <!-- Logout button -->
      <button
        type="button"
        class="logout-btn"
        :disabled="auth.loading"
        aria-label="Logout"
        title="Logout"
        @click="handleLogout"
      >
        <!-- Power icon -->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="16"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M18.36 6.64a9 9 0 1 1-12.73 0" />
          <line x1="12" y1="2" x2="12" y2="12" />
        </svg>
      </button>
    </div>
  </aside>
</template>

<style scoped>
/* ── Sidebar shell ────────────────────────────────────────────────────────── */
.sidebar {
  width: 210px;
  min-width: 210px;
  height: 100vh;
  background-color: #1a1d2e;
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  overflow: hidden;
  flex-shrink: 0;
}

/* ── Brand ────────────────────────────────────────────────────────────────── */
.sidebar-brand {
  padding: 22px 20px 18px;
  border-bottom: 1px solid #2a2d3e;
}

.brand-logo {
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1;
  margin-bottom: 3px;
}

.brand-leave {
  color: #ffffff;
}

.brand-hub {
  color: #818cf8;
}

.brand-tagline {
  margin: 0;
  font-size: 0.67rem;
  color: #6b7280;
  letter-spacing: 0.01em;
  line-height: 1.3;
}

/* ── Nav ──────────────────────────────────────────────────────────────────── */
.sidebar-nav {
  padding: 20px 12px 8px;
  flex-shrink: 0;
}

.nav-section-label {
  margin: 0 0 10px 8px;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: #4b5263;
  text-transform: uppercase;
}

.nav-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* ── Nav link ─────────────────────────────────────────────────────────────── */
.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 8px;
  text-decoration: none;
  color: #a0a3b1;
  font-size: 0.875rem;
  font-weight: 500;
  transition: background-color 0.15s, color 0.15s;
  line-height: 1;
}

.nav-link:hover:not(.nav-link--active) {
  background-color: #2a2d3e;
  color: #d1d5db;
}

.nav-link--active {
  background-color: #4f46e5;
  color: #ffffff;
  font-weight: 600;
}

.nav-emoji {
  font-size: 1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
  line-height: 1;
}

.nav-label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Spacer ───────────────────────────────────────────────────────────────── */
.sidebar-spacer {
  flex: 1;
}

/* ── Footer / user profile ────────────────────────────────────────────────── */
.sidebar-footer {
  padding: 12px 14px;
  border-top: 1px solid #2a2d3e;
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background-color: #4f46e5;
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  font-weight: 700;
  flex-shrink: 0;
  letter-spacing: 0;
}

.user-details {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.user-name {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 600;
  color: #e5e7eb;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}

.user-email {
  margin: 0;
  font-size: 0.68rem;
  color: #6b7280;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.3;
}

/* ── Logout button ────────────────────────────────────────────────────────── */
.logout-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: none;
  background: transparent;
  border-radius: 6px;
  color: #6b7280;
  cursor: pointer;
  transition: background-color 0.15s, color 0.15s;
  padding: 0;
}

.logout-btn:hover:not(:disabled) {
  background-color: #2a2d3e;
  color: #ef4444;
}

.logout-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
