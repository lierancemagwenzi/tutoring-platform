<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminSubjectsStore } from '../../stores/adminSubjects'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'
import Pagination from '../../components/common/Pagination.vue'
import Modal from '../../components/common/Modal.vue'

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'archived', label: 'Archived' },
]

const store = useAdminSubjectsStore()
const loading = ref(true)
const errorMessage = ref('')
const actioningId = ref(null)

const filters = reactive({ search: '', status: '' })

async function applyFilters(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchSubjects({ search: filters.search, status: filters.status, page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading subjects.'
    } finally {
        loading.value = false
    }
}

onMounted(() => applyFilters())

let searchTimeout = null
function onSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(1), 300)
}

function selectStatus(status) {
    filters.status = status
    applyFilters(1)
}

async function setStatus(subject, action, label) {
    if (!confirm(`${label} "${subject.name}"?`)) {
        return
    }

    actioningId.value = subject.id
    errorMessage.value = ''
    try {
        if (action === 'activate') await store.activateSubject(subject.id)
        if (action === 'deactivate') await store.deactivateSubject(subject.id)
        if (action === 'archive') await store.archiveSubject(subject.id)
        await applyFilters(store.meta.current_page)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong.'
    } finally {
        actioningId.value = null
    }
}

// Create subject
const showCreate = ref(false)
const createForm = reactive({ name: '', description: '' })
const creating = ref(false)
const createError = ref('')

async function createSubject() {
    creating.value = true
    createError.value = ''
    try {
        await store.createSubject({ name: createForm.name, description: createForm.description || null })
        showCreate.value = false
        createForm.name = ''
        createForm.description = ''
        await applyFilters(1)
    } catch (error) {
        const errors = error.response?.data?.errors
        createError.value = errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? 'Something went wrong.')
    } finally {
        creating.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <h1 class="text-ink text-2xl font-bold">Subjects</h1>
            <button
                type="button"
                class="bg-amber rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-95"
                @click="showCreate = true"
            >
                New Subject
            </button>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-4">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search subjects…"
                class="focus:border-accent w-64 rounded-xl border border-gray-300 px-4 py-2.5 text-sm text-gray-900 outline-none"
                @input="onSearchInput"
            />
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in STATUS_TABS"
                    :key="tab.value"
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition"
                    :class="filters.status === tab.value ? 'bg-amber text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                    @click="selectStatus(tab.value)"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.subjects.length === 0" class="mt-16 text-center text-gray-500">No subjects found.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="subject in store.subjects" :key="subject.id" class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <router-link :to="`/admin/subjects/${subject.id}`" class="text-ink font-bold hover:underline">
                            {{ subject.name }}
                        </router-link>
                        <p class="mt-1 text-sm text-gray-500">{{ subject.description || 'No description.' }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>{{ subject.approved_tutors_count }} approved tutors</span>
                            <span>{{ subject.pending_requests_count }} pending requests</span>
                            <span>{{ subject.services_count }} services</span>
                            <span>{{ subject.self_paced_courses_count }} courses</span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(subject.status)">
                        {{ adminStatusLabel(subject.status) }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4">
                    <button
                        v-if="subject.status !== 'active'"
                        type="button"
                        :disabled="actioningId === subject.id"
                        class="text-sm font-semibold text-green-600 disabled:opacity-40"
                        @click="setStatus(subject, 'activate', 'Activate')"
                    >
                        Activate
                    </button>
                    <button
                        v-if="subject.status === 'active'"
                        type="button"
                        :disabled="actioningId === subject.id"
                        class="text-sm font-semibold text-gray-600 disabled:opacity-40"
                        @click="setStatus(subject, 'deactivate', 'Deactivate')"
                    >
                        Deactivate
                    </button>
                    <button
                        v-if="subject.status !== 'archived'"
                        type="button"
                        :disabled="actioningId === subject.id"
                        class="text-sm font-semibold text-red-600 disabled:opacity-40"
                        @click="setStatus(subject, 'archive', 'Archive')"
                    >
                        Archive
                    </button>
                </div>
            </div>
        </div>

        <Pagination :meta="store.meta" @change="applyFilters" />

        <Modal v-model="showCreate" title="New Subject">
            <div class="space-y-4">
                <p v-if="createError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ createError }}</p>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="subject-name">Name</label>
                    <input
                        id="subject-name"
                        v-model="createForm.name"
                        type="text"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                    />
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="subject-description">Description</label>
                    <textarea
                        id="subject-description"
                        v-model="createForm.description"
                        rows="3"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                    />
                </div>
            </div>

            <template #footer>
                <button
                    type="button"
                    class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    @click="showCreate = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="creating || !createForm.name"
                    class="bg-amber rounded-full px-6 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="createSubject"
                >
                    {{ creating ? 'Creating…' : 'Create Subject' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
