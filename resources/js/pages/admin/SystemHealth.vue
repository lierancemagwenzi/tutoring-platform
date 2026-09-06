<script setup>
import { onMounted, ref } from 'vue'
import { useAdminSystemHealthStore } from '../../stores/adminSystemHealth'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const store = useAdminSystemHealthStore()
const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    loading.value = true
    try {
        await store.fetchChecks()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading system health.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">System Health</h1>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="(check, key) in store.checks" :key="key" class="rounded-2xl bg-card p-5 shadow-elevated">
                <div class="flex items-center justify-between">
                    <p class="text-body font-bold capitalize">{{ key.replace('_', ' ') }}</p>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(check.status)">
                        {{ adminStatusLabel(check.status) }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-muted">{{ check.message }}</p>
            </div>
        </div>
    </div>
</template>
