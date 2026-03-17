<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { toast } from '@/plugins/toast'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// ─── Form state ───────────────────────────────────────────────────────────────

const form = ref({
  email: '',
  password: '',
})

const showPassword = ref(false)
const fieldErrors = ref<{ email?: string; password?: string }>({})

// ─── Validation ───────────────────────────────────────────────────────────────

function validateForm(): boolean {
  fieldErrors.value = {}

  if (!form.value.email.trim()) {
    fieldErrors.value.email = 'Email wajib diisi.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email.trim())) {
    fieldErrors.value.email = 'Format email tidak valid.'
  }

  if (!form.value.password) {
    fieldErrors.value.password = 'Password wajib diisi.'
  } else if (form.value.password.length < 6) {
    fieldErrors.value.password = 'Password minimal 6 karakter.'
  }

  return Object.keys(fieldErrors.value).length === 0
}

function clearFieldError(field: 'email' | 'password') {
  if (fieldErrors.value[field]) {
    delete fieldErrors.value[field]
  }
  if (auth.error) {
    auth.clearError()
  }
}

// ─── Submit ───────────────────────────────────────────────────────────────────

const isLoading = computed(() => auth.loading)

async function handleSubmit() {
  if (!validateForm()) return

  try {
    await auth.login({
      email: form.value.email.trim(),
      password: form.value.password,
    })

    toast.success(
      'Login berhasil!',
      `Selamat datang, ${auth.user?.name ?? 'pengguna'}.`,
    )

    // Redirect: honour ?redirect= query param, otherwise go to role dashboard
    const redirectTo = route.query.redirect as string | undefined

    if (redirectTo) {
      await router.push(redirectTo)
    } else if (auth.isAdmin) {
      await router.push({ name: 'admin.users' })
    } else {
      await router.push({ name: 'user.balance' })
    }
  } catch (err: unknown) {
    // auth store already set auth.error; map 422 field errors if present
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const axiosErr = err as any
    if (axiosErr?.response?.status === 422 && axiosErr.response.data?.errors) {
      const errs = axiosErr.response.data.errors as Record<string, string[]>
      if (errs.email)    fieldErrors.value.email    = errs.email[0]
      if (errs.password) fieldErrors.value.password = errs.password[0]
    }
    // Toast is not shown here — the inline error below the form handles UX
  }
}
</script>

<template>
  <div class="login-page">
    <!-- ── Card ─────────────────────────────────────────────────────────────── -->
    <div class="login-card">
      <!-- Brand -->
      <div class="login-brand">
        <h1 class="brand-title">
          <span class="brand-leave">Leave</span><span class="brand-hub">Hub</span>
        </h1>
        <p class="brand-subtitle">Leave Request Management System</p>
      </div>

      <!-- Form -->
      <form class="login-form" novalidate @submit.prevent="handleSubmit">
        <!-- Email field -->
        <div class="field-group">
          <label for="email" class="field-label">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="field-input"
            :class="{ 'field-input--error': fieldErrors.email }"
            placeholder="admin@leavehub.com"
            autocomplete="email"
            autofocus
            :disabled="isLoading"
            @input="clearFieldError('email')"
          />
          <p v-if="fieldErrors.email" class="field-error">
            {{ fieldErrors.email }}
          </p>
        </div>

        <!-- Password field -->
        <div class="field-group">
          <label for="password" class="field-label">Password</label>
          <div class="password-wrapper">
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="field-input password-input"
              :class="{ 'field-input--error': fieldErrors.password }"
              placeholder="••••••••••"
              autocomplete="current-password"
              :disabled="isLoading"
              @input="clearFieldError('password')"
              @keyup.enter="handleSubmit"
            />
            <button
              type="button"
              class="password-toggle"
              :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
              tabindex="-1"
              @click="showPassword = !showPassword"
            >
              <!-- Eye icon (show) -->
              <svg
                v-if="!showPassword"
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
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              <!-- Eye-off icon (hide) -->
              <svg
                v-else
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
                <path
                  d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
                     a18.45 18.45 0 0 1 5.06-5.94"
                />
                <path
                  d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
                     a18.5 18.5 0 0 1-2.16 3.19"
                />
                <line x1="1" y1="1" x2="23" y2="23" />
              </svg>
            </button>
          </div>
          <p v-if="fieldErrors.password" class="field-error">
            {{ fieldErrors.password }}
          </p>
        </div>

        <!-- General API error banner -->
        <div v-if="auth.error" class="error-banner" role="alert">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="error-banner__icon"
          >
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          <span>{{ auth.error }}</span>
        </div>

        <!-- Submit button -->
        <button
          type="submit"
          class="submit-btn"
          :disabled="isLoading"
        >
          <span v-if="isLoading" class="submit-btn__spinner" aria-hidden="true" />
          <span>{{ isLoading ? 'Memproses...' : 'Login' }}</span>
        </button>
      </form>

      <!-- Footer note -->
      <p class="login-footnote">
        Sanctum PAT &nbsp;·&nbsp; No register endpoint
      </p>
    </div>
  </div>
