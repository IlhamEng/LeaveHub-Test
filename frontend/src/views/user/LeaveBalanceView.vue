<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import AppLayout from "@/components/layout/AppLayout.vue";
import { getLeaveBalances, getLeaveRequests } from "@/services/user.service";
import { toast } from "@/plugins/toast";
import type { LeaveBalance, LeaveRequest } from "@/types";

// ─── Data ─────────────────────────────────────────────────────────────────────

const balances = ref<LeaveBalance[]>([]);
const requests = ref<LeaveRequest[]>([]);
const loading = ref(false);

// ─── Derived ──────────────────────────────────────────────────────────────────

const annualLeave = computed<LeaveBalance | null>(() => {
    return (
        balances.value.find((b) =>
            b.leave_type?.name?.toLowerCase().includes("annual"),
        ) ?? null
    );
});

const sickLeave = computed<LeaveBalance | null>(() => {
    return (
        balances.value.find((b) =>
            b.leave_type?.name?.toLowerCase().includes("sick"),
        ) ?? null
    );
});

const currentYear = computed(() => new Date().getFullYear());

// ─── Request stats ────────────────────────────────────────────────────────────

const pendingCount = computed(
    () => requests.value.filter((r) => r.status === "pending").length,
);

const approvedCount = computed(
    () => requests.value.filter((r) => r.status === "approved").length,
);

const rejectedCount = computed(
    () => requests.value.filter((r) => r.status === "rejected").length,
);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function remainingPercent(balance: LeaveBalance | null): number {
    if (!balance || balance.total_quota === 0) return 0;
    return Math.min(
        100,
        Math.round((balance.remaining_quota / balance.total_quota) * 100),
    );
}

// ─── Fetch ────────────────────────────────────────────────────────────────────

async function fetchData() {
    loading.value = true;
    try {
        const [bal, req] = await Promise.all([
            getLeaveBalances(),
            getLeaveRequests(),
        ]);
        balances.value = bal;
        requests.value = req;
    } catch (err) {
        toast.apiError(err, "Gagal memuat data kuota cuti.");
    } finally {
        loading.value = false;
    }
}

onMounted(fetchData);
</script>

<template>
    <AppLayout>
        <div class="page">
            <!-- ── Page header ──────────────────────────────────────────────── -->
            <div class="page-header">
                <h1 class="page-title">Sisa Kuota Cuti</h1>
                <p class="page-subtitle">
                    Periode: Tahun {{ currentYear }} · Kalkulasi: total_quota -
                    used
                </p>
            </div>

            <!-- ── Loading skeleton ────────────────────────────────────────── -->
            <template v-if="loading">
                <div class="skeleton-grid-2">
                    <div class="skeleton-card" />
                    <div class="skeleton-card" />
                </div>
                <div class="skeleton-grid-3">
                    <div class="skeleton-card skeleton-card--sm" />
                    <div class="skeleton-card skeleton-card--sm" />
                    <div class="skeleton-card skeleton-card--sm" />
                </div>
            </template>

            <template v-else>
                <!-- ── Quota cards ──────────────────────────────────────────── -->
                <div class="quota-grid">
                    <!-- Annual Leave -->
                    <div class="quota-card">
                        <div class="quota-card__header">
                            <span
                                class="quota-card__emoji"
                                role="img"
                                aria-label="Annual Leave"
                                >🏖️</span
                            >
                            <h2 class="quota-card__title">Annual Leave</h2>
                        </div>

                        <div class="quota-card__progress-wrap">
                            <div class="progress-bar">
                                <div
                                    class="progress-bar__fill progress-bar__fill--blue"
                                    :style="{
                                        width:
                                            remainingPercent(annualLeave) + '%',
                                    }"
                                />
                            </div>
                        </div>

                        <div class="quota-card__stats">
                            <span class="quota-stat quota-stat--used">
                                Terpakai:
                                <strong
                                    >{{ annualLeave?.used ?? 0 }} hari</strong
                                >
                            </span>
                            <span class="quota-stat quota-stat--remaining">
                                Sisa:
                                <strong>
                                    {{ annualLeave?.remaining_quota ?? 0 }} /
                                    {{ annualLeave?.total_quota ?? 0 }} hari
                                </strong>
                            </span>
                        </div>
                    </div>

                    <!-- Sick Leave -->
                    <div class="quota-card">
                        <div class="quota-card__header">
                            <span
                                class="quota-card__emoji"
                                role="img"
                                aria-label="Sick Leave"
                                >🏥</span
                            >
                            <h2 class="quota-card__title">Sick Leave</h2>
                        </div>

                        <div class="quota-card__progress-wrap">
                            <div class="progress-bar">
                                <div
                                    class="progress-bar__fill progress-bar__fill--green"
                                    :style="{
                                        width:
                                            remainingPercent(sickLeave) + '%',
                                    }"
                                />
                            </div>
                        </div>

                        <div class="quota-card__stats">
                            <span class="quota-stat quota-stat--used">
                                Terpakai:
                                <strong>{{ sickLeave?.used ?? 0 }} hari</strong>
                            </span>
                            <span class="quota-stat quota-stat--remaining">
                                Sisa:
                                <strong>
                                    {{ sickLeave?.remaining_quota ?? 0 }} /
                                    {{ sickLeave?.total_quota ?? 0 }} hari
                                </strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ── Status summary cards ─────────────────────────────────── -->
                <div class="status-grid">
                    <!-- Pending -->
                    <div class="status-card">
                        <div
                            class="status-card__icon status-card__icon--pending"
                        >
                            <!-- Hourglass icon -->
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M5 22h14M5 2h14M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22M17 2v4.172a2 2 0 0 1-.586 1.414L12 12 7.586 7.586A2 2 0 0 1 7 6.172V2"
                                />
                            </svg>
                        </div>
                        <div class="status-card__body">
                            <p class="status-card__label">Pending</p>
                            <p
                                class="status-card__value status-card__value--pending"
                            >
                                {{ pendingCount }}
                            </p>
                            <p class="status-card__sub">Menunggu approval</p>
                        </div>
                    </div>

                    <!-- Approved -->
                    <div class="status-card">
                        <div
                            class="status-card__icon status-card__icon--approved"
                        >
                            <!-- Check icon -->
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                        </div>
                        <div class="status-card__body">
                            <p class="status-card__label">Approved</p>
                            <p
                                class="status-card__value status-card__value--approved"
                            >
                                {{ approvedCount }}
                            </p>
                            <p class="status-card__sub">Disetujui tahun ini</p>
                        </div>
                    </div>

                    <!-- Rejected -->
                    <div class="status-card">
                        <div
                            class="status-card__icon status-card__icon--rejected"
                        >
                            <!-- X icon -->
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="22"
                                height="22"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </div>
                        <div class="status-card__body">
                            <p class="status-card__label">Rejected</p>
                            <p
                                class="status-card__value status-card__value--rejected"
                            >
                                {{ rejectedCount }}
                            </p>
                            <p class="status-card__sub">Ditolak</p>
                        </div>
                    </div>
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
    max-width: 960px;
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

