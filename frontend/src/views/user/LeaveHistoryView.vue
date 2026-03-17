<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import AppLayout from "@/components/layout/AppLayout.vue";
import StatusBadge from "@/components/ui/StatusBadge.vue";
import {
    getLeaveRequests,
    cancelLeaveRequest,
    deleteLeaveRequest,
    formatDateRange,
} from "@/services/user.service";
import { toast } from "@/plugins/toast";
import type { LeaveRequest } from "@/types";

// ─── Data ─────────────────────────────────────────────────────────────────────

const requests = ref<LeaveRequest[]>([]);
const loading = ref(false);

// ─── Stats ────────────────────────────────────────────────────────────────────

const totalCount = computed(() => requests.value.length);

// ─── Inline cancel confirmation panel ────────────────────────────────────────

const cancelTargetId = ref<number | null>(null);
const canceling = ref(false);

const cancelTarget = computed<LeaveRequest | null>(() => {
    if (cancelTargetId.value === null) return null;
    return requests.value.find((r) => r.id === cancelTargetId.value) ?? null;
});

function openCancelPanel(request: LeaveRequest) {
    // Toggle: clicking same row again closes the panel
    if (cancelTargetId.value === request.id) {
        cancelTargetId.value = null;
        return;
    }
    cancelTargetId.value = request.id;
}

function closeCancelPanel() {
    cancelTargetId.value = null;
}

// ─── Deleting state ───────────────────────────────────────────────────────────

const deletingId = ref<number | null>(null);

// ─── Fetch ────────────────────────────────────────────────────────────────────

async function fetchRequests() {
    loading.value = true;
    try {
        requests.value = await getLeaveRequests();
    } catch (err) {
        toast.apiError(err, "Gagal memuat riwayat cuti.");
    } finally {
        loading.value = false;
    }
}

onMounted(fetchRequests);

// ─── Cancel action ────────────────────────────────────────────────────────────

async function confirmCancel() {
    if (!cancelTarget.value || canceling.value) return;
    const req = cancelTarget.value;

    canceling.value = true;
    try {
        const updated = await cancelLeaveRequest(req.id);
        // Replace in array reactively
        const idx = requests.value.findIndex((r) => r.id === req.id);
        if (idx !== -1) requests.value[idx] = updated;
        toast.success(
            "Cuti Dibatalkan",
            `${req.leave_type?.name ?? "Cuti"} berhasil dibatalkan.`,
        );
        closeCancelPanel();
    } catch (err) {
        toast.apiError(err, "Gagal membatalkan cuti.");
    } finally {
        canceling.value = false;
    }
}

// ─── Delete action ────────────────────────────────────────────────────────────

async function handleDelete(request: LeaveRequest) {
    if (deletingId.value !== null) return;

    deletingId.value = request.id;
    try {
        await deleteLeaveRequest(request.id);
        requests.value = requests.value.filter((r) => r.id !== request.id);
        toast.success(
            "History Dihapus",
            `${request.leave_type?.name ?? "Cuti"} berhasil dihapus dari riwayat.`,
        );
    } catch (err) {
        toast.apiError(err, "Gagal menghapus history cuti.");
    } finally {
        deletingId.value = null;
    }
}

// ─── Formatting helpers ────────────────────────────────────────────────────────

function fmtRange(start: string, end: string): string {
    return formatDateRange(start, end);
}
</script>

