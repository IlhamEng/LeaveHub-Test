<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import AppLayout from "@/components/layout/AppLayout.vue";
import {
    getAdminLeaveRequests,
    approveLeaveRequest,
    rejectLeaveRequest,
    deleteAdminLeaveRequest,
} from "@/services/admin.service";
import { formatDateRange } from "@/services/user.service";
import { toast } from "@/plugins/toast";
import StatusBadge from "@/components/ui/StatusBadge.vue";
import type { LeaveRequest } from "@/types";

// ─── Data ─────────────────────────────────────────────────────────────────────

const requests = ref<LeaveRequest[]>([]);
const loading = ref(false);

// ─── Derived lists ────────────────────────────────────────────────────────────

const pendingRequests = computed(() =>
    requests.value.filter((r) => r.status === "pending"),
);

const historyRequests = computed(() =>
    requests.value.filter((r) => r.status !== "pending"),
);

// ─── Stats ────────────────────────────────────────────────────────────────────

const pendingCount = computed(() => pendingRequests.value.length);
const approvedCount = computed(
    () => requests.value.filter((r) => r.status === "approved").length,
);
const rejectedCount = computed(
    () => requests.value.filter((r) => r.status === "rejected").length,
);

// ─── Confirmation panel state ─────────────────────────────────────────────────

type ActionType = "approve" | "reject";

interface ActiveAction {
    requestId: number;
    type: ActionType;
    adminNotes: string;
}

const activeAction = ref<ActiveAction | null>(null);
const submitting = ref(false);

function openActionPanel(request: LeaveRequest, type: ActionType) {
    // Toggle: if already open for this request + type, close it
    if (
        activeAction.value?.requestId === request.id &&
        activeAction.value?.type === type
    ) {
        activeAction.value = null;
        return;
    }
    activeAction.value = { requestId: request.id, type, adminNotes: "" };
}

function closeActionPanel() {
    activeAction.value = null;
}

function isActionOpen(requestId: number, type: ActionType): boolean {
    return (
        activeAction.value?.requestId === requestId &&
        activeAction.value?.type === type
    );
}

// ─── Fetch ────────────────────────────────────────────────────────────────────

async function fetchRequests() {
    loading.value = true;
    try {
        requests.value = await getAdminLeaveRequests();
    } catch (err) {
        toast.apiError(err, "Gagal memuat data leave request.");
    } finally {
        loading.value = false;
    }
}

onMounted(fetchRequests);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getRequest(id: number): LeaveRequest | undefined {
    return requests.value.find((r) => r.id === id);
}

function getBalanceAfterApprove(request: LeaveRequest): string {
    // We don't have full balance info here, so we show days to deduct
    return `${request.total_days} hari akan dipotong dari kuota`;
}

// ─── Actions ──────────────────────────────────────────────────────────────────

async function confirmApprove() {
    if (!activeAction.value || submitting.value) return;
    const { requestId, adminNotes } = activeAction.value;
    const request = getRequest(requestId);
    if (!request) return;

    submitting.value = true;
    try {
        const updated = await approveLeaveRequest(requestId, adminNotes);
        // Replace in array
        const idx = requests.value.findIndex((r) => r.id === requestId);
        if (idx !== -1) requests.value[idx] = updated;
        toast.success(
            "Request Disetujui",
            `Cuti ${request.user?.name ?? ""} (${request.leave_type?.name ?? ""}) berhasil di-approve.`,
        );
        closeActionPanel();
    } catch (err) {
        toast.apiError(err, "Gagal menyetujui cuti.");
    } finally {
        submitting.value = false;
    }
}

