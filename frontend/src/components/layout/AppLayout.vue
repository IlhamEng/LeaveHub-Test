<script setup lang="ts">
import { useAuthStore } from "@/stores/auth";
import AppSidebar from "./AppSidebar.vue";

const auth = useAuthStore();
</script>

<template>
    <div class="app-layout">
        <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
        <AppSidebar />

        <!-- ── Main area ───────────────────────────────────────────────────────── -->
        <div class="app-main">
            <!-- Top-right role indicator bar -->
            <div class="role-bar">
                <span class="role-bar__label">Role:</span>
                <span
                    class="role-chip"
                    :class="{ 'role-chip--active': auth.isAdmin }"
                >
                    Admin
                </span>
                <span
                    class="role-chip"
                    :class="{ 'role-chip--active': auth.isUser }"
                >
                    User
                </span>
                <span class="show-all-chip"> 🎭 Show All </span>
            </div>

            <!-- Page content -->
            <main class="app-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
/* ── Root layout ─────────────────────────────────────────────────────────── */
.app-layout {
    display: flex;
    min-height: 100vh;
    background-color: #f3f4f6;
}

/* ── Main column ─────────────────────────────────────────────────────────── */
.app-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    position: relative;
}

/* ── Role indicator bar (top-right) ──────────────────────────────────────── */
.role-bar {
    position: absolute;
    top: 16px;
    right: 20px;
    display: flex;
    align-items: center;
    gap: 4px;
    background-color: #1a1d2e;
    border-radius: 10px;
    padding: 5px 10px;
    z-index: 100;
}

.role-bar__label {
    font-size: 0.72rem;
    color: #6b7280;
    font-weight: 500;
    margin-right: 2px;
    letter-spacing: 0.02em;
}

.role-chip {
    padding: 3px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #9ca3af;
    background-color: transparent;
    transition:
        background-color 0.15s,
        color 0.15s;
    cursor: default;
    letter-spacing: 0.01em;
}

.role-chip--active {
    background-color: #4f46e5;
    color: #ffffff;
}

.show-all-chip {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.72rem;
    font-weight: 500;
    color: #9ca3af;
    background-color: #2a2d3e;
    margin-left: 2px;
    cursor: default;
    white-space: nowrap;
}

/* ── Page content ────────────────────────────────────────────────────────── */
.app-content {
    flex: 1;
    padding: 32px 36px 40px;
    overflow-y: auto;
    min-height: 100vh;
}
</style>
