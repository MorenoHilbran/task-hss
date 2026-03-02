<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

interface Task {
    id: number;
    title: string;
    status: 'pending' | 'done';
    created_at: string;
}

// ─── Auth state ───────────────────────────────────────────────────────────────
const token          = ref<string | null>(localStorage.getItem('api_token'));
const email          = ref('admin@test.com');
const password       = ref('123456');
const loginError     = ref('');
const loginLoading   = ref(false);

// ─── Task state ──────────────────────────────────────────────────────────────
const tasks          = ref<Task[]>([]);
const loadingTasks   = ref(false);
const newTitle       = ref('');
const adding         = ref(false);
const addError       = ref('');
const editingId      = ref<number | null>(null);
const editTitle      = ref('');
const filter         = ref<'all' | 'pending' | 'done'>('all');

// ─── Computed ────────────────────────────────────────────────────────────────
const isLoggedIn   = computed(() => !!token.value);
const pendingCount = computed(() => tasks.value.filter(t => t.status === 'pending').length);
const doneCount    = computed(() => tasks.value.filter(t => t.status === 'done').length);

const filteredTasks = computed(() => {
    if (filter.value === 'all') return tasks.value;
    return tasks.value.filter(t => t.status === filter.value);
});

// ─── API helper ──────────────────────────────────────────────────────────────
async function apiFetch(path: string, opts: RequestInit = {}) {
    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };
    if (token.value) headers['Authorization'] = `Bearer ${token.value}`;

    const res  = await fetch(`/api${path}`, { ...opts, headers });
    const json = await res.json();

    if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
    return json;
}

// ─── Auth ────────────────────────────────────────────────────────────────────
async function login() {
    loginLoading.value = true;
    loginError.value   = '';
    try {
        const resp = await apiFetch('/login', {
            method: 'POST',
            body:   JSON.stringify({ email: email.value, password: password.value }),
        });
        const t = resp.data?.token ?? resp.token ?? 'simple-admin-token-2024';
        token.value = t;
        localStorage.setItem('api_token', t);
        await fetchTasks();
    } catch (e: any) {
        loginError.value = e.message || 'Login gagal. Periksa kembali kredensial Anda.';
    } finally {
        loginLoading.value = false;
    }
}

function logout() {
    token.value = null;
    localStorage.removeItem('api_token');
    tasks.value = [];
}

// ─── Tasks CRUD ──────────────────────────────────────────────────────────────
async function fetchTasks() {
    loadingTasks.value = true;
    try {
        const resp  = await apiFetch('/tasks');
        tasks.value = Array.isArray(resp.data) ? resp.data : [];
    } catch {
        tasks.value = [];
    } finally {
        loadingTasks.value = false;
    }
}

async function addTask() {
    const title = newTitle.value.trim();
    if (!title) return;
    adding.value   = true;
    addError.value = '';
    try {
        const resp = await apiFetch('/tasks', {
            method: 'POST',
            body:   JSON.stringify({ title }),
        });
        tasks.value.unshift(resp.data);
        newTitle.value = '';
    } catch (e: any) {
        addError.value = e.message || 'Gagal menambahkan task.';
    } finally {
        adding.value = false;
    }
}

async function toggleStatus(task: Task) {
    const newStatus = task.status === 'pending' ? 'done' : 'pending';
    try {
        const resp = await apiFetch(`/tasks/${task.id}`, {
            method: 'PUT',
            body:   JSON.stringify({ status: newStatus }),
        });
        const idx = tasks.value.findIndex(t => t.id === task.id);
        if (idx !== -1) tasks.value[idx] = resp.data;
    } catch {}
}

function startEdit(task: Task) {
    editingId.value = task.id;
    editTitle.value  = task.title;
}