async function confirmReject() {
    if (!activeAction.value || submitting.value) return;
    const { requestId, adminNotes } = activeAction.value;
    const request = getRequest(requestId);
    if (!request) return;

    submitting.value = true;
    try {
        const updated = await rejectLeaveRequest(requestId, adminNotes);
        const idx = requests.value.findIndex((r) => r.id === requestId);
        if (idx !== -1) requests.value[idx] = updated;
        toast.success(
            "Request Ditolak",
            `Cuti ${request.user?.name ?? ""} berhasil di-reject.`,
        );
        closeActionPanel();
    } catch (err) {
        toast.apiError(err, "Gagal menolak cuti.");
    } finally {
        submitting.value = false;
    }
}

// ─── Delete ───────────────────────────────────────────────────────────────────

const deletingId = ref<number | null>(null);

async function handleDelete(request: LeaveRequest) {
    if (deletingId.value !== null) return;
    const confirmed = window.confirm(
        `Hapus history cuti "${request.leave_type?.name ?? ""}" milik ${request.user?.name ?? ""}?\nAksi ini tidak dapat dibatalkan.`,
    );
    if (!confirmed) return;

    deletingId.value = request.id;
    try {
        await deleteAdminLeaveRequest(request.id);
        requests.value = requests.value.filter((r) => r.id !== request.id);
        toast.success("Dihapus", "History cuti berhasil dihapus.");
    } catch (err) {
        toast.apiError(err, "Gagal menghapus history cuti.");
    } finally {
        deletingId.value = null;
    }
}

// ─── Date formatting ──────────────────────────────────────────────────────────

function fmtRange(start: string, end: string): string {
    return formatDateRange(start, end);
}

