<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import {
    getAdminUsers,
    createAdminUser,
    updateAdminUser,
} from "@/services/admin.service";
import { toast } from "@/plugins/toast";
import AppLayout from "@/components/layout/AppLayout.vue";
import BaseModal from "@/components/ui/BaseModal.vue";
import type { AdminUser, CreateUserPayload, UpdateUserPayload } from "@/types";

// ─── Data ─────────────────────────────────────────────────────────────────────

const users = ref<AdminUser[]>([]);
const loading = ref(false);

// ─── Stats ────────────────────────────────────────────────────────────────────

const MAX_USERS = 2;

const totalUsers = computed(() => users.value.length);
const availableSlots = computed(() =>
    Math.max(0, MAX_USERS - totalUsers.value),
);
const isAtCapacity = computed(() => totalUsers.value >= MAX_USERS);

// ─── Modal state ──────────────────────────────────────────────────────────────

type ModalMode = "create" | "edit" | null;

const modalMode = ref<ModalMode>(null);
const editingUser = ref<AdminUser | null>(null);
const submitting = ref(false);

const form = ref({
    name: "",
    email: "",
    password: "",
});

const fieldErrors = ref<Record<string, string>>({});

const modalTitle = computed(() =>
    modalMode.value === "create" ? "Tambah User Baru" : "Update Password User",
);

const modalSubtitle = computed(() =>
    modalMode.value === "create"
        ? "Leave balance otomatis ter-assign saat user dibuat."
        : `Edit akun: ${editingUser.value?.name ?? ""}`,
);

// ─── Load users ───────────────────────────────────────────────────────────────

async function fetchUsers() {
    loading.value = true;
    try {
        users.value = await getAdminUsers();
    } catch (err) {
        toast.apiError(err, "Gagal memuat data user.");
    } finally {
        loading.value = false;
    }
}

onMounted(fetchUsers);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getLeaveBalance(user: AdminUser, typeName: string) {
    if (!user.leave_balances) return null;
    return (
        user.leave_balances.find((b) =>
            b.leave_type?.name?.toLowerCase().includes(typeName.toLowerCase()),
        ) ?? null
    );
}

function formatBalance(user: AdminUser, typeName: string): string {
    const balance = getLeaveBalance(user, typeName);
    if (!balance) return "— / —";
    return `${balance.remaining_quota} / ${balance.total_quota} hari`;
}

// ─── Open modals ──────────────────────────────────────────────────────────────

function openCreateModal() {
    if (isAtCapacity.value) {
        toast.warning(
            "Kuota Penuh",
            "Maksimal 2 user sudah tercapai. Tidak bisa menambah user baru.",
        );
        return;
    }
    form.value = { name: "", email: "", password: "" };
    fieldErrors.value = {};
    editingUser.value = null;
    modalMode.value = "create";
}

function openEditModal(user: AdminUser) {
    form.value = { name: user.name, email: user.email, password: "" };
    fieldErrors.value = {};
    editingUser.value = user;
    modalMode.value = "edit";
}

function closeModal() {
    modalMode.value = null;
    editingUser.value = null;
    fieldErrors.value = {};
}

// ─── Validation ───────────────────────────────────────────────────────────────

function validateCreate(): boolean {
    fieldErrors.value = {};

    if (!form.value.name.trim()) {
        fieldErrors.value.name = "Nama lengkap wajib diisi.";
    } else if (form.value.name.trim().length < 2) {
        fieldErrors.value.name = "Nama minimal 2 karakter.";
    }

    if (!form.value.email.trim()) {
        fieldErrors.value.email = "Email wajib diisi.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email.trim())) {
        fieldErrors.value.email = "Format email tidak valid.";
    }

    if (!form.value.password) {
        fieldErrors.value.password = "Password wajib diisi.";
    } else if (form.value.password.length < 8) {
        fieldErrors.value.password = "Password minimal 8 karakter.";
    }

    return Object.keys(fieldErrors.value).length === 0;
}

function validateEdit(): boolean {
    fieldErrors.value = {};

    if (!form.value.name.trim()) {
        fieldErrors.value.name = "Nama lengkap wajib diisi.";
    }

    if (!form.value.email.trim()) {
        fieldErrors.value.email = "Email wajib diisi.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email.trim())) {
        fieldErrors.value.email = "Format email tidak valid.";
    }

    if (form.value.password && form.value.password.length < 8) {
        fieldErrors.value.password = "Password minimal 8 karakter.";
    }

    return Object.keys(fieldErrors.value).length === 0;
}