</template>

<style scoped>
/* ── Page wrapper ─────────────────────────────────────────────────────────── */
.login-page {
  min-height: 100vh;
  background-color: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

/* ── Card ─────────────────────────────────────────────────────────────────── */
.login-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid #e5e7eb;
  box-shadow:
    0 4px 24px rgba(0, 0, 0, 0.07),
    0 1px 6px rgba(0, 0, 0, 0.04);
  width: 100%;
  max-width: 400px;
  padding: 36px 32px 28px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* ── Brand ────────────────────────────────────────────────────────────────── */
.login-brand {
  text-align: left;
}

.brand-title {
  margin: 0 0 4px;
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1;
}

.brand-leave {
  color: #111827;
}

.brand-hub {
  color: #4f46e5;
}

.brand-subtitle {
  margin: 0;
  font-size: 0.8rem;
  color: #9ca3af;
  font-weight: 400;
  letter-spacing: 0.01em;
}

/* ── Form ─────────────────────────────────────────────────────────────────── */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* ── Field group ──────────────────────────────────────────────────────────── */
.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  line-height: 1;
}

.field-input {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  font-size: 0.875rem;
  color: #111827;
  background: #ffffff;
  transition: border-color 0.15s, box-shadow 0.15s;
  box-sizing: border-box;
  outline: none;
  font-family: inherit;
}

.field-input::placeholder {
  color: #d1d5db;
}

.field-input:focus {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.field-input:disabled {
  background: #f9fafb;
  cursor: not-allowed;
  opacity: 0.75;
}

.field-input--error {
  border-color: #ef4444 !important;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.08) !important;
}

.field-error {
  margin: 0;
  font-size: 0.775rem;
  color: #ef4444;
  line-height: 1.3;
}

/* ── Password field ───────────────────────────────────────────────────────── */
.password-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input {
  padding-right: 40px;
}

.password-toggle {
  position: absolute;
  right: 11px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: none;
  background: transparent;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 4px;
  padding: 0;
  transition: color 0.15s;
}

.password-toggle:hover {
  color: #4f46e5;
}

/* ── Error banner ─────────────────────────────────────────────────────────── */
.error-banner {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 0.82rem;
  color: #b91c1c;
  line-height: 1.4;
}

.error-banner__icon {
  flex-shrink: 0;
  margin-top: 1px;
}

/* ── Submit button ────────────────────────────────────────────────────────── */
.submit-btn {
  width: 100%;
  padding: 11px 16px;
  background-color: #4f46e5;
  color: #ffffff;
  border: none;
  border-radius: 10px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s, box-shadow 0.15s, transform 0.1s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-family: inherit;
  margin-top: 4px;
}

.submit-btn:hover:not(:disabled) {
  background-color: #4338ca;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
}

.submit-btn:active:not(:disabled) {
  transform: scale(0.99);
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* Loading spinner inside button */
.submit-btn__spinner {
  display: inline-block;
  width: 15px;
  height: 15px;
  border: 2px solid rgba(255, 255, 255, 0.35);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.65s linear infinite;
  flex-shrink: 0;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* ── Footer note ──────────────────────────────────────────────────────────── */
.login-footnote {
  margin: 0;
  text-align: center;
  font-size: 0.75rem;
  color: #d1d5db;
  letter-spacing: 0.02em;
}
</style>