function fmtDate(dateStr: string | null): string {
    if (!dateStr) return "—";
    const d = new Date(dateStr + (dateStr.includes("T") ? "" : "T00:00:00"));
    return d.toLocaleDateString("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    });
}
</script>

<template>
    <AppLayout>
        <div class="page">
            <!-- ── Page header ─────────────────────────────────────────────────────── -->
            <div class="page-header">
                <h1 class="page-title">Semua Leave Request</h1>
                <p class="page-subtitle">
                    Kelola dan respond permohonan cuti dari semua user.
                </p>
            </div>

            <!-- ── Stats cards ────────────────────────────────────────────────────── -->
            <div class="stats-grid">
                <!-- Pending -->
                <div class="stat-card">
                    <div class="stat-icon stat-icon--orange">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <div class="stat-body">
                        <p class="stat-label">Pending</p>
                        <p class="stat-value stat-value--orange">
                            {{ pendingCount }}
                        </p>
                        <p class="stat-sub">Menunggu keputusan</p>
                    </div>
                </div>

                <!-- Approved -->
                <div class="stat-card">
                    <div class="stat-icon stat-icon--green">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                    <div class="stat-body">
                        <p class="stat-label">Approved</p>
                        <p class="stat-value stat-value--green">
                            {{ approvedCount }}
                        </p>
                        <p class="stat-sub">Disetujui</p>
                    </div>
                </div>

                <!-- Rejected -->
                <div class="stat-card">
                    <div class="stat-icon stat-icon--red">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </div>
                    <div class="stat-body">
                        <p class="stat-label">Rejected</p>
                        <p class="stat-value stat-value--red">
                            {{ rejectedCount }}
                        </p>
                        <p class="stat-sub">Ditolak</p>
                    </div>
                </div>
            </div>

            <!-- ── Loading skeleton ───────────────────────────────────────────────── -->
            <div v-if="loading" class="loading-area">
                <div v-for="n in 4" :key="n" class="skeleton-row">
                    <div class="skeleton" style="width: 120px" />
                    <div class="skeleton" style="width: 100px" />
                    <div class="skeleton" style="width: 130px" />
                    <div class="skeleton" style="width: 50px" />
                    <div class="skeleton" style="width: 80px" />
                    <div class="skeleton" style="width: 60px" />
                    <div class="skeleton" style="width: 160px" />
                </div>
            </div>

            <template v-else>
                <!-- ── Perlu Tindakan (Pending) ────────────────────────────────────── -->
                <div v-if="pendingRequests.length > 0" class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">Perlu Tindakan</h2>
                        <span class="pending-badge"
                            >⏳ {{ pendingCount }} pending</span
                        >
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>USER</th>
                                <th>TIPE</th>
                                <th>TANGGAL</th>
                                <th>HARI</th>
                                <th>ALASAN</th>
                                <th>STATUS</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="req in pendingRequests" :key="req.id">
                                <td class="td-name">
                                    {{ req.user?.name ?? "—" }}
                                </td>
                                <td>{{ req.leave_type?.name ?? "—" }}</td>
                                <td class="td-date">
                                    {{ fmtRange(req.start_date, req.end_date) }}
                                </td>
                                <td class="td-center">{{ req.total_days }}</td>
                                <td class="td-reason">{{ req.reason }}</td>
                                <td>
                                    <StatusBadge :status="req.status" />
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button
                                            type="button"
                                            class="btn-approve"
                                            :class="{
                                                'btn-approve--active':
                                                    isActionOpen(
                                                        req.id,
                                                        'approve',
                                                    ),
                                            }"
                                            @click="
                                                openActionPanel(req, 'approve')
                                            "
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            class="btn-reject"
                                            :class="{
                                                'btn-reject--active':
                                                    isActionOpen(
                                                        req.id,
                                                        'reject',
                                                    ),
                                            }"
                                            @click="
                                                openActionPanel(req, 'reject')
                                            "
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- ── Inline confirmation panels ─────────────────────────────────── -->
                    <template v-if="activeAction">
                        <div
                            v-for="req in pendingRequests"
                            :key="`panel-${req.id}`"
                        >
                            <!-- Approve Panel -->
                            <div
                                v-if="isActionOpen(req.id, 'approve')"
                                class="confirm-panel confirm-panel--approve"
                            >
                                <h3
                                    class="confirm-panel__title confirm-panel__title--approve"
                                >
                                    Approve Request —
                                    {{ req.user?.name ?? "—" }}
                                </h3>
                                <p class="confirm-panel__detail">
                                    <strong>{{
                                        req.leave_type?.name ?? "—"
                                    }}</strong>
                                    ·
                                    {{
                                        fmtRange(req.start_date, req.end_date)
                                    }}
                                    ({{ req.total_days }}
                                    hari)
                                </p>
                                <p class="confirm-panel__detail">
                                    Alasan: {{ req.reason }}
                                </p>
                                <p class="confirm-panel__balance">
                                    {{ getBalanceAfterApprove(req) }}
                                </p>
                                <div class="confirm-panel__field">
                                    <label class="confirm-panel__label"
                                        >Catatan Admin (opsional)</label
                                    >
                                    <textarea
                                        v-model="activeAction.adminNotes"
                                        class="confirm-panel__textarea"
                                        rows="3"
                                        placeholder="Approved, selamat berlibur."
                                    />
                                </div>
                                <div class="confirm-panel__actions">
                                    <button
                                        type="button"
                                        class="btn-confirm-approve"
                                        :disabled="submitting"
                                        @click="confirmApprove"
                                    >
                                        <span
                                            v-if="submitting"
                                            class="btn-spinner btn-spinner--white"
                                        />
                                        {{
                                            submitting
                                                ? "Memproses..."
                                                : "Konfirmasi Approve"
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-cancel-action"
                                        @click="closeActionPanel"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </div>

                            <!-- Reject Panel -->
                            <div
                                v-if="isActionOpen(req.id, 'reject')"
                                class="confirm-panel confirm-panel--reject"
                            >
                                <h3
                                    class="confirm-panel__title confirm-panel__title--reject"
                                >
                                    Reject Request — {{ req.user?.name ?? "—" }}
                                </h3>
                                <p class="confirm-panel__detail">
                                    <strong>{{
                                        req.leave_type?.name ?? "—"
                                    }}</strong>
                                    ·
                                    {{
                                        fmtRange(req.start_date, req.end_date)
                                    }}
                                    ({{ req.total_days }}
                                    hari)
                                </p>
                                <p class="confirm-panel__detail">
                                    Alasan: {{ req.reason }}
                                </p>
                                <div class="confirm-panel__field">
                                    <label class="confirm-panel__label"
                                        >Catatan Admin (opsional)</label
                                    >
                                    <textarea
                                        v-model="activeAction.adminNotes"
                                        class="confirm-panel__textarea"
                                        rows="3"
                                        placeholder="Alasan penolakan..."
                                    />
                                </div>
                                <div class="confirm-panel__actions">
                                    <button
                                        type="button"
                                        class="btn-confirm-reject"
                                        :disabled="submitting"
                                        @click="confirmReject"
                                    >
                                        <span
                                            v-if="submitting"
                                            class="btn-spinner btn-spinner--white"
                                        />
                                        {{
                                            submitting
                                                ? "Memproses..."
                                                : "Konfirmasi Reject"
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-cancel-action"
                                        @click="closeActionPanel"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ── No pending message ─────────────────────────────────────────── -->
                <div v-else-if="!loading" class="no-pending">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="36"
                        height="36"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="#10b981"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                        <polyline points="22 4 12 14.01 9 11.01" />
                    </svg>
                    <p class="no-pending__text">
                        Tidak ada request yang perlu ditindaklanjuti.
                    </p>
                </div>

                <!-- ── Riwayat Semua Request ────────────────────────────────────────── -->
                <div v-if="historyRequests.length > 0" class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">Riwayat Semua Request</h2>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>USER</th>
                                <th>TIPE</th>
                                <th>TANGGAL</th>
                                <th>HARI</th>
                                <th>STATUS</th>
                                <th>DIRESPON</th>
                                <th>CATATAN</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="req in historyRequests" :key="req.id">
                                <td class="td-name">
                                    {{ req.user?.name ?? "—" }}
                                </td>
                                <td>{{ req.leave_type?.name ?? "—" }}</td>
                                <td class="td-date">
                                    {{ fmtRange(req.start_date, req.end_date) }}
                                </td>
                                <td class="td-center">{{ req.total_days }}</td>
                                <td>
                                    <StatusBadge :status="req.status" />
                                </td>
                                <td class="td-responded">
                                    {{ fmtDate(req.responded_at) }}
                                </td>
                                <td class="td-notes">
                                    {{ req.admin_notes ?? "—" }}
                                </td>
                                <td>
                                    <button
                                        v-if="req.status !== 'pending'"
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
                                    <span v-else class="td-dash">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty all -->
                <div v-if="requests.length === 0" class="empty-all">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="40"
                        height="40"
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
                        <polyline points="10 9 9 9 8 9" />
                    </svg>
                    <p class="empty-all__text">
                        Belum ada pengajuan cuti dari user manapun.
                    </p>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ── Page ─────────────────────────────────────────────────────────────────── */
.page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    max-width: 1100px;
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

/* ── Stats grid ──────────────────────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.stat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 24px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon--orange {
    background-color: #fff7ed;
    color: #f59e0b;
}
.stat-icon--green {
    background-color: #f0fdf4;
    color: #10b981;
}
.stat-icon--red {
    background-color: #fef2f2;
    color: #ef4444;
}

.stat-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stat-label {
    margin: 0;
    font-size: 0.75rem;
    color: #9ca3af;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.stat-value {
    margin: 0;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
}

.stat-value--orange {
    color: #f59e0b;
}
.stat-value--green {
    color: #10b981;
}
.stat-value--red {
    color: #ef4444;
}

.stat-sub {
    margin: 0;
    font-size: 0.75rem;
    color: #9ca3af;
}

/* ── Section card ────────────────────────────────────────────────────────── */
.section-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
}

.section-title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    color: #111827;
}

.pending-badge {
    padding: 3px 10px;
    background-color: #fef3c7;
    color: #92400e;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
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
    padding: 13px 16px;
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

.td-name {
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
}

.td-date {
    white-space: nowrap;
    color: #374151;
}

.td-center {
    text-align: center;
}

.td-reason {
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #6b7280;
}

.td-responded {
    white-space: nowrap;
    color: #6b7280;
    font-size: 0.82rem;
}

.td-notes {
    max-width: 160px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #6b7280;
    font-size: 0.82rem;
}

.td-dash {
    color: #d1d5db;
}

/* ── Action buttons in table ─────────────────────────────────────────────── */
.action-btns {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: nowrap;
}

.btn-approve {
    padding: 5px 12px;
    background-color: #10b981;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition:
        background-color 0.15s,
        box-shadow 0.15s;
    white-space: nowrap;
    font-family: inherit;
}

.btn-approve:hover,
.btn-approve--active {
    background-color: #059669;
}

.btn-reject {
    padding: 5px 12px;
    background-color: #ef4444;
    color: #ffffff;
    border: none;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition:
        background-color 0.15s,
        box-shadow 0.15s;
    white-space: nowrap;
    font-family: inherit;
}

.btn-reject:hover,
.btn-reject--active {
    background-color: #dc2626;
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

/* ── Confirmation panels ─────────────────────────────────────────────────── */
.confirm-panel {
    margin: 0;
    padding: 20px 24px;
    border-top: 1px solid #f3f4f6;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 520px;
}

.confirm-panel--approve {
    border-left: 4px solid #10b981;
    background-color: #f9fafb;
}

.confirm-panel--reject {
    border-left: 4px solid #ef4444;
    background-color: #f9fafb;
}

.confirm-panel__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.3;
}

.confirm-panel__title--approve {
    color: #065f46;
}
.confirm-panel__title--reject {
    color: #991b1b;
}

.confirm-panel__detail {
    margin: 0;
    font-size: 0.85rem;
    color: #374151;
    line-height: 1.5;
}

.confirm-panel__balance {
    margin: 0;
    font-size: 0.82rem;
    color: #2563eb;
    font-weight: 500;
}

.confirm-panel__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.confirm-panel__label {
    font-size: 0.82rem;
    font-weight: 500;
    color: #374151;
}

.confirm-panel__textarea {
    width: 100%;
    padding: 8px 10px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #111827;
    background: #ffffff;
    resize: vertical;
    font-family: inherit;
    outline: none;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
    box-sizing: border-box;
    max-width: 100%;
}

.confirm-panel__textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.confirm-panel__actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-confirm-approve {
    padding: 8px 16px;
    background-color: #10b981;
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

.btn-confirm-approve:hover:not(:disabled) {
    background-color: #059669;
}

.btn-confirm-approve:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-confirm-reject {
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

.btn-confirm-reject:hover:not(:disabled) {
    background-color: #dc2626;
}

.btn-confirm-reject:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-cancel-action {
    padding: 8px 16px;
    background-color: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.15s;
    font-family: inherit;
}

.btn-cancel-action:hover {
    background-color: #e5e7eb;
}

/* ── Spinner ─────────────────────────────────────────────────────────────── */
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

/* ── No pending ──────────────────────────────────────────────────────────── */
.no-pending {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 32px 24px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.no-pending__text {
    margin: 0;
    font-size: 0.9rem;
    color: #6b7280;
}

/* ── Empty all ───────────────────────────────────────────────────────────── */
.empty-all {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 48px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    text-align: center;
}

.empty-all__text {
    margin: 0;
    font-size: 0.9rem;
    color: #9ca3af;
}

/* ── Loading skeleton ────────────────────────────────────────────────────── */
.loading-area {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.skeleton-row {
    display: flex;
    gap: 16px;
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
</style>