async function saveEdit(task: Task) {
    const title = editTitle.value.trim();
    if (!title) return cancelEdit();
    try {
        const resp = await apiFetch(`/tasks/${task.id}`, {
            method: 'PUT',
            body:   JSON.stringify({ title }),
        });
        const idx = tasks.value.findIndex(t => t.id === task.id);
        if (idx !== -1) tasks.value[idx] = resp.data;
    } finally {
        cancelEdit();
    }
}

function cancelEdit() {
    editingId.value = null;
    editTitle.value = '';
}

async function removeTask(id: number) {
    if (!confirm('Hapus task ini?')) return;
    try {
        await apiFetch(`/tasks/${id}`, { method: 'DELETE' });
        tasks.value = tasks.value.filter(t => t.id !== id);
    } catch {}
}

onMounted(() => { if (isLoggedIn.value) fetchTasks(); });
</script>

<template>
    <!-- ══════════ LOGIN ══════════ -->
    <div v-if="!isLoggedIn" class="auth-page">
        <div class="auth-card">
            <div class="auth-logo">
                <svg width="44" height="44" viewBox="0 0 44 44" fill="none">
                    <rect width="44" height="44" rx="12" fill="#16a34a"/>
                    <path d="M13 22l6 6 12-12" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="auth-title">Task Manager</h1>
            <p class="auth-sub">Masuk untuk mengelola tugas Anda</p>

            <form @submit.prevent="login" class="auth-form">
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" v-model="email" type="email" placeholder="admin@test.com" required />
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" v-model="password" type="password" placeholder="••••••" required />
                </div>
                <p v-if="loginError" class="field-error">{{ loginError }}</p>
                <button type="submit" class="btn-primary" :disabled="loginLoading">
                    <span v-if="loginLoading" class="spinner"></span>
                    <span v-else>Masuk</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ══════════ APP ══════════ -->
    <div v-else class="app-layout">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <svg width="32" height="32" viewBox="0 0 44 44" fill="none">
                    <rect width="44" height="44" rx="10" fill="#16a34a"/>
                    <path d="M13 22l6 6 12-12" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>TaskFlow</span>
            </div>

            <nav class="sidebar-nav">
                <button
                    v-for="f in (['all', 'pending', 'done'] as const)"
                    :key="f"
                    class="nav-btn"
                    :class="{ active: filter === f }"
                    @click="filter = f"
                >
                    <span class="nav-indicator" :class="f"></span>
                    <span class="nav-label">
                        {{ f === 'all' ? 'Semua Task' : f === 'pending' ? 'Pending' : 'Selesai' }}
                    </span>
                    <span class="nav-count">
                        {{ f === 'all' ? tasks.length : f === 'pending' ? pendingCount : doneCount }}
                    </span>
                </button>
            </nav>

            <div class="sidebar-stats">
                <div class="stat-row">
                    <span class="stat-label">Total</span>
                    <span class="stat-value">{{ tasks.length }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label pending-text">Pending</span>
                    <span class="stat-value pending-text">{{ pendingCount }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label done-text">Selesai</span>
                    <span class="stat-value done-text">{{ doneCount }}</span>
                </div>
                <div class="progress-bar-wrap">
                    <div
                        class="progress-bar-fill"
                        :style="{ width: tasks.length ? (doneCount / tasks.length * 100) + '%' : '0%' }"
                    ></div>
                </div>
                <p class="progress-label">
                    {{ tasks.length ? Math.round(doneCount / tasks.length * 100) : 0 }}% selesai
                </p>
            </div>

            <button class="btn-logout" @click="logout">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                </svg>
                Keluar
            </button>
        </aside>

        <!-- Main content -->
        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1 class="page-title">
                        {{ filter === 'all' ? 'Semua Task' : filter === 'pending' ? 'Task Pending' : 'Task Selesai' }}
                    </h1>
                    <p class="page-sub">{{ filteredTasks.length }} task ditampilkan</p>
                </div>
            </header>

            <!-- Add Task -->
            <div class="add-task-card">
                <div class="add-task-row">
                    <input
                        id="new-task-input"
                        v-model="newTitle"
                        @keyup.enter="addTask"
                        class="add-input"
                        placeholder="Ketik judul task baru, lalu tekan Enter atau klik Tambah..."
                        :disabled="adding"
                        autocomplete="off"
                    />
                    <button
                        id="add-task-btn"
                        class="btn-add"
                        @click="addTask"
                        :disabled="adding || !newTitle.trim()"
                    >
                        <span v-if="adding" class="spinner spinner-green"></span>
                        <template v-else>
                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
                            Tambah
                        </template>
                    </button>
                </div>
                <p v-if="addError" class="field-error mt-1">{{ addError }}</p>
            </div>

            <!-- Loading -->
            <div v-if="loadingTasks" class="state-center">
                <div class="loader-ring"></div>
                <p class="state-text">Memuat task...</p>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredTasks.length === 0" class="state-center">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                    <circle cx="32" cy="32" r="30" stroke="#d1fae5" stroke-width="2"/>
                    <path d="M22 32h20M32 22v20" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" opacity=".5"/>
                </svg>
                <p class="state-text">Belum ada task.<br>Tambahkan task pertama Anda!</p>
            </div>

            <!-- Task list -->
            <ul v-else class="task-list">
                <li
                    v-for="task in filteredTasks"
                    :key="task.id"
                    class="task-card"
                    :class="{ 'task-done': task.status === 'done' }"
                >
                    <!-- Checkbox toggle -->
                    <button
                        class="check-btn"
                        @click="toggleStatus(task)"
                        :title="task.status === 'pending' ? 'Tandai selesai' : 'Tandai pending'"
                    >
                        <span v-if="task.status === 'done'" class="check-icon check-done">
                            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        </span>
                        <span v-else class="check-icon check-empty"></span>
                    </button>

                    <!-- Title / edit -->
                    <div class="task-body">
                        <template v-if="editingId === task.id">
                            <input
                                v-model="editTitle"
                                class="edit-input"
                                @keyup.enter="saveEdit(task)"
                                @keyup.escape="cancelEdit"
                                autofocus
                            />
                        </template>
                        <template v-else>
                            <span class="task-title" :class="{ 'task-title-done': task.status === 'done' }">
                                {{ task.title }}
                            </span>
                        </template>
                    </div>

                    <!-- Badge -->
                    <span class="badge" :class="task.status === 'done' ? 'badge-done' : 'badge-pending'">
                        {{ task.status === 'done' ? 'Selesai' : 'Pending' }}
                    </span>

                    <!-- Actions -->
                    <div class="task-actions">
                        <template v-if="editingId === task.id">
                            <button class="icon-btn icon-save" @click="saveEdit(task)" title="Simpan">✓</button>
                            <button class="icon-btn icon-cancel" @click="cancelEdit" title="Batal">✕</button>
                        </template>
                        <template v-else>
                            <button class="icon-btn icon-edit" @click="startEdit(task)" title="Edit">
                                <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                            </button>
                            <button class="icon-btn icon-delete" @click="removeTask(task.id)" title="Hapus">
                                <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd"/></svg>
                            </button>
                        </template>
                    </div>
                </li>
            </ul>
        </main>
    </div>
</template>

<style scoped>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Fonts ── */
:root {
    --green:       #16a34a;
    --green-light: #dcfce7;
    --green-mid:   #86efac;
    --green-dark:  #15803d;
    --text:        #111827;
    --text-muted:  #6b7280;
    --border:      #e5e7eb;
    --bg:          #f9fafb;
    --white:       #ffffff;
    --red:         #ef4444;
    --amber:       #f59e0b;
}

/* ── Auth ── */
.auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg);
    padding: 1.5rem;
}

