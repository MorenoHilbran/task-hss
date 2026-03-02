<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

interface Task {
    id: number;
    title: string;
    status: 'pending' | 'done';
    created_at: string;
}

const token          = ref<string | null>(localStorage.getItem('api_token'));
const email          = ref('admin@test.com');
const password       = ref('123456');
const loginError     = ref('');
const loginLoading   = ref(false);
const tasks          = ref<Task[]>([]);
const loadingTasks   = ref(false);
const newTitle       = ref('');
const adding         = ref(false);
const addError       = ref('');
const editingId      = ref<number | null>(null);
const editTitle      = ref('');
const filter         = ref<'all' | 'pending' | 'done'>('all');

const isLoggedIn   = computed(() => !!token.value);
const pendingCount = computed(() => tasks.value.filter(t => t.status === 'pending').length);
const doneCount    = computed(() => tasks.value.filter(t => t.status === 'done').length);
const filteredTasks = computed(() => {
    if (filter.value === 'all') return tasks.value;
    return tasks.value.filter(t => t.status === filter.value);
});

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

async function login() {
    loginLoading.value = true;
    loginError.value   = '';
    try {
        const resp = await apiFetch('/login', {
            method: 'POST',
            body: JSON.stringify({ email: email.value, password: password.value }),
        });
        const t = resp.data?.token ?? resp.token ?? 'simple-admin-token-2024';
        token.value = t;
        localStorage.setItem('api_token', t);
        await fetchTasks();
    } catch (e: any) {
        loginError.value = e.message || 'Login gagal.';
    } finally {
        loginLoading.value = false;
    }
}

function logout() {
    token.value = null;
    localStorage.removeItem('api_token');
    tasks.value = [];
}

async function fetchTasks() {
    loadingTasks.value = true;
    try {
        const resp  = await apiFetch('/tasks');
        tasks.value = Array.isArray(resp.data) ? resp.data : [];
    } catch { tasks.value = []; }
    finally { loadingTasks.value = false; }
}

async function addTask() {
    const title = newTitle.value.trim();
    if (!title) return;
    adding.value   = true;
    addError.value = '';
    try {
        const resp = await apiFetch('/tasks', { method: 'POST', body: JSON.stringify({ title }) });
        tasks.value.unshift(resp.data);
        newTitle.value = '';
    } catch (e: any) {
        addError.value = e.message || 'Gagal menambahkan task.';
    } finally { adding.value = false; }
}

async function toggleStatus(task: Task) {
    const newStatus = task.status === 'pending' ? 'done' : 'pending';
    try {
        const resp = await apiFetch(`/tasks/${task.id}`, { method: 'PUT', body: JSON.stringify({ status: newStatus }) });
        const idx = tasks.value.findIndex(t => t.id === task.id);
        if (idx !== -1) tasks.value[idx] = resp.data;
    } catch {}
}

function startEdit(task: Task) { editingId.value = task.id; editTitle.value = task.title; }

async function saveEdit(task: Task) {
    const title = editTitle.value.trim();
    if (!title) return cancelEdit();
    try {
        const resp = await apiFetch(`/tasks/${task.id}`, { method: 'PUT', body: JSON.stringify({ title }) });
        const idx = tasks.value.findIndex(t => t.id === task.id);
        if (idx !== -1) tasks.value[idx] = resp.data;
    } finally { cancelEdit(); }
}