/* ── Quota grid ──────────────────────────────────────────────────────────── */
.quota-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

/* ── Quota card ──────────────────────────────────────────────────────────── */
.quota-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 22px 24px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.quota-card__header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.quota-card__emoji {
    font-size: 1.3rem;
    line-height: 1;
}

.quota-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}

/* ── Progress bar ────────────────────────────────────────────────────────── */
.quota-card__progress-wrap {
    padding: 0;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background-color: #f3f4f6;
    border-radius: 9999px;
    overflow: hidden;
}

.progress-bar__fill {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.6s ease-out;
}

.progress-bar__fill--blue {
    background: linear-gradient(90deg, #6366f1, #4f46e5);
}

.progress-bar__fill--green {
    background: linear-gradient(90deg, #34d399, #10b981);
}

/* ── Quota stats ─────────────────────────────────────────────────────────── */
.quota-card__stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.quota-stat {
    font-size: 0.82rem;
    color: #6b7280;
}

.quota-stat strong {
    font-weight: 600;
    color: #374151;
}

.quota-stat--remaining strong {
    color: #111827;
}

/* ── Status summary grid ─────────────────────────────────────────────────── */
.status-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

/* ── Status card ─────────────────────────────────────────────────────────── */
.status-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px 24px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.status-card__icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.status-card__icon--pending {
    background-color: #fff7ed;
    color: #f59e0b;
}

.status-card__icon--approved {
    background-color: #f0fdf4;
    color: #10b981;
}

.status-card__icon--rejected {
    background-color: #fef2f2;
    color: #ef4444;
}

.status-card__body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.status-card__label {
    margin: 0;
    font-size: 0.78rem;
    color: #9ca3af;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.status-card__value {
    margin: 0;
    font-size: 2.25rem;
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -0.04em;
}

.status-card__value--pending {
    color: #f59e0b;
}

.status-card__value--approved {
    color: #10b981;
}

.status-card__value--rejected {
    color: #ef4444;
}

.status-card__sub {
    margin: 0;
    font-size: 0.75rem;
    color: #9ca3af;
    line-height: 1.4;
}

/* ── Skeleton loaders ────────────────────────────────────────────────────── */
.skeleton-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.skeleton-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.skeleton-card {
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    border-radius: 14px;
    height: 120px;
    border: 1px solid #e5e7eb;
}

.skeleton-card--sm {
    height: 100px;
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