<template>
    <AppLayout>
        <div class="page">
            <!-- ── Page header ──────────────────────────────────────────────── -->
            <div class="page-header">
                <h1 class="page-title">Riwayat Cuti Saya</h1>
                <p class="page-subtitle">
                    Semua pengajuan cuti yang pernah disubmit.
                </p>
            </div>

            <!-- ── Table card ──────────────────────────────────────────────── -->
            <div class="table-card">
                <div class="table-card__header">
                    <h2 class="table-card__title">Riwayat Pengajuan</h2>
                    <span v-if="!loading" class="table-count">
                        {{ totalCount }} request
                    </span>
                </div>

                <!-- Loading skeleton -->
                <div v-if="loading" class="skeleton-area">
                    <div v-for="n in 4" :key="n" class="skeleton-row">
                        <div class="skeleton" style="width: 120px" />
                        <div class="skeleton" style="width: 130px" />
                        <div class="skeleton" style="width: 40px" />
                        <div class="skeleton" style="width: 160px" />
                        <div class="skeleton" style="width: 80px" />
                        <div class="skeleton" style="width: 120px" />
                        <div class="skeleton" style="width: 80px" />
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-else-if="requests.length === 0"
                    class="empty-state"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="42"
                        height="42"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="#d1d5db"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path
                            d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"
                        />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                    <p class="empty-state__text">
                        Belum ada riwayat pengajuan cuti.
                    </p>
                </div>

                <!-- Data table -->
                <template v-else>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>TIPE</th>
                                <th>TANGGAL</th>
                                <th>HARI</th>
                                <th>ALASAN</th>
                                <th>STATUS</th>
                                <th>CATATAN ADMIN</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="req in requests"
                                :key="req.id"
                                :class="{
                                    'row--cancel-active':
                                        cancelTargetId === req.id,
                                }"
                            >
                                <!-- Type -->
                                <td class="td-type">
                                    {{ req.leave_type?.name ?? "—" }}
                                </td>

                                <!-- Date range -->
                                <td class="td-date">
                                    {{
                                        fmtRange(req.start_date, req.end_date)
                                    }}
                                </td>

                                <!-- Days -->
                                <td class="td-days">{{ req.total_days }}</td>

                                <!-- Reason -->
                                <td class="td-reason">{{ req.reason }}</td>

                                <!-- Status -->
                                <td>
                                    <StatusBadge :status="req.status" />
                                </td>

                                <!-- Admin notes -->
                                <td class="td-notes">
                                    {{ req.admin_notes ?? "—" }}
                                </td>

                                <!-- Actions -->
                                <td class="td-actions">
                                    <!-- Cancel button (only for pending) -->
                                    <button
                                        v-if="req.status === 'pending'"
                                        type="button"
                                        class="btn-cancel"
                                        :class="{
                                            'btn-cancel--active':
                                                cancelTargetId === req.id,
                                        }"
                                        @click="openCancelPanel(req)"
                                    >
                                        Cancel
                                    </button>

                                    <!-- Delete button (only for canceled or rejected) -->
                                    <button
                                        v-else-if="
                                            req.status === 'canceled' ||
                                            req.status === 'rejected'
                                        "
                                        type="button"
                                        class="btn-delete"
                                        :disabled="deletingId === req.id"
                                        @click="handleDelete(req)"
                                    >
                                        <span
                                            v-if="deletingId === req.id"
                                            class="btn-spinner btn-spinner--red"
                                        />
                                        {{
                                            deletingId === req.id
                                                ? "..."
                                                : "Hapus"
                                        }}
                                    </button>

                                    <!-- No action available -->
                                    <span v-else class="td-dash">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- ── Inline Cancel Confirmation Panel ─────────────────── -->
                    <Transition name="slide-down">
                        <div
                            v-if="cancelTarget !== null"
                            class="cancel-panel"
                            role="dialog"
                            aria-label="Konfirmasi pembatalan cuti"
                        >
                            <!-- Panel title -->
                            <h3 class="cancel-panel__title">Cancel Request?</h3>

                            <!-- Request summary -->
                            <p class="cancel-panel__summary">
                                <strong>{{
                                    cancelTarget.leave_type?.name ?? "Cuti"
                                }}</strong>
                                ·
                                {{
                                    fmtRange(
                                        cancelTarget.start_date,
                                        cancelTarget.end_date,
                                    )
                                }}
                                ({{ cancelTarget.total_days }} hari)
                            </p>

                            <!-- Current status row -->
                            <p class="cancel-panel__status-row">
                                Status saat ini:&nbsp;
                                <StatusBadge :status="cancelTarget.status" />
                            </p>

                            <!-- Warning -->
                            <p class="cancel-panel__warning">
                                Request yang sudah di-cancel tidak bisa
                                dikembalikan.
                            </p>

                            <!-- Action buttons -->
                            <div class="cancel-panel__actions">
                                <button
                                    type="button"
                                    class="btn-confirm-cancel"
                                    :disabled="canceling"
                                    @click="confirmCancel"
                                >
                                    <span
                                        v-if="canceling"
                                        class="btn-spinner btn-spinner--white"
                                    />
                                    {{
                                        canceling
                                            ? "Memproses..."
                                            : "Ya, Cancel"
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="btn-back"
                                    :disabled="canceling"
                                    @click="closeCancelPanel"
                                >
                                    Batal
                                </button>
                            </div>
                        </div>
                    </Transition>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ── Page ─────────────────────────────────────────────────────────────────── */
.page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    max-width: 1060px;
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

/* ── Table card ──────────────────────────────────────────────────────────── */
.table-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
}

.table-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
}

.table-card__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #111827;
}