function cancelEdit() { editingId.value = null; editTitle.value = ''; }

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
    <!-- LOGIN -->
    <div v-if="!isLoggedIn" class="tm-auth-page">
        <div class="tm-auth-card">
            <div class="tm-auth-logo">
                <svg width="44" height="44" viewBox="0 0 44 44" fill="none">
                    <rect width="44" height="44" rx="12" fill="#16a34a"/>
                    <path d="M13 22l6 6 12-12" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="tm-auth-title">Task Manager</h1>
            <p class="tm-auth-sub">Masuk untuk mengelola tugas Anda</p>
            <form @submit.prevent="login" class="tm-auth-form">
                <div class="tm-field">
                    <label for="tm-email">Email</label>
                    <input id="tm-email" v-model="email" type="email" placeholder="admin@test.com" required class="tm-input" />
                </div>
                <div class="tm-field">
                    <label for="tm-password">Password</label>
                    <input id="tm-password" v-model="password" type="password" placeholder="••••••" required class="tm-input" />
                </div>
                <p v-if="loginError" class="tm-error">{{ loginError }}</p>
                <button type="submit" class="tm-btn-primary" :disabled="loginLoading">
                    <span v-if="loginLoading" class="tm-spinner"></span>
                    <span v-else>Masuk</span>
                </button>
            </form>
        </div>
    </div>

    <!-- APP -->
    <div v-else class="tm-app">
        <aside class="tm-sidebar">
            <div class="tm-brand">
                <svg width="30" height="30" viewBox="0 0 44 44" fill="none">
                    <rect width="44" height="44" rx="10" fill="#16a34a"/>
                    <path d="M13 22l6 6 12-12" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>TaskFlow</span>
            </div>

            <nav class="tm-nav">
                <button v-for="f in (['all','pending','done'] as const)" :key="f"
                    class="tm-nav-btn" :class="{ 'tm-nav-btn--active': filter === f }"
                    @click="filter = f">
                    <span class="tm-dot" :class="`tm-dot--${f}`"></span>
                    <span class="tm-nav-label">{{ f === 'all' ? 'Semua Task' : f === 'pending' ? 'Pending' : 'Selesai' }}</span>
                    <span class="tm-nav-count">{{ f === 'all' ? tasks.length : f === 'pending' ? pendingCount : doneCount }}</span>
                </button>
            </nav>

            <div class="tm-stats">
                <div class="tm-stat-row"><span>Total</span><strong>{{ tasks.length }}</strong></div>
                <div class="tm-stat-row tm-stat-row--amber"><span>Pending</span><strong>{{ pendingCount }}</strong></div>
                <div class="tm-stat-row tm-stat-row--green"><span>Selesai</span><strong>{{ doneCount }}</strong></div>
                <div class="tm-progress-track">
                    <div class="tm-progress-fill" :style="{ width: tasks.length ? (doneCount/tasks.length*100)+'%' : '0%' }"></div>
                </div>
                <p class="tm-progress-label">{{ tasks.length ? Math.round(doneCount/tasks.length*100) : 0 }}% selesai</p>
            </div>

            <button class="tm-logout" @click="logout">
                <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                </svg>
                Keluar
            </button>
        </aside>

        <main class="tm-main">
            <header class="tm-header">
                <h1 class="tm-page-title">{{ filter === 'all' ? 'Semua Task' : filter === 'pending' ? 'Task Pending' : 'Task Selesai' }}</h1>
                <p class="tm-page-sub">{{ filteredTasks.length }} task ditampilkan</p>
            </header>

            <!-- Add Task -->
            <div class="tm-add-card">
                <div class="tm-add-row">
                    <input id="tm-new-task" v-model="newTitle" @keyup.enter="addTask"
                        class="tm-input tm-add-input"
                        placeholder="Ketik judul task, lalu tekan Enter atau klik Tambah..."
                        :disabled="adding" autocomplete="off" />
                    <button id="tm-add-btn" class="tm-btn-add" @click="addTask"
                        :disabled="adding || !newTitle.trim()">
                        <span v-if="adding" class="tm-spinner tm-spinner--green"></span>
                        <template v-else>
                            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                            </svg>
                            Tambah
                        </template>
                    </button>
                </div>
                <p v-if="addError" class="tm-error" style="margin-top:.35rem">{{ addError }}</p>
            </div>

            <!-- Loading -->
            <div v-if="loadingTasks" class="tm-state">
                <div class="tm-loader"></div>
                <p class="tm-state-text">Memuat task...</p>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredTasks.length === 0" class="tm-state">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                    <circle cx="30" cy="30" r="28" stroke="#d1fae5" stroke-width="2"/>
                    <path d="M20 30h20M30 20v20" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" opacity=".5"/>
                </svg>
                <p class="tm-state-text">Belum ada task.<br>Tambahkan task pertama Anda!</p>
            </div>

            <!-- List -->
            <ul v-else class="tm-list">
                <li v-for="task in filteredTasks" :key="task.id"
                    class="tm-item" :class="{ 'tm-item--done': task.status === 'done' }">

                    <button class="tm-check" @click="toggleStatus(task)"
                        :title="task.status === 'pending' ? 'Tandai selesai' : 'Tandai pending'">
                        <span class="tm-check-circle" :class="{ 'tm-check-circle--done': task.status === 'done' }">
                            <svg v-if="task.status === 'done'" width="12" height="12" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                    </button>

                    <div class="tm-item-body">
                        <template v-if="editingId === task.id">
                            <input v-model="editTitle" class="tm-input tm-edit-input"
                                @keyup.enter="saveEdit(task)" @keyup.escape="cancelEdit" autofocus />
                        </template>
                        <template v-else>
                            <span class="tm-item-title" :class="{ 'tm-item-title--done': task.status === 'done' }">{{ task.title }}</span>
                        </template>
                    </div>

                    <span class="tm-badge" :class="task.status === 'done' ? 'tm-badge--done' : 'tm-badge--pending'">
                        {{ task.status === 'done' ? 'Selesai' : 'Pending' }}
                    </span>

                    <div class="tm-actions">
                        <template v-if="editingId === task.id">
                            <button class="tm-icon-btn tm-icon-btn--save" @click="saveEdit(task)" title="Simpan">✓</button>
                            <button class="tm-icon-btn tm-icon-btn--cancel" @click="cancelEdit" title="Batal">✕</button>
                        </template>
                        <template v-else>
                            <button class="tm-icon-btn tm-icon-btn--edit" @click="startEdit(task)" title="Edit">
                                <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                            </button>
                            <button class="tm-icon-btn tm-icon-btn--delete" @click="removeTask(task.id)" title="Hapus">
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
/* All classes are prefixed with tm- to avoid conflicts with Tailwind */
*, *::before, *::after { box-sizing: border-box; }