// ─── Submit ───────────────────────────────────────────────────────────────────

async function handleSubmit() {
    if (modalMode.value === "create") {
        await handleCreate();
    } else if (modalMode.value === "edit") {
        await handleEdit();
    }
}

async function handleCreate() {
    if (!validateCreate()) return;
    submitting.value = true;

    const payload: CreateUserPayload = {
        name: form.value.name.trim(),
        email: form.value.email.trim(),
        password: form.value.password,
    };

    try {
        const newUser = await createAdminUser(payload);
        users.value.push(newUser);
        toast.success("User Dibuat", `Akun ${newUser.name} berhasil dibuat.`);
        closeModal();
        // Refresh to get leave_balances populated
        await fetchUsers();
    } catch (err: unknown) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const axiosErr = err as any;
        if (axiosErr?.response?.status === 422) {
            const errors = axiosErr.response.data?.errors as
                | Record<string, string[]>
                | undefined;
            if (errors) {
                Object.entries(errors).forEach(([key, msgs]) => {
                    fieldErrors.value[key] = msgs[0];
                });
                // Check for max-user-limit message surfaced in the general 'message' field
                const generalMsg: string =
                    axiosErr.response.data?.message ?? "";
                if (
                    generalMsg.toLowerCase().includes("maks") ||
                    generalMsg.toLowerCase().includes("limit")
                ) {
                    toast.error("Kuota Penuh", generalMsg);
                }
                return;
            }
        }
        toast.apiError(err, "Gagal membuat user.");
    } finally {
        submitting.value = false;
    }
}