.table-count {
    font-size: 0.8rem;
    color: #9ca3af;
    font-weight: 500;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    padding: 10px 16px;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #9ca3af;
    border-bottom: 1px solid #f3f4f6;
    white-space: nowrap;
}

.data-table td {
    padding: 14px 16px;
    font-size: 0.875rem;
    color: #374151;
    border-bottom: 1px solid #f9fafb;
    vertical-align: middle;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.data-table tbody tr:hover td {
    background-color: #fafafa;
}

/* Highlight active-cancel row */
.row--cancel-active td {
    background-color: #fffbf0 !important;
}

/* ── Cell types ──────────────────────────────────────────────────────────── */
.td-type {
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
}

.td-date {
    white-space: nowrap;
    color: #374151;
}

.td-days {
    text-align: center;
    font-weight: 500;
}

.td-reason {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #6b7280;
}

.td-notes {
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #6b7280;
    font-size: 0.82rem;
}

.td-actions {
    white-space: nowrap;
}

.td-dash {
    color: #d1d5db;
}

/* ── Action buttons ──────────────────────────────────────────────────────── */
.btn-cancel {
    padding: 5px 14px;
    background-color: #ef4444;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s;
    white-space: nowrap;
    font-family: inherit;
}

.btn-cancel:hover {
    background-color: #dc2626;
}

.btn-cancel--active {
    background-color: #b91c1c;
}

.btn-delete {
    padding: 5px 12px;
    background-color: #fef2f2;
    color: #ef4444;
    border: 1px solid #fecaca;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-delete:hover:not(:disabled) {
    background-color: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
}

.btn-delete:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── Cancel confirmation panel ───────────────────────────────────────────── */
.cancel-panel {
    margin: 0 20px 20px;
    padding: 20px 24px;
    background: #ffffff;
    border: 1.5px solid #f59e0b;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 480px;
    box-shadow: 0 2px 10px rgba(245, 158, 11, 0.08);
}

.cancel-panel__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #b45309;
}

.cancel-panel__summary {
    margin: 0;
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.5;
}

.cancel-panel__status-row {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.cancel-panel__warning {
    margin: 0;
    font-size: 0.82rem;
    color: #d97706;
    font-style: italic;
    font-weight: 500;
}

.cancel-panel__actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ── Confirm cancel button ───────────────────────────────────────────────── */
.btn-confirm-cancel {
    padding: 8px 16px;
    background-color: #ef4444;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-confirm-cancel:hover:not(:disabled) {
    background-color: #dc2626;
}

.btn-confirm-cancel:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── Back / Batal button ─────────────────────────────────────────────────── */
.btn-back {
    padding: 8px 16px;
    background-color: #f9fafb;
    color: #374151;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.15s;
    font-family: inherit;
}

.btn-back:hover:not(:disabled) {
    background-color: #f3f4f6;
}

.btn-back:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── Spinners ────────────────────────────────────────────────────────────── */
.btn-spinner {
    display: inline-block;
    width: 13px;
    height: 13px;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    flex-shrink: 0;
}

.btn-spinner--white {
    border: 2px solid rgba(255, 255, 255, 0.35);
    border-top-color: #ffffff;
}

.btn-spinner--red {
    border: 2px solid rgba(239, 68, 68, 0.3);
    border-top-color: #ef4444;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ── Loading skeleton ────────────────────────────────────────────────────── */
.skeleton-area {
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.skeleton-row {
    display: flex;
    gap: 20px;
    align-items: center;
}

.skeleton {
    height: 14px;
    border-radius: 6px;
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    flex-shrink: 0;
}

@keyframes shimmer {
    from {
        background-position: 200% 0;
    }
    to {
        background-position: -200% 0;
    }
}

/* ── Empty state ─────────────────────────────────────────────────────────── */
.empty-state {
    padding: 48px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    text-align: center;
}

.empty-state__text {
    margin: 0;
    font-size: 0.9rem;
    color: #9ca3af;
}

/* ── Slide-down transition for cancel panel ──────────────────────────────── */
.slide-down-enter-active {
    animation: slideDownIn 0.25s cubic-bezier(0.21, 1.02, 0.73, 1) forwards;
}

.slide-down-leave-active {
    animation: slideDownOut 0.2s ease-in forwards;
}

@keyframes slideDownIn {
    from {
        opacity: 0;
        transform: translateY(-12px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slideDownOut {
    from {
        opacity: 1;
        transform: translateY(0) scale(1);
        max-height: 300px;
    }
    to {
        opacity: 0;
        transform: translateY(-8px) scale(0.98);
        max-height: 0;
    }
}
</style>
