<script setup lang="ts">
import { ref, computed, watch, onMounted } from "vue";
import AppLayout from "@/components/layout/AppLayout.vue";
import {
    getLeaveBalances,
    createLeaveRequest,
    calcDays,
    formatDate,
} from "@/services/user.service";
import { toast } from "@/plugins/toast";
import type { LeaveBalance } from "@/types";

// ─── Data ─────────────────────────────────────────────────────────────────────

const balances = ref<LeaveBalance[]>([]);
const loading = ref(false);
const submitting = ref(false);

// ─── Form state ───────────────────────────────────────────────────────────────

const form = ref({
    leave_type_id: 1,
    start_date: "",
    end_date: "",
    reason: "",
});

const fieldErrors = ref<Record<string, string>>({});

// ─── Leave type options ───────────────────────────────────────────────────────

interface LeaveTypeOption {
    id: number;
    name: string;
    remaining: number;
}

const leaveTypeOptions = computed<LeaveTypeOption[]>(() => {
    const annualBalance = balances.value.find((b) =>
        b.leave_type?.name?.toLowerCase().includes("annual"),
    );
    const sickBalance = balances.value.find((b) =>
        b.leave_type?.name?.toLowerCase().includes("sick"),
    );

    return [
        {
            id: 1,
            name: "Annual Leave",
            remaining: annualBalance?.remaining_quota ?? 0,
        },
        {
            id: 2,
            name: "Sick Leave",
            remaining: sickBalance?.remaining_quota ?? 0,
        },
    ];
});

const selectedLeaveBalance = computed<LeaveBalance | null>(() => {
    return (
        balances.value.find((b) => {
            if (form.value.leave_type_id === 1)
                return b.leave_type?.name?.toLowerCase().includes("annual");
            return b.leave_type?.name?.toLowerCase().includes("sick");
        }) ?? null
    );
});

// ─── Day calculation ──────────────────────────────────────────────────────────

const totalDays = computed<number>(() =>
    calcDays(form.value.start_date, form.value.end_date),
);

const remainingAfterApprove = computed<number>(() => {
    const current = selectedLeaveBalance.value?.remaining_quota ?? 0;
    return Math.max(0, current - totalDays.value);
});

const dateRangeLabel = computed<string>(() => {
    if (!form.value.start_date || !form.value.end_date) return "";
    if (totalDays.value <= 0) return "";
    const s = formatDate(form.value.start_date);
    const e = formatDate(form.value.end_date);
    if (form.value.start_date === form.value.end_date) return s;
    return `${s} – ${e}`;
});

// ─── Live validations ─────────────────────────────────────────────────────────

type CheckState = "ok" | "error" | "idle";

interface ValidationCheck {
    state: CheckState;
    label: string;
    detail: string;
}

const today = computed<string>(() => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
});

const checks = computed<ValidationCheck[]>(() => {
    const startOk =
        !!form.value.start_date &&
        !!form.value.end_date &&
        form.value.start_date >= today.value &&
        form.value.end_date >= form.value.start_date &&
        totalDays.value > 0;

    const quotaOk =
        selectedLeaveBalance.value !== null &&
        totalDays.value > 0 &&
        totalDays.value <= (selectedLeaveBalance.value?.remaining_quota ?? 0);

    const remaining = selectedLeaveBalance.value?.remaining_quota ?? 0;

    return [
        {
            state:
                !form.value.start_date || !form.value.end_date
                    ? "idle"
                    : startOk
                      ? "ok"
                      : "error",
            label: "Tanggal valid",
            detail: startOk
                ? "(start ≤ end, tidak di masa lalu)"
                : form.value.start_date < today.value
                  ? "(tanggal mulai sudah lewat)"
                  : "(start > end atau tanggal kosong)",
        },
        {
            state: totalDays.value === 0 ? "idle" : quotaOk ? "ok" : "error",
            label: "Kuota cukup",
            detail: quotaOk
                ? `(sisa ${remaining} hari ≥ ${totalDays.value} hari yang diajukan)`
                : `(sisa ${remaining} hari, mengajukan ${totalDays.value} hari)`,
        },
        {
            state: "idle",
            label: "Tidak ada overlap",
            detail: "dengan request pending/approved",
        },
    ];
});

// ─── Fetch ────────────────────────────────────────────────────────────────────

async function fetchBalances() {
    loading.value = true;
    try {
        balances.value = await getLeaveBalances();
    } catch (err) {
        toast.apiError(err, "Gagal memuat data kuota cuti.");
    } finally {
        loading.value = false;
    }
}