async function handleEdit() {
    if (!editingUser.value || !validateEdit()) return;
    submitting.value = true;

    const payload: UpdateUserPayload = {
        name: form.value.name.trim(),
        email: form.value.email.trim(),
    };
    if (form.value.password) {
        payload.password = form.value.password;
    }

    try {
        await updateAdminUser(editingUser.value.id, payload);
        toast.success(
            "User Diperbarui",
            `Akun ${form.value.name} berhasil diperbarui.`,
        );
        closeModal();
        await fetchUsers();
    } catch (err: unknown) {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const axiosErr = err as any;
        if (axiosErr?.response?.status === 422) {
            const errors = axiosErr.response.data?.errors as
                | Record<string, string[]>
                | undefined;
            if (errors) {
                Object.entries(errors).forEach(([key, msgs]) => {
                    fieldErrors.value[key] = msgs[0];
                });
                return;
            }
        }
        toast.apiError(err, "Gagal memperbarui user.");
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <AppLayout>
        <div class="page">
            <!-- ── Page header ─────────────────────────────────────────────────────── -->
            <div class="page-header">
                <h1 class="page-title">Kelola User</h1>
                <p class="page-subtitle">
                    Buat dan kelola akun user. Maksimal {{ MAX_USERS }} user.
                </p>
            </div>

            <!-- ── Stats cards ────────────────────────────────────────────────────── -->
            <div class="stats-grid">
                <!-- Total user -->
                <div class="stat-card">
                    <div class="stat-icon stat-icon--blue">
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
                                d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
                            />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </div>
                    <div class="stat-body">
                        <p class="stat-label">Total User</p>
                        <p class="stat-value stat-value--blue">
                            {{ totalUsers }}
                        </p>
                        <p class="stat-sub">Maks. {{ MAX_USERS }} user</p>
                    </div>
                </div>

                <!-- Available slots -->
                <div class="stat-card">
                    <div
                        class="stat-icon"
                        :class="
                            availableSlots > 0
                                ? 'stat-icon--green'
                                : 'stat-icon--gray'
                        "
                    >
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
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                    <div class="stat-body">
                        <p class="stat-label">Slot Tersedia</p>
                        <p
                            class="stat-value"
                            :class="
                                availableSlots > 0
                                    ? 'stat-value--green'
                                    : 'stat-value--gray'
                            "
                        >
                            {{ availableSlots }}
                        </p>
                        <p class="stat-sub">
                            {{ isAtCapacity ? "Kuota penuh" : "Slot kosong" }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── User table ─────────────────────────────────────────────────────── -->
            <div class="table-card">
                <div class="table-card__header">
                    <h2 class="table-card__title">Daftar User</h2>
                    <button
                        type="button"
                        class="btn-add"
                        :disabled="isAtCapacity || loading"
                        :title="
                            isAtCapacity
                                ? 'Kuota user sudah penuh (maks. 2)'
                                : 'Tambah user baru'
                        "
                        @click="openCreateModal"
                    >
                        + Tambah User
                    </button>
                </div>

                <!-- Loading skeleton -->
                <div v-if="loading" class="table-loading">
                    <div v-for="n in 3" :key="n" class="skeleton-row">
                        <div class="skeleton skeleton--name" />
                        <div class="skeleton skeleton--email" />
                        <div class="skeleton skeleton--balance" />
                        <div class="skeleton skeleton--balance" />
                        <div class="skeleton skeleton--btn" />
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else-if="users.length === 0" class="empty-state">
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
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    <p class="empty-state__text">Belum ada user terdaftar.</p>
                    <button
                        type="button"
                        class="btn-add"
                        @click="openCreateModal"
                    >
                        + Tambah User Pertama
                    </button>
                </div>

                <!-- Table -->
                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>NAMA</th>
                            <th>EMAIL</th>
                            <th>ANNUAL LEAVE</th>
                            <th>SICK LEAVE</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id">
                            <td class="td-name">{{ user.name }}</td>
                            <td class="td-email">{{ user.email }}</td>
                            <td>{{ formatBalance(user, "annual") }}</td>
                            <td>{{ formatBalance(user, "sick") }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-action"
                                    @click="openEditModal(user)"
                                >
                                    Update Password
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Create / Edit Modal ────────────────────────────────────────────── -->
            <BaseModal
                v-if="modalMode !== null"
                :title="modalTitle"
                :subtitle="modalSubtitle"
                size="md"
                @close="closeModal"
            >
                <form @submit.prevent="handleSubmit">
                    <div class="form-fields">
                        <!-- Name -->
                        <div class="field-group">
                            <label class="field-label" for="user-name"
                                >Nama Lengkap</label
                            >
                            <input
                                id="user-name"
                                v-model="form.name"
                                type="text"
                                class="field-input"
                                :class="{
                                    'field-input--error': fieldErrors.name,
                                }"
                                :placeholder="
                                    modalMode === 'create' ? 'Budi Santoso' : ''
                                "
                                autocomplete="off"
                            />
                            <p v-if="fieldErrors.name" class="field-error">
                                {{ fieldErrors.name }}
                            </p>
                        </div>

                        <!-- Email + Password row -->
                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label" for="user-email"
                                    >Email</label
                                >
                                <input
                                    id="user-email"
                                    v-model="form.email"
                                    type="email"
                                    class="field-input"
                                    :class="{
                                        'field-input--error': fieldErrors.email,
                                    }"
                                    placeholder="budi@energeek.id"
                                    autocomplete="off"
                                />
                                <p v-if="fieldErrors.email" class="field-error">
                                    {{ fieldErrors.email }}
                                </p>
                            </div>

                            <div class="field-group">
                                <label class="field-label" for="user-password">
                                    Password
                                    <span
                                        v-if="modalMode === 'edit'"
                                        class="field-label--optional"
                                        >(opsional)</span
                                    >
                                </label>
                                <input
                                    id="user-password"
                                    v-model="form.password"
                                    type="password"
                                    class="field-input"
                                    :class="{
                                        'field-input--error':
                                            fieldErrors.password,
                                    }"
                                    :placeholder="
                                        modalMode === 'edit'
                                            ? 'Kosongkan jika tidak diubah'
                                            : 'Min. 8 karakter'
                                    "
                                    autocomplete="new-password"
                                />
                                <p
                                    v-if="fieldErrors.password"
                                    class="field-error"
                                >
                                    {{ fieldErrors.password }}
                                </p>
                            </div>
                        </div>

                        <!-- Auto-assign info (create only) -->
                        <div v-if="modalMode === 'create'" class="info-banner">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="14"
                                height="14"
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
                            <span
                                >Auto-assign: Annual Leave (12 hari), Sick Leave
                                (6 hari)</span
                            >
                        </div>
                    </div>
                </form>

                <template #footer>
                    <button
                        type="button"
                        class="modal-btn modal-btn--cancel"
                        @click="closeModal"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="modal-btn modal-btn--submit"
                        :disabled="submitting"
                        @click="handleSubmit"
                    >
                        <span v-if="submitting" class="btn-spinner" />
                        {{
                            submitting
                                ? "Menyimpan..."
                                : modalMode === "create"
                                  ? "Simpan User"
                                  : "Simpan Perubahan"
                        }}
                    </button>
                </template>
            </BaseModal>
        </div>
    </AppLayout>
</template>

<style scoped>
/* ── Page layout ─────────────────────────────────────────────────────────── */
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

/* ── Stats grid ──────────────────────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
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
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon--blue {
    background-color: #eff6ff;
    color: #3b82f6;
}
.stat-icon--green {
    background-color: #f0fdf4;
    color: #10b981;
}
.stat-icon--gray {
    background-color: #f9fafb;
    color: #9ca3af;
}

.stat-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stat-label {
    margin: 0;
    font-size: 0.78rem;
    color: #9ca3af;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-value {
    margin: 0;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.1;
    letter-spacing: -0.03em;
}

.stat-value--blue {
    color: #3b82f6;
}
.stat-value--green {
    color: #10b981;
}
.stat-value--gray {
    color: #9ca3af;
}

.stat-sub {
    margin: 0;
    font-size: 0.75rem;
    color: #9ca3af;
}

/* ── Table card ──────────────────────────────────────────────────────────── */
.table-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
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

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn-add {
    padding: 7px 16px;
    background-color: #4f46e5;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition:
        background-color 0.15s,
        opacity 0.15s;
    font-family: inherit;
}

.btn-add:hover:not(:disabled) {
    background-color: #4338ca;
}

.btn-add:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.btn-action {
    padding: 5px 12px;
    background-color: #ffffff;
    color: #374151;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    font-family: inherit;
}

.btn-action:hover {
    border-color: #4f46e5;
    color: #4f46e5;
    background-color: #f5f3ff;
}

/* ── Table cells ─────────────────────────────────────────────────────────── */
.td-name {
    font-weight: 600;
    color: #111827;
}

.td-email {
    color: #6b7280;
}

/* ── Loading skeleton ────────────────────────────────────────────────────── */
.table-loading {
    padding: 12px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.skeleton-row {
    display: grid;
    grid-template-columns: 200px 260px 120px 120px 130px;
    gap: 16px;
    align-items: center;
}

.skeleton {
    height: 14px;
    border-radius: 6px;
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
}

.skeleton--name {
    width: 140px;
}
.skeleton--email {
    width: 200px;
}
.skeleton--balance {
    width: 90px;
}
.skeleton--btn {
    width: 110px;
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

/* ── Modal form ──────────────────────────────────────────────────────────── */
.form-fields {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.field-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
}

.field-label--optional {
    font-size: 0.75rem;
    font-weight: 400;
    color: #9ca3af;
}

.field-input {
    padding: 9px 12px;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    color: #111827;
    background: #ffffff;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
    outline: none;
    font-family: inherit;
    width: 100%;
    box-sizing: border-box;
}

.field-input::placeholder {
    color: #d1d5db;
}

.field-input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.field-input--error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.08) !important;
}

.field-error {
    margin: 0;
    font-size: 0.775rem;
    color: #ef4444;
}

/* ── Info banner ─────────────────────────────────────────────────────────── */
.info-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background-color: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    font-size: 0.8rem;
    color: #1d4ed8;
}

/* ── Modal buttons ───────────────────────────────────────────────────────── */
.modal-btn {
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.modal-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.modal-btn--cancel {
    background-color: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.modal-btn--cancel:hover:not(:disabled) {
    background-color: #e5e7eb;
}

.modal-btn--submit {
    background-color: #4f46e5;
    color: #ffffff;
}

.modal-btn--submit:hover:not(:disabled) {
    background-color: #4338ca;
}

/* ── Spinner ─────────────────────────────────────────────────────────────── */
.btn-spinner {
    display: inline-block;
    width: 13px;
    height: 13px;
    border: 2px solid rgba(255, 255, 255, 0.35);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    flex-shrink: 0;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
