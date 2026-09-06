<script setup>
import { onMounted, ref } from 'vue'
import { useAdminActivityLogStore } from '../../stores/adminActivityLog'
import { adminStatusLabel } from '../../utils/adminStatusBadge'
import Pagination from '../../components/common/Pagination.vue'

const store = useAdminActivityLogStore()
const loading = ref(true)
const errorMessage = ref('')

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchList({ page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading the activity log.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Activity Log</h1>
        <p class="mt-1 text-sm text-muted">Every administrative action taken on the platform, most recent first.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.logs.length === 0" class="mt-16 text-center text-muted">No activity recorded yet.</div>

        <div v-else class="mt-6 space-y-3">
            <div v-for="log in store.logs" :key="log.id" class="rounded-2xl bg-card p-5 shadow-elevated">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-body text-sm">{{ log.description }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                            <span v-if="log.actor" class="font-medium text-muted">{{ log.actor.name }}</span>
                            <span>{{ log.subject_type }} #{{ log.subject_id }}</span>
                            <span>{{ log.created_at.slice(0, 19).replace('T', ' ') }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full bg-card-alt px-2.5 py-1 text-xs font-semibold text-muted">
                        {{ adminStatusLabel(log.action.split('.').pop()) }}
                    </span>
                </div>
            </div>
        </div>

        <Pagination :meta="store.meta" @change="load" />
    </div>
</template>