onMounted(fetchBalances);

// ─── Auto-correct end_date ────────────────────────────────────────────────────

watch(
    () => form.value.start_date,
    (newStart) => {
        if (form.value.end_date && form.value.end_date < newStart) {
            form.value.end_date = newStart;
        }
        // Clear date errors when user changes dates
        delete fieldErrors.value.start_date;
        delete fieldErrors.value.end_date;
    },
);

watch(
    () => form.value.end_date,
    () => {
        delete fieldErrors.value.start_date;
        delete fieldErrors.value.end_date;
    },
);

watch(
    () => form.value.leave_type_id,
    () => {
        delete fieldErrors.value.leave_type_id;
    },
);

// ─── Client-side validation ───────────────────────────────────────────────────

function validate(): boolean {
    fieldErrors.value = {};

    if (!form.value.start_date) {
        fieldErrors.value.start_date = "Tanggal mulai wajib diisi.";
    } else if (form.value.start_date < today.value) {
        fieldErrors.value.start_date =
            "Tanggal mulai tidak boleh di masa lalu.";
    }

    if (!form.value.end_date) {
        fieldErrors.value.end_date = "Tanggal selesai wajib diisi.";
    } else if (
        form.value.start_date &&
        form.value.end_date < form.value.start_date
    ) {
        fieldErrors.value.end_date =
            "Tanggal selesai tidak boleh sebelum tanggal mulai.";
    }

    if (!form.value.reason.trim()) {
        fieldErrors.value.reason = "Alasan cuti wajib diisi.";
    } else if (form.value.reason.trim().length < 5) {
        fieldErrors.value.reason = "Alasan minimal 5 karakter.";
    }

    const balance = selectedLeaveBalance.value;
    if (balance && totalDays.value > balance.remaining_quota) {
        fieldErrors.value.quota = `Kuota tidak cukup. Sisa ${balance.remaining_quota} hari, Anda mengajukan ${totalDays.value} hari.`;
    }

    return Object.keys(fieldErrors.value).length === 0;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

const submitSuccess = ref(false);

async function handleSubmit() {
    if (!validate()) return;

    submitting.value = true;
    fieldErrors.value = {};

    try {
        await createLeaveRequest({
            leave_type_id: form.value.leave_type_id,
            start_date: form.value.start_date,
            end_date: form.value.end_date,
            reason: form.value.reason.trim(),
        });

        toast.success(
            "Pengajuan Berhasil!",
            `Cuti ${totalDays.value} hari berhasil diajukan dan menunggu persetujuan admin.`,
        );

        submitSuccess.value = true;

        // Reset form
        form.value = {
            leave_type_id: 1,
            start_date: "",
            end_date: "",
            reason: "",
        };

        // Refresh balances
        await fetchBalances();

        setTimeout(() => {
            submitSuccess.value = false;
        }, 5000);
    } catch (err: unknown) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const axiosErr = err as any;

        if (axiosErr?.response?.status === 422) {
            const data = axiosErr.response.data;
            const errors = data?.errors as Record<string, string[]> | undefined;

            if (errors) {
                // Map backend errors to fieldErrors
                Object.entries(errors).forEach(([key, msgs]) => {
                    fieldErrors.value[key] = msgs[0];
                });

                // Surface overlap error specifically
                const allMessages = Object.values(errors).flat().join(" ");
                if (
                    allMessages.toLowerCase().includes("overlap") ||
                    allMessages.toLowerCase().includes("bentrok")
                ) {
                    fieldErrors.value.overlap =
                        data.message ??
                        "Tanggal bentrok dengan request yang sudah ada.";
                }

                // Surface quota error
                if (
                    allMessages.toLowerCase().includes("quota") ||
                    allMessages.toLowerCase().includes("kuota") ||
                    allMessages.toLowerCase().includes("balance")
                ) {
                    fieldErrors.value.quota =
                        data.message ?? "Kuota cuti tidak mencukupi.";
                }
            } else if (data?.message) {
                // General 422 message (e.g. overlap detected surfaced at root)
                const msg: string = data.message.toLowerCase();
                if (msg.includes("overlap") || msg.includes("bentrok")) {
                    fieldErrors.value.overlap = data.message;
                } else if (
                    msg.includes("quota") ||
                    msg.includes("kuota") ||
                    msg.includes("balance")
                ) {
                    fieldErrors.value.quota = data.message;
                } else {
                    fieldErrors.value.general = data.message;
                }
            }

            toast.error(
                "Validasi Gagal",
                data?.message ?? "Periksa kembali form pengajuan.",
            );
        } else {
            toast.apiError(err, "Gagal mengajukan cuti.");
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="page">
            <!-- ── Page header ──────────────────────────────────────────────── -->
            <div class="page-header">
                <h1 class="page-title">Ajukan Cuti</h1>
                <p class="page-subtitle">
                    Isi form untuk mengajukan permohonan cuti baru.
                </p>
            </div>

            <!-- ── Success banner ──────────────────────────────────────────── -->
            <Transition name="fade">
                <div v-if="submitSuccess" class="success-banner" role="status">
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
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <span>
                        Pengajuan cuti berhasil dikirim! Menunggu persetujuan
                        admin.
                    </span>
                </div>
            </Transition>

            <!-- ── Main form card ──────────────────────────────────────────── -->
            <div class="form-card">
                <h2 class="form-card__title">Form Pengajuan Cuti</h2>

                <form
                    class="form-body"
                    novalidate
                    @submit.prevent="handleSubmit"
                >
                    <!-- Leave type selector -->
                    <div class="field-group">
                        <label class="field-label" for="leave-type">
                            Jenis Cuti
                        </label>
                        <div class="select-wrapper">
                            <select
                                id="leave-type"
                                v-model="form.leave_type_id"
                                class="field-select"
                                :class="{
                                    'field-input--error':
                                        fieldErrors.leave_type_id,
                                }"
                                :disabled="loading || submitting"
                            >
                                <option
                                    v-for="opt in leaveTypeOptions"
                                    :key="opt.id"
                                    :value="opt.id"
                                >
                                    {{ opt.name }} (sisa: {{ opt.remaining }}
                                    hari)
                                </option>
                            </select>
                            <svg
                                class="select-chevron"
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
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </div>
                        <p v-if="fieldErrors.leave_type_id" class="field-error">
                            {{ fieldErrors.leave_type_id }}
                        </p>
                    </div>

                    <!-- Date row -->
                    <div class="date-row">
                        <!-- Start date -->
                        <div class="field-group">
                            <label class="field-label" for="start-date">
                                Tanggal Mulai
                            </label>
                            <input
                                id="start-date"
                                v-model="form.start_date"
                                type="date"
                                class="field-input"
                                :class="{
                                    'field-input--error':
                                        fieldErrors.start_date,
                                }"
                                :min="today"
                                :disabled="submitting"
                            />
                            <p
                                v-if="fieldErrors.start_date"
                                class="field-error"
                            >
                                {{ fieldErrors.start_date }}
                            </p>
                        </div>

                        <!-- End date -->
                        <div class="field-group">
                            <label class="field-label" for="end-date">
                                Tanggal Selesai
                            </label>
                            <input
                                id="end-date"
                                v-model="form.end_date"
                                type="date"
                                class="field-input"
                                :class="{
                                    'field-input--error': fieldErrors.end_date,
                                }"
                                :min="form.start_date || today"
                                :disabled="submitting"
                            />
                            <p v-if="fieldErrors.end_date" class="field-error">
                                {{ fieldErrors.end_date }}
                            </p>
                        </div>
                    </div>

                    <!-- Day summary (shows when both dates are filled) -->
                    <Transition name="fade">
                        <div
                            v-if="totalDays > 0"
                            class="day-summary"
                            :class="{
                                'day-summary--warning':
                                    totalDays >
                                    (selectedLeaveBalance?.remaining_quota ??
                                        0),
                            }"
                        >
                            <p class="day-summary__total">
                                📅 Total:
                                <strong>{{ totalDays }} hari</strong>
                                <span
                                    v-if="dateRangeLabel"
                                    class="day-summary__range"
                                >
                                    ({{ dateRangeLabel }})
                                </span>
                            </p>
                            <p class="day-summary__balance">
                                Sisa balance setelah approved:
                                <strong>
                                    {{
                                        selectedLeaveBalance?.remaining_quota ??
                                        0
                                    }}
                                    →
                                    {{ remainingAfterApprove }} hari
                                </strong>
                            </p>
                        </div>
                    </Transition>

                    <!-- Reason textarea -->
                    <div class="field-group">
                        <label class="field-label" for="reason">Alasan</label>
                        <textarea
                            id="reason"
                            v-model="form.reason"
                            class="field-textarea"
                            :class="{
                                'field-input--error': fieldErrors.reason,
                            }"
                            rows="4"
                            placeholder="Liburan keluarga ke Malang"
                            :disabled="submitting"
                            @input="delete fieldErrors.reason"
                        />
                        <p v-if="fieldErrors.reason" class="field-error">
                            {{ fieldErrors.reason }}
                        </p>
                    </div>

                    <!-- Backend validation errors -->
                    <div
                        v-if="
                            fieldErrors.overlap ||
                            fieldErrors.quota ||
                            fieldErrors.general
                        "
                        class="backend-errors"
                        role="alert"
                    >
                        <div class="backend-errors__header">
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
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            <span>Pengajuan ditolak oleh server</span>
                        </div>
                        <ul class="backend-errors__list">
                            <li v-if="fieldErrors.overlap">
                                ❌ <strong>Overlap terdeteksi</strong> —
                                {{ fieldErrors.overlap }}
                            </li>
                            <li v-if="fieldErrors.quota">
                                ❌ <strong>Kuota tidak cukup</strong> —
                                {{ fieldErrors.quota }}
                            </li>
                            <li v-if="fieldErrors.general">
                                ❌ {{ fieldErrors.general }}
                            </li>
                        </ul>
                    </div>

                    <!-- Submit button -->
                    <button
                        type="submit"
                        class="submit-btn"
                        :disabled="submitting || loading"
                    >
                        <span
                            v-if="submitting"
                            class="btn-spinner"
                            aria-hidden="true"
                        />
                        {{ submitting ? "Mengajukan..." : "Submit Pengajuan" }}
                    </button>
                </form>
            </div>

            <!-- ── Live validation checklist ───────────────────────────────── -->
            <div class="validation-card">
                <h3 class="validation-card__title">Validasi yang Dicek</h3>
                <ul class="check-list">
                    <li
                        v-for="(check, i) in checks"
                        :key="i"
                        class="check-item"
                        :class="{
                            'check-item--ok': check.state === 'ok',
                            'check-item--error': check.state === 'error',
                            'check-item--idle': check.state === 'idle',
                        }"
                    >
                        <span class="check-icon" aria-hidden="true">
                            <template v-if="check.state === 'ok'">✅</template>
                            <template v-else-if="check.state === 'error'"
                                >❌</template
                            >
                            <template v-else>✅</template>
                        </span>
                        <span class="check-text">
                            <strong>{{ check.label }}</strong>
                            <span class="check-detail">{{ check.detail }}</span>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- ── Example: Validation failure (shown when errors exist) ─────── -->
            <Transition name="fade">
                <div
                    v-if="fieldErrors.overlap || fieldErrors.quota"
                    class="example-card example-card--error"
                >
                    <h3 class="example-card__title example-card__title--error">
                        Contoh: Validasi Gagal
                    </h3>
                    <ul class="check-list">
                        <li class="check-item check-item--ok">
                            <span class="check-icon">✅</span>
                            <span class="check-text">
                                <strong class="check-text--ok"
                                    >Tanggal valid</strong
                                >
                            </span>
                        </li>
                        <li
                            v-if="fieldErrors.quota"
                            class="check-item check-item--error"
                        >
                            <span class="check-icon">❌</span>
                            <span class="check-text">
                                <strong class="check-text--error"
                                    >Kuota tidak cukup</strong
                                >
                                <span class="check-detail"
                                    >— {{ fieldErrors.quota }}</span
                                >
                            </span>
                        </li>
                        <li
                            v-if="fieldErrors.overlap"
                            class="check-item check-item--error"
                        >
                            <span class="check-icon">❌</span>
                            <span class="check-text">
                                <strong class="check-text--error"
                                    >Overlap terdeteksi</strong
                                >
                                <span class="check-detail"
                                    >— {{ fieldErrors.overlap }}</span
                                >
                            </span>
                        </li>
                    </ul>
                </div>
            </Transition>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ── Page ─────────────────────────────────────────────────────────────────── */
