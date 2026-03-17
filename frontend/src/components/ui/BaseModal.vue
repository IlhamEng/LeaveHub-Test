<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

interface Props {
  title?: string
  subtitle?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  closable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  subtitle: '',
  size: 'md',
  closable: true,
})

const emit = defineEmits<{
  close: []
}>()

function handleClose() {
  if (props.closable) {
    emit('close')
  }
}

function handleOverlayClick(e: MouseEvent) {
  if (e.target === e.currentTarget) {
    handleClose()
  }
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.closable) {
    handleClose()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.body.style.overflow = 'hidden'
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})

const sizeClasses: Record<string, string> = {
  sm: 'modal-sm',
  md: 'modal-md',
  lg: 'modal-lg',
  xl: 'modal-xl',
}
</script>

<template>
  <!-- Overlay -->
  <Teleport to="body">
    <div
      class="modal-overlay"
      role="dialog"
      aria-modal="true"
      :aria-label="title || 'Modal'"
      @click="handleOverlayClick"
    >
      <!-- Panel -->
      <div class="modal-content" :class="sizeClasses[size]">
        <!-- Header -->
        <div v-if="title || $slots.header" class="modal-header">
          <slot name="header">
            <div class="modal-header-text">
              <h2 class="modal-title">{{ title }}</h2>
              <p v-if="subtitle" class="modal-subtitle">{{ subtitle }}</p>
            </div>
          </slot>
          <button
            v-if="closable"
            type="button"
            class="modal-close-btn"
            aria-label="Tutup modal"
            @click="handleClose"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
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

        <!-- Body -->
        <div class="modal-body">
          <slot />
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 1000;
  background-color: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: overlayFadeIn 0.2s ease-out forwards;
}

.modal-content {
  position: relative;
  background: #ffffff;
  border-radius: 14px;
  box-shadow:
    0 20px 60px rgba(0, 0, 0, 0.2),
    0 4px 16px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalScaleIn 0.2s ease-out forwards;
}

/* Sizes */
.modal-sm  { max-width: 400px; }
.modal-md  { max-width: 520px; }
.modal-lg  { max-width: 680px; }
.modal-xl  { max-width: 860px; }

/* Header */
.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f0f0f0;
  flex-shrink: 0;
}

.modal-header-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.modal-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #111827;
  line-height: 1.3;
}

.modal-subtitle {
  margin: 0;
  font-size: 0.8rem;
  color: #6b7280;
}

/* Close button */
.modal-close-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: none;
  background: transparent;
  border-radius: 6px;
  color: #9ca3af;
  cursor: pointer;
  transition: background-color 0.15s, color 0.15s;
  margin-top: -2px;
}

.modal-close-btn:hover {
  background-color: #f3f4f6;
  color: #374151;
}

/* Body */
.modal-body {
  padding: 20px 24px;
  overflow-y: auto;
  flex: 1;
}

/* Footer */
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 14px 24px;
  border-top: 1px solid #f0f0f0;
  flex-shrink: 0;
  background: #fafafa;
  border-radius: 0 0 14px 14px;
}

/* Animations */
@keyframes overlayFadeIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}

@keyframes modalScaleIn {
  from {
    opacity: 0;
    transform: scale(0.94) translateY(-10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
</style>
