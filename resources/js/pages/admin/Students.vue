<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminStudentsStore } from '../../stores/adminStudents'
import Pagination from '../../components/common/Pagination.vue'

const store = useAdminStudentsStore()
const loading = ref(true)
const errorMessage = ref('')

const filters = reactive({ search: '' })

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchStudents({ search: filters.search, page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading students.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

let searchTimeout = null
function onSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => load(1), 300)
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Students</h1>
        <p class="mt-1 text-sm text-muted">Every student on the platform.</p>

        <input
            v-model="filters.search"
            type="text"
            placeholder="Search students…"
            class="focus:border-accent mt-6 w-64 rounded-xl border border-border px-4 py-2.5 text-sm text-body outline-none"
            @input="onSearchInput"
        />

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.students.length === 0" class="mt-16 text-center text-muted">No students found.</div>

        <div v-else class="mt-6 space-y-3">
            <router-link
                v-for="student in store.students"
                :key="student.id"
                :to="{ name: 'admin.students.show', params: { id: student.id } }"
                class="block rounded-2xl bg-card p-5 shadow-elevated transition hover:brightness-95"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-body font-bold">{{ student.name }}</p>
                        <p class="text-sm text-muted">{{ student.email }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span>Registered {{ student.registered_at?.slice(0, 10) }}</span>
                            <span>{{ student.email_verified ? 'Email verified' : 'Email not verified' }}</span>
                            <span>{{ student.enrollments_count }} enrollments</span>
                            <span>{{ student.bookings_count }} bookings</span>
                        </div>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold"
                        :class="student.disabled ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                    >
                        {{ student.disabled ? 'Disabled' : 'Active' }}
                    </span>
                </div>
            </router-link>
        </div>

        <Pagination :meta="store.meta" @change="load" />
    </div>
</template>