.page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    max-width: 720px;
}

.page-header {
    padding-top: 4px;
}

.page-title {
    margin: 0 0 4px;
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
}

.page-subtitle {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
}

/* ── Success banner ──────────────────────────────────────────────────────── */
.success-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background-color: #f0fdf4;
    border: 1px solid #86efac;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #166534;
    font-weight: 500;
}

/* ── Form card ───────────────────────────────────────────────────────────── */
.form-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

.form-body {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

/* ── Field group ─────────────────────────────────────────────────────────── */
.field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.field-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

/* ── Select ──────────────────────────────────────────────────────────────── */
.select-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.field-select {
    width: 100%;
    padding: 10px 36px 10px 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #111827;
    background: #ffffff;
    appearance: none;
    -webkit-appearance: none;
    outline: none;
    font-family: inherit;
    cursor: pointer;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
    box-sizing: border-box;
}

.field-select:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.field-select:disabled {
    background-color: #f9fafb;
    cursor: not-allowed;
    opacity: 0.7;
}

.select-chevron {
    position: absolute;
    right: 12px;
    color: #9ca3af;
    pointer-events: none;
    flex-shrink: 0;
}

/* ── Date row ────────────────────────────────────────────────────────────── */
.date-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* ── Text inputs & date inputs ───────────────────────────────────────────── */
.field-input {
    padding: 10px 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #111827;
    background: #ffffff;
    outline: none;
    font-family: inherit;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
    box-sizing: border-box;
    width: 100%;
}

.field-input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.field-input:disabled {
    background-color: #f9fafb;
    cursor: not-allowed;
    opacity: 0.7;
}

.field-input--error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.08) !important;
}

