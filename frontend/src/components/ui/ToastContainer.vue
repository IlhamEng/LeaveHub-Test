<script setup lang="ts">
import { useToast } from '@/plugins/toast'
import type { Toast } from '@/types'

const { toasts, removeToast } = useToast()

const iconMap: Record<Toast['type'], string> = {
  success: '✓',
  error: '✕',
  warning: '⚠',
  info: 'ℹ',
}

const colorMap: Record<
  Toast['type'],
  { bar: string; icon: string; iconBg: string; title: string }
> = {
  success: {
    bar: '#10b981',
    icon: '#ffffff',
    iconBg: '#10b981',
    title: '#065f46',
  },
  error: {
    bar: '#ef4444',
    icon: '#ffffff',
    iconBg: '#ef4444',
    title: '#991b1b',
  },
  warning: {
    bar: '#f59e0b',
    icon: '#ffffff',
    iconBg: '#f59e0b',
    title: '#92400e',
  },
  info: {
    bar: '#3b82f6',
    icon: '#ffffff',
    iconBg: '#3b82f6',
    title: '#1e40af',
  },
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-wrapper" role="region" aria-label="Notifikasi" aria-live="polite">
      <TransitionGroup name="toast" tag="div" class="toast-list">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="toast-item"
          :style="{ '--bar-color': colorMap[toast.type].bar }"
          role="alert"
        >
          <!-- Left accent bar -->
          <div class="toast-bar" :style="{ backgroundColor: colorMap[toast.type].bar }" />

          <!-- Icon -->
          <div
            class="toast-icon"
            :style="{
              backgroundColor: colorMap[toast.type].iconBg,
              color: colorMap[toast.type].icon,
            }"
          >
            {{ iconMap[toast.type] }}
          </div>

          <!-- Content -->
          <div class="toast-content">
            <p
              class="toast-title"
              :style="{ color: colorMap[toast.type].title }"
            >
              {{ toast.title }}
            </p>
            <p v-if="toast.message" class="toast-message">
              {{ toast.message }}
            </p>
          </div>

          <!-- Dismiss button -->
          <button
            type="button"
            class="toast-dismiss"
            aria-label="Tutup notifikasi"
            @click="removeToast(toast.id)"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
/* ── Container ──────────────────────────────────────────────────────────────── */
.toast-wrapper {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  pointer-events: none;
  width: 360px;
  max-width: calc(100vw - 32px);
}

.toast-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ── Individual toast ───────────────────────────────────────────────────────── */
.toast-item {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #ffffff;
  border-radius: 10px;
  padding: 12px 12px 12px 0;
  box-shadow:
    0 4px 12px rgba(0, 0, 0, 0.12),
    0 1px 4px rgba(0, 0, 0, 0.07);
  overflow: hidden;
  pointer-events: all;
  border: 1px solid #f0f0f0;
}

/* Left accent bar */
.toast-bar {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  border-radius: 10px 0 0 10px;
  flex-shrink: 0;
}

/* Icon circle */
.toast-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  margin-left: 14px;
  margin-top: 1px;
}

/* Text block */
.toast-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.toast-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 700;
  line-height: 1.3;
  word-break: break-word;
}

.toast-message {
  margin: 0;
  font-size: 0.8rem;
  color: #6b7280;
  line-height: 1.45;
  word-break: break-word;
}

/* Dismiss button */
.toast-dismiss {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: none;
  background: transparent;
  border-radius: 6px;
  color: #9ca3af;
  cursor: pointer;
  transition: background-color 0.15s, color 0.15s;
  margin-top: 1px;
  padding: 0;
}

.toast-dismiss:hover {
  background-color: #f3f4f6;
  color: #374151;
}

/* ── TransitionGroup animations ─────────────────────────────────────────────── */
.toast-enter-active {
  animation: toastSlideIn 0.28s cubic-bezier(0.21, 1.02, 0.73, 1) forwards;
}

.toast-leave-active {
  animation: toastSlideOut 0.22s ease-in forwards;
}

.toast-move {
  transition: transform 0.25s ease;
}

@keyframes toastSlideIn {
  from {
    opacity: 0;
    transform: translateX(100%) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateX(0) scale(1);
  }
}

@keyframes toastSlideOut {
  from {
    opacity: 1;
    transform: translateX(0) scale(1);
    max-height: 120px;
    margin-bottom: 0;
  }
  to {
    opacity: 0;
    transform: translateX(80%) scale(0.95);
    max-height: 0;
    margin-bottom: -10px;
  }
}
</style>
