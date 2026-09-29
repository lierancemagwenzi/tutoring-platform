<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminGradesStore } from '../../stores/adminGrades'
import Modal from '../../components/common/Modal.vue'

const store = useAdminGradesStore()
const loading = ref(true)
const errorMessage = ref('')
const actioningId = ref(null)

const inputClass = 'focus:border-accent mt-1.5 w-full rounded-xl border border-border px-3.5 py-2.5 text-sm text-body outline-none'

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchGrades()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading grades.'
    } finally {
        loading.value = false
    }
}

onMounted(load)

async function toggleActive(grade) {
    const label = grade.is_active ? 'Deactivate' : 'Activate'
    if (!confirm(`${label} "${grade.name}"?`)) {
        return
    }

    actioningId.value = grade.id
    errorMessage.value = ''
    try {
        if (grade.is_active) await store.deactivateGrade(grade.id)
        else await store.activateGrade(grade.id)
        await load()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        actioningId.value = null
    }
}

// Shared create/edit modal — editingId null means "new grade".
const showForm = ref(false)
const editingId = ref(null)
const form = reactive({ name: '', level: '', description: '' })
const saving = ref(false)
const formError = ref('')

function openCreate() {
    editingId.value = null
    const nextLevel = store.grades.length ? Math.max(...store.grades.map((grade) => grade.level)) + 1 : 1
    Object.assign(form, { name: '', level: String(nextLevel), description: '' })
    formError.value = ''
    showForm.value = true
}

function openEdit(grade) {
    editingId.value = grade.id
    Object.assign(form, { name: grade.name, level: String(grade.level), description: grade.description ?? '' })
    formError.value = ''
    showForm.value = true
}

async function saveGrade() {
    saving.value = true
    formError.value = ''
    const payload = { name: form.name, level: Number(form.level), description: form.description || null }
    try {
        if (editingId.value) await store.updateGrade(editingId.value, payload)
        else await store.createGrade(payload)
        showForm.value = false
        await load()
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-body text-2xl font-bold">Grades</h1>
                <p class="mt-1 text-sm text-muted">Grades appear in service, course and marketplace pickers, ordered by level.</p>
            </div>
            <button
                type="button"
                class="bg-amber shrink-0 rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95"
                @click="openCreate"
            >
                New Grade
            </button>
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.grades.length === 0" class="mt-16 text-center text-muted">No grades yet.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="grade in store.grades" :key="grade.id" class="rounded-2xl bg-card p-5 shadow-elevated">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-body font-bold">{{ grade.name }}</p>
                        <p class="mt-1 text-sm text-muted">{{ grade.description || 'No description.' }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span>Level {{ grade.level }}</span>
                            <span>{{ grade.services_count }} services</span>
                            <span>{{ grade.self_paced_courses_count }} courses</span>
                        </div>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                        :class="grade.is_active ? 'bg-green-100 text-green-700' : 'bg-card-alt text-muted'"
                    >
                        {{ grade.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-border pt-4">
                    <button type="button" class="text-accent text-sm font-semibold" @click="openEdit(grade)">Edit</button>
                    <button
                        type="button"
                        :disabled="actioningId === grade.id"
                        class="text-sm font-semibold disabled:opacity-40"
                        :class="grade.is_active ? 'text-muted' : 'text-green-600'"
                        @click="toggleActive(grade)"
                    >
                        {{ grade.is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        </div>

        <Modal v-model="showForm" :title="editingId ? 'Edit Grade' : 'New Grade'">
            <div class="space-y-4">
                <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>
                <div>
                    <label class="block text-sm font-semibold text-body" for="grade-name">Name</label>
                    <input id="grade-name" v-model="form.name" type="text" placeholder="e.g. Grade 8" :class="inputClass" />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-body" for="grade-level">Level</label>
                    <input id="grade-level" v-model="form.level" type="number" min="0" max="255" :class="inputClass" />
                    <p class="mt-1 text-xs text-muted">Controls sort order — lower levels are listed first. Each grade needs its own level.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-body" for="grade-description">Description</label>
                    <textarea id="grade-description" v-model="form.description" rows="3" :class="inputClass" />
                </div>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="rounded-full border border-border px-4 py-2 text-sm font-semibold text-body hover:brightness-95"
                    @click="showForm = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="saving || !form.name || form.level === ''"
                    class="bg-amber rounded-full px-6 py-2 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="saveGrade"
                >
                    {{ saving ? 'Saving…' : editingId ? 'Save Changes' : 'Create Grade' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