/* ── Textarea ────────────────────────────────────────────────────────────── */
.field-textarea {
    padding: 10px 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.875rem;
    color: #111827;
    background: #ffffff;
    outline: none;
    font-family: inherit;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
    resize: vertical;
    min-height: 90px;
    box-sizing: border-box;
    width: 100%;
}

.field-textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.field-textarea:disabled {
    background-color: #f9fafb;
    cursor: not-allowed;
    opacity: 0.7;
}

.field-error {
    margin: 0;
    font-size: 0.775rem;
    color: #ef4444;
    line-height: 1.3;
}

/* ── Day summary box ─────────────────────────────────────────────────────── */
.day-summary {
    padding: 12px 14px;
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.day-summary--warning {
    background-color: #fef3c7;
    border-color: #fcd34d;
}

.day-summary--warning .day-summary__total,
.day-summary--warning .day-summary__balance {
    color: #92400e;
}

.day-summary__total {
    margin: 0;
    font-size: 0.875rem;
    color: #1d4ed8;
    line-height: 1.5;
}

.day-summary__total strong {
    font-weight: 700;
}

.day-summary__range {
    color: #3b82f6;
    font-size: 0.82rem;
    margin-left: 4px;
}

.day-summary__balance {
    margin: 0;
    font-size: 0.82rem;
    color: #2563eb;
    line-height: 1.5;
}

.day-summary__balance strong {
    font-weight: 700;
}

/* ── Backend errors box ──────────────────────────────────────────────────── */
.backend-errors {
    padding: 12px 14px;
    background-color: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.backend-errors__header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    font-weight: 700;
    color: #991b1b;
}

.backend-errors__list {
    margin: 0;
    padding: 0 0 0 4px;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.backend-errors__list li {
    font-size: 0.82rem;
    color: #b91c1c;
    line-height: 1.5;
}

/* ── Submit button ───────────────────────────────────────────────────────── */
.submit-btn {
    padding: 11px 20px;
    background-color: #4f46e5;
    color: #ffffff;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition:
        background-color 0.15s,
        box-shadow 0.15s;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    align-self: flex-start;
}

.submit-btn:hover:not(:disabled) {
    background-color: #4338ca;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255, 255, 255, 0.35);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.65s linear infinite;
    flex-shrink: 0;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ── Validation checklist card ───────────────────────────────────────────── */
.validation-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.validation-card__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #111827;
}

/* ── Check list ──────────────────────────────────────────────────────────── */
.check-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.check-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.875rem;
    line-height: 1.5;
}

.check-icon {
    font-size: 0.9rem;
    flex-shrink: 0;
    line-height: 1.5;
}

.check-item--idle .check-icon {
    filter: grayscale(100%) opacity(0.4);
}

.check-text {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 4px;
    color: #374151;
}

.check-text--ok {
    color: #065f46;
}

.check-text--error {
    color: #991b1b;
}

.check-item--ok .check-text strong {
    color: #065f46;
}

.check-item--error .check-text strong {
    color: #991b1b;
}

.check-item--idle .check-text strong {
    color: #6b7280;
}

.check-detail {
    font-size: 0.8rem;
    color: #6b7280;
    font-weight: 400;
}

.check-item--ok .check-detail {
    color: #059669;
}

.check-item--error .check-detail {
    color: #dc2626;
}

/* ── Example validation-fail card ────────────────────────────────────────── */
.example-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.example-card--error {
    border-color: #fecaca;
    background-color: #fffbfb;
}

.example-card__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
}

.example-card__title--error {
    color: #b91c1c;
}

/* ── Transition ──────────────────────────────────────────────────────────── */
.fade-enter-active,
.fade-leave-active {
    transition:
        opacity 0.25s ease,
        transform 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