/* ── Inputs ── */
.tm-input {
    -webkit-appearance: none;
    appearance: none;
    display: block;
    width: 100%;
    border: 1.5px solid #e5e7eb !important;
    border-radius: 10px;
    padding: .7rem .9rem;
    font-size: .95rem;
    font-family: inherit;
    color: #111827;
    background: #fff;
    outline: none !important;
    box-shadow: none !important;
    transition: border-color .2s;
}
.tm-input:focus       { border-color: #16a34a !important; }
.tm-input::placeholder { color: #9ca3af; }
.tm-edit-input        { padding: .35rem .6rem; font-size: .9rem; }
.tm-add-input         { flex: 1; }

/* ── Buttons — all use display:flex with !important to beat Tailwind preflight ── */
.tm-btn-primary {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: .5rem;
    width: 100%;
    padding: .8rem;
    border: none !important;
    border-radius: 10px;
    background: #16a34a !important;
    color: #fff !important;
    font-size: .95rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    outline: none !important;
    box-shadow: none !important;
    transition: background .2s, opacity .2s;
}
.tm-btn-primary:hover:not(:disabled) { background: #15803d !important; }
.tm-btn-primary:disabled { opacity: .55; cursor: not-allowed; }

.tm-btn-add {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: .4rem;
    flex-shrink: 0;
    min-width: 92px;
    padding: .7rem 1.1rem;
    border: none !important;
    border-radius: 10px;
    background: #16a34a !important;
    color: #fff !important;
    font-size: .9rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    white-space: nowrap;
    outline: none !important;
    box-shadow: none !important;
    transition: background .15s, opacity .15s;
}
.tm-btn-add:hover:not(:disabled) { background: #15803d !important; }
.tm-btn-add:disabled { opacity: .45 !important; cursor: not-allowed; }

.tm-nav-btn {
    display: flex !important;
    align-items: center !important;
    gap: .6rem;
    width: 100%;
    padding: .6rem .75rem;
    border: none !important;
    border-radius: 10px;
    background: transparent !important;
    color: #6b7280 !important;
    font-size: .875rem;
    font-weight: 500;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
    outline: none !important;
    box-shadow: none !important;
    transition: background .15s, color .15s;
}
.tm-nav-btn:hover         { background: #dcfce7 !important; color: #15803d !important; }
.tm-nav-btn--active       { background: #dcfce7 !important; color: #16a34a !important; font-weight: 600; }

.tm-check {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border: none !important;
    background: none !important;
    padding: 0 !important;
    cursor: pointer;
    outline: none !important;
    box-shadow: none !important;
}

.tm-icon-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 30px !important;
    height: 30px !important;
    padding: 0 !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px;
    background: #fff !important;
    color: #6b7280 !important;
    font-size: .75rem;
    font-family: inherit;
    cursor: pointer;
    outline: none !important;
    box-shadow: none !important;
    transition: background .15s, color .15s, border-color .15s;
}
.tm-icon-btn--edit:hover   { background: #eff6ff !important; color: #2563eb !important; border-color: #bfdbfe !important; }
.tm-icon-btn--delete:hover { background: #fef2f2 !important; color: #ef4444 !important; border-color: #fecaca !important; }
.tm-icon-btn--save:hover   { background: #dcfce7 !important; color: #16a34a !important; border-color: #86efac !important; }
.tm-icon-btn--cancel:hover { background: #fef2f2 !important; color: #ef4444 !important; border-color: #fecaca !important; }

.tm-logout {
    display: flex !important;
    align-items: center !important;
    gap: .5rem;
    margin-top: 1rem;
    width: 100%;
    padding: .6rem .75rem;
    border: 1px solid #e5e7eb !important;
    border-radius: 10px;
    background: transparent !important;
    color: #6b7280 !important;
    font-size: .85rem;
    font-family: inherit;
    cursor: pointer;
    outline: none !important;
    box-shadow: none !important;
    transition: background .15s, color .15s, border-color .15s;
}
.tm-logout:hover { background: #fef2f2 !important; color: #ef4444 !important; border-color: #fecaca !important; }

/* ── Auth page ── */
.tm-auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    padding: 1.5rem;
}
.tm-auth-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 2.5rem 2rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 4px 24px rgba(0,0,0,.06);
}
.tm-auth-logo  { display: flex; justify-content: center; margin-bottom: 1.25rem; }
.tm-auth-title { text-align: center; font-size: 1.6rem; font-weight: 700; color: #111827; letter-spacing: -.02em; margin: 0; }
.tm-auth-sub   { text-align: center; color: #6b7280; font-size: .875rem; margin: .35rem 0 1.75rem; }
.tm-auth-form  { display: flex; flex-direction: column; gap: 1rem; }
.tm-field      { display: flex; flex-direction: column; gap: .35rem; }
.tm-field label { font-size: .8rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; }

/* ── App shell ── */
.tm-app {
    display: flex;
    min-height: 100vh;
    background: #f9fafb;
    font-family: 'Inter', system-ui, sans-serif;
    color: #111827;
}

/* ── Sidebar ── */
.tm-sidebar {
    width: 248px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    padding: 1.5rem 1rem;
}
.tm-brand {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: 0 .5rem;
    margin-bottom: 1.75rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
}
.tm-nav { display: flex; flex-direction: column; gap: .2rem; }

.tm-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    display: inline-block;
    border: none !important;
    padding: 0 !important;
    outline: none !important;
    box-shadow: none !important;
}
.tm-dot--all     { background: #6b7280; }
.tm-dot--pending { background: #f59e0b; }
.tm-dot--done    { background: #16a34a; }
.tm-nav-label    { flex: 1; }
.tm-nav-count {
    font-size: .72rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb !important;
    padding: .1rem .4rem;
    border-radius: 20px;
    color: #6b7280;
    display: inline-block;
    box-shadow: none !important;
    outline: none !important;
}
.tm-nav-btn--active .tm-nav-count { background: #fff; border-color: #86efac !important; color: #15803d; }

.tm-stats {
    margin-top: auto;
    border-top: 1px solid #e5e7eb;
    padding-top: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: .4rem;
}
.tm-stat-row {
    display: flex;
    justify-content: space-between;
    font-size: .8rem;
    color: #6b7280;
}
.tm-stat-row strong { font-weight: 600; color: #111827; }
.tm-stat-row--amber strong, .tm-stat-row--amber span { color: #f59e0b; }
.tm-stat-row--green strong, .tm-stat-row--green span { color: #16a34a; }

.tm-progress-track {
    height: 6px;
    background: #dcfce7;
    border-radius: 10px;
    overflow: hidden;
    margin-top: .35rem;
    border: none !important;
}
.tm-progress-fill {
    height: 100%;
    background: #16a34a;
    border-radius: 10px;
    transition: width .4s ease;
}
.tm-progress-label { font-size: .75rem; color: #6b7280; text-align: right; }

/* ── Main ── */
.tm-main       { flex: 1; overflow-y: auto; padding: 2rem; max-width: 820px; }
.tm-header     { margin-bottom: 1.5rem; }
.tm-page-title { font-size: 1.5rem; font-weight: 700; color: #111827; letter-spacing: -.02em; margin: 0; }
.tm-page-sub   { font-size: .85rem; color: #6b7280; margin-top: .2rem; }

/* ── Add Task Card ── */
.tm-add-card {
    background: #fff;
    border: 1.5px solid #86efac !important;
    border-radius: 14px;
    padding: 1rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 8px rgba(22,163,74,.08) !important;
}
.tm-add-row {
    display: flex;
    gap: .75rem;
    align-items: center;
}

/* ── States ── */
.tm-state      { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 4rem 0; }
.tm-state-text { color: #6b7280; text-align: center; line-height: 1.7; font-size: .9rem; }
.tm-loader {
    width: 36px; height: 36px;
    border: 3px solid #dcfce7;
    border-top-color: #16a34a;
    border-radius: 50%;
    animation: tm-spin .8s linear infinite;
}

/* ── Task list ── */
.tm-list { list-style: none; display: flex; flex-direction: column; gap: .5rem; padding: 0; margin: 0; }
.tm-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    background: #fff;
    border: 1px solid #e5e7eb !important;
    border-radius: 12px;
    padding: .875rem 1rem;
    box-shadow: none !important;
    transition: border-color .2s;
    animation: tm-slide .2s ease;
}
.tm-item:hover       { border-color: #86efac !important; }
.tm-item--done       { background: #f9fafb; }

.tm-check-circle {
    width: 20px; height: 20px;
    border-radius: 50%;
    border: 2px solid #e5e7eb !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0;
    background: #fff;
    box-shadow: none !important;
    transition: background .15s, border-color .15s;
}
.tm-check-circle--done { background: #16a34a !important; border-color: #16a34a !important; color: #fff; }
.tm-check:hover .tm-check-circle:not(.tm-check-circle--done) { border-color: #16a34a !important; }

.tm-item-body  { flex: 1; min-width: 0; }
.tm-item-title { font-size: .9rem; color: #111827; word-break: break-word; display: block; }
.tm-item-title--done { text-decoration: line-through; color: #6b7280; }

.tm-badge {
    flex-shrink: 0;
    font-size: .7rem;
    font-weight: 600;
    padding: .2rem .6rem;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: .04em;
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
    display: inline-block;
}
.tm-badge--pending { background: #fef3c7; color: #92400e; }
.tm-badge--done    { background: #dcfce7; color: #15803d; }

.tm-actions { display: flex; gap: .3rem; flex-shrink: 0; }

/* ── Spinners ── */
.tm-spinner {
    display: inline-block !important;
    flex-shrink: 0;
    width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: tm-spin .7s linear infinite;
}
.tm-spinner--green { border-color: #dcfce7; border-top-color: #16a34a; }

.tm-error { font-size: .82rem; color: #ef4444; }

@keyframes tm-spin  { to { transform: rotate(360deg); } }
@keyframes tm-slide { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }

@media (max-width: 640px) {
    .tm-app     { flex-direction: column; }
    .tm-sidebar { width: 100%; flex-direction: row; flex-wrap: wrap; padding: .75rem; }
    .tm-nav     { flex-direction: row; flex: 1; gap: .25rem; }
    .tm-stats, .tm-logout { margin-top: .5rem; }
    .tm-main    { padding: 1rem; }
}
</style>
