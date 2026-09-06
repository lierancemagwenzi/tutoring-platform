<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAdminSubjectsStore } from '../../stores/adminSubjects'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const route = useRoute()
const store = useAdminSubjectsStore()
const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    loading.value = true
    try {
        await store.fetchSubject(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading this subject.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="p-8">
        <router-link to="/admin/subjects" class="text-accent text-sm font-semibold">&larr; Back to Subjects</router-link>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="store.current">
            <div class="mt-4 flex items-center justify-between">
                <h1 class="text-body text-2xl font-bold">{{ store.current.name }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(store.current.status)">
                    {{ adminStatusLabel(store.current.status) }}
                </span>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-5">
                <div class="rounded-2xl bg-card p-5 shadow-elevated">
                    <p class="text-xs text-muted">Approved Tutors</p>
                    <p class="text-body text-2xl font-bold">{{ store.current.approved_tutors_count }}</p>
                </div>
                <div class="rounded-2xl bg-card p-5 shadow-elevated">
                    <p class="text-xs text-muted">Pending Requests</p>
                    <p class="text-body text-2xl font-bold">{{ store.current.pending_requests_count }}</p>
                </div>
                <div class="rounded-2xl bg-card p-5 shadow-elevated">
                    <p class="text-xs text-muted">Services</p>
                    <p class="text-body text-2xl font-bold">{{ store.current.services_count }}</p>
                </div>
                <div class="rounded-2xl bg-card p-5 shadow-elevated">
                    <p class="text-xs text-muted">Self-Paced Courses</p>
                    <p class="text-body text-2xl font-bold">{{ store.current.self_paced_courses_count }}</p>
                </div>
                <div class="rounded-2xl bg-card p-5 shadow-elevated">
                    <p class="text-xs text-muted">Active Offerings</p>
                    <p class="text-body text-2xl font-bold">{{ store.current.active_offerings_count }}</p>
                </div>
            </div>

            <section class="mt-6 rounded-2xl bg-card p-5 shadow-elevated">
                <h2 class="text-body font-bold">Approved Tutors</h2>
                <p v-if="store.current.approved_tutors.length === 0" class="mt-3 text-sm text-muted">
                    No tutors are approved to teach this subject yet.
                </p>
                <ul v-else class="mt-3 divide-y divide-border">
                    <li v-for="tutor in store.current.approved_tutors" :key="tutor.tutor_profile_id" class="flex items-center justify-between py-2.5">
                        <span class="text-body text-sm font-medium">{{ tutor.display_name }}</span>
                        <span class="text-sm text-muted">{{ tutor.email }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