.auth-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 4px 24px rgba(0,0,0,.06);
}

.auth-logo { display: flex; justify-content: center; margin-bottom: 1.25rem; }

.auth-title {
    text-align: center;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -.02em;
}
.auth-sub { text-align: center; color: var(--text-muted); font-size: .875rem; margin: .35rem 0 1.75rem; }

.auth-form { display: flex; flex-direction: column; gap: 1rem; }

.field { display: flex; flex-direction: column; gap: .35rem; }
.field label { font-size: .8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
.field input, .add-input, .edit-input {
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: .7rem .9rem;
    font-size: .95rem;
    color: var(--text);
    background: var(--white);
    outline: none;
    transition: border .2s;
    width: 100%;
}
.field input:focus, .add-input:focus, .edit-input:focus {
    border-color: var(--green);
}
.field input::placeholder, .add-input::placeholder { color: #9ca3af; }
.field-error { font-size: .82rem; color: var(--red); }
.mt-1 { margin-top: .35rem; }

.btn-primary {
    padding: .8rem;
    border-radius: 10px;
    border: none;
    background: var(--green);
    color: white;
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    transition: background .2s, transform .12s;
}
.btn-primary:hover:not(:disabled) { background: var(--green-dark); }
.btn-primary:active:not(:disabled) { transform: scale(.98); }
.btn-primary:disabled { opacity: .55; cursor: not-allowed; }

/* ── App layout ── */
.app-layout {
    display: flex;
    min-height: 100vh;
    background: var(--bg);
    font-family: 'Inter', system-ui, sans-serif;
    color: var(--text);
}

/* ── Sidebar ── */
.sidebar {
    width: 248px;
    flex-shrink: 0;
    background: var(--white);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 1.5rem 1rem;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: 0 .5rem;
    margin-bottom: 1.75rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
}

.sidebar-nav { display: flex; flex-direction: column; gap: .2rem; }

.nav-btn {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .6rem .75rem;
    border-radius: 10px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: .875rem;
    font-weight: 500;
    cursor: pointer;
    text-align: left;
    transition: background .15s, color .15s;
    width: 100%;
}
.nav-btn:hover { background: var(--green-light); color: var(--green-dark); }
.nav-btn.active { background: var(--green-light); color: var(--green); font-weight: 600; }

.nav-indicator {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.nav-indicator.all     { background: var(--text-muted); }
.nav-indicator.pending { background: var(--amber); }
.nav-indicator.done    { background: var(--green); }

.nav-label { flex: 1; }

.nav-count {
    font-size: .72rem;
    background: var(--bg);
    border: 1px solid var(--border);
    padding: .1rem .4rem;
    border-radius: 20px;
    color: var(--text-muted);
}
.nav-btn.active .nav-count { background: white; border-color: var(--green-mid); color: var(--green-dark); }

.sidebar-stats {
    margin-top: auto;
    border-top: 1px solid var(--border);
    padding-top: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .4rem;
}
.stat-row { display: flex; justify-content: space-between; font-size: .8rem; }
.stat-label { color: var(--text-muted); }
.stat-value { font-weight: 600; color: var(--text); }
.pending-text { color: var(--amber); }
.done-text    { color: var(--green); }

.progress-bar-wrap {
    margin-top: .4rem;
    height: 6px;
    background: var(--green-light);
    border-radius: 10px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: var(--green);
    border-radius: 10px;
    transition: width .4s ease;
}
.progress-label { font-size: .75rem; color: var(--text-muted); text-align: right; }

.btn-logout {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-top: 1rem;
    padding: .6rem .75rem;
    width: 100%;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: .85rem;
    cursor: pointer;
    transition: background .15s, color .15s, border .15s;
}
.btn-logout:hover { background: #fef2f2; color: var(--red); border-color: #fecaca; }

/* ── Main ── */
.main-content {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
    max-width: 820px;
}

.page-header    { margin-bottom: 1.5rem; }
.page-title     { font-size: 1.5rem; font-weight: 700; color: var(--text); letter-spacing: -.02em; }
.page-sub       { font-size: .85rem; color: var(--text-muted); margin-top: .2rem; }

/* ── Add Task ── */
.add-task-card {
    background: var(--white);
    border: 1.5px solid var(--green-mid);
    border-radius: 14px;
    padding: 1rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 8px rgba(22,163,74,.08);
}

.add-task-row {
    display: flex;
    gap: .75rem;
}

.add-input { flex: 1; }

.btn-add {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .7rem 1.25rem;
    border-radius: 10px;
    border: none;
    background: var(--green);
    color: white;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s, transform .12s;
    flex-shrink: 0;
}
.btn-add:hover:not(:disabled) { background: var(--green-dark); }
.btn-add:active:not(:disabled) { transform: scale(.97); }
.btn-add:disabled { opacity: .45; cursor: not-allowed; }

/* ── States ── */
.state-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 4rem 0;
}
.state-text { color: var(--text-muted); text-align: center; line-height: 1.7; font-size: .9rem; }

.loader-ring {
    width: 36px; height: 36px;
    border: 3px solid var(--green-light);
    border-top-color: var(--green);
    border-radius: 50%;
    animation: spin .8s linear infinite;
}

/* ── Task list ── */
.task-list { list-style: none; display: flex; flex-direction: column; gap: .5rem; }

.task-card {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: .875rem 1rem;
    transition: border .2s, box-shadow .2s;
    animation: slideIn .2s ease;
}
.task-card:hover { border-color: var(--green-mid); box-shadow: 0 2px 12px rgba(22,163,74,.06); }
.task-card.task-done { background: #f9fafb; }

/* Check button */
.check-btn {
    flex-shrink: 0;
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    width: 22px; height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.check-icon {
    width: 20px; height: 20px;
    border-radius: 50%;
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s, border .15s;
}
.check-icon.check-done {
    background: var(--green);
    border-color: var(--green);
    color: white;
}
.check-icon.check-empty:hover {
    border-color: var(--green);
}

.task-body { flex: 1; min-width: 0; }

.task-title {
    font-size: .9rem;
    color: var(--text);
    word-break: break-word;
    display: block;
}
.task-title-done {
    text-decoration: line-through;
    color: var(--text-muted);
}

.edit-input { padding: .35rem .6rem; font-size: .9rem; }

/* Badge */
.badge {
    flex-shrink: 0;
    font-size: .7rem;
    font-weight: 600;
    padding: .15rem .55rem;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.badge-pending { background: #fef3c7; color: #92400e; }
.badge-done    { background: var(--green-light); color: var(--green-dark); }

.task-actions { display: flex; gap: .3rem; flex-shrink: 0; }

.icon-btn {
    width: 30px; height: 30px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--white);
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    transition: background .15s, color .15s, border .15s;
}
.icon-btn.icon-edit:hover   { background: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
.icon-btn.icon-delete:hover { background: #fef2f2; color: var(--red); border-color: #fecaca; }
.icon-btn.icon-save:hover   { background: var(--green-light); color: var(--green); border-color: var(--green-mid); }
.icon-btn.icon-cancel:hover { background: #fef2f2; color: var(--red); border-color: #fecaca; }

/* ── Spinner ── */
.spinner {
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,.4);
    border-top-color: white;
    border-radius: 50%;
    animation: spin .7s linear infinite;
    display: inline-block;
}
.spinner-green {
    border-color: var(--green-light);
    border-top-color: var(--green);
}

@keyframes spin    { to { transform: rotate(360deg); } }
@keyframes slideIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }

/* ── Responsive ── */
@media (max-width: 640px) {
    .app-layout { flex-direction: column; }
    .sidebar    { width: 100%; flex-direction: row; flex-wrap: wrap; padding: .75rem; overflow-x: auto; }
    .sidebar-nav { flex-direction: row; flex: 1; gap: .25rem; }
    .sidebar-stats, .btn-logout { margin-top: .5rem; }
    .main-content { padding: 1rem; }
}
</style>
