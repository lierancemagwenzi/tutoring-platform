<script setup>
import { onMounted, ref } from 'vue'
import { useAdminIntegrationsStore } from '../../stores/adminIntegrations'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const store = useAdminIntegrationsStore()
const loading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
    loading.value = true
    try {
        await store.fetchStatus()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading integrations.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Integrations</h1>
        <p class="mt-1 text-sm text-gray-500">Status is derived from actual configuration — secrets are never shown here.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="(integration, key) in store.integrations" :key="key" class="rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-ink font-bold">{{ integration.name }}</p>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(integration.status)">
                        {{ adminStatusLabel(integration.status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
