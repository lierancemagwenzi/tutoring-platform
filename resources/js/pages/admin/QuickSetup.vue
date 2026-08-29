<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAdminQuickSetupStore } from '../../stores/adminQuickSetup'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const store = useAdminQuickSetupStore()
const loading = ref(true)
const errorMessage = ref('')
const testEmailSending = ref(false)
const testEmailResult = ref(null)

onMounted(load)

async function load() {
    loading.value = true
    try {
        await store.fetchChecklist()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading Quick Setup.'
    } finally {
        loading.value = false
    }
}

const requiredItems = computed(() => store.checklist.filter((item) => item.required))
const optionalItems = computed(() => store.checklist.filter((item) => !item.required))

// Settings sub-paths (e.g. /admin/settings/email) map to the single
// Settings page with a `tab` query param instead of a dedicated route —
// Settings is one fetched/patched payload, not several routed pages.
const KNOWN_TOP_LEVEL_LINKS = ['/admin/subjects', '/admin/integrations', '/admin/settings']

function resolveAction(link) {
    if (!link) {
        return null
    }
    // Payments has no editable settings of its own — PayFast is env-only
    // and its status lives on the Integrations page, not a Settings tab.
    if (link === '/admin/settings/payments') {
        return { path: '/admin/integrations' }
    }
    if (link.startsWith('/admin/settings/')) {
        return { path: '/admin/settings', query: { tab: link.replace('/admin/settings/', '') } }
    }
    if (KNOWN_TOP_LEVEL_LINKS.includes(link)) {
        return { path: link }
    }
    return null
}

async function sendTestEmail() {
    testEmailSending.value = true
    testEmailResult.value = null
    try {
        const data = await store.sendTestEmail()
        testEmailResult.value = { success: true, message: data.message }
    } catch (error) {
        testEmailResult.value = { success: false, message: error.response?.data?.message ?? 'Failed to send test email.' }
    } finally {
        testEmailSending.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Quick Setup</h1>
        <p class="mt-1 text-sm text-gray-500">Everything required before the platform is ready to operate.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else>
            <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="bg-amber h-full rounded-full transition-all"
                        :style="{ width: `${(store.progress.completed / Math.max(store.progress.total, 1)) * 100}%` }"
                    />
                </div>
                <p class="mt-2 text-sm text-gray-600">{{ store.progress.completed }} / {{ store.progress.total }} completed</p>
                <p class="mt-2 text-sm font-semibold" :class="store.progress.ready_for_production ? 'text-green-700' : 'text-amber-700'">
                    {{
                        store.progress.ready_for_production
                            ? 'Ready for production.'
                            : `${store.progress.required_total - store.progress.required_completed} required configuration items need attention.`
                    }}
                </p>
            </section>

            <section class="mt-6">
                <h2 class="text-ink font-bold">Required</h2>
                <div class="mt-3 space-y-3">
                    <div v-for="item in requiredItems" :key="item.key" class="rounded-2xl bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-ink font-semibold">{{ item.name }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ item.description }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(item.status)">
                                {{ adminStatusLabel(item.status) }}
                            </span>
                        </div>

                        <div class="mt-3 flex items-center gap-4">
                            <router-link v-if="resolveAction(item.action_link)" :to="resolveAction(item.action_link)" class="text-accent text-sm font-semibold">
                                {{ item.action_label }}
                            </router-link>
                            <span v-else class="text-sm text-gray-400">{{ item.action_label }}</span>

                            <template v-if="item.key === 'email'">
                                <button
                                    type="button"
                                    :disabled="testEmailSending"
                                    class="text-sm font-semibold text-gray-700 hover:text-gray-900 disabled:opacity-40"
                                    @click="sendTestEmail"
                                >
                                    {{ testEmailSending ? 'Sending…' : 'Send Test Email' }}
                                </button>
                            </template>
                        </div>

                        <p
                            v-if="item.key === 'email' && testEmailResult"
                            class="mt-2 text-sm"
                            :class="testEmailResult.success ? 'text-green-700' : 'text-red-600'"
                        >
                            {{ testEmailResult.message }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="mt-6">
                <h2 class="text-ink font-bold">Optional</h2>
                <div class="mt-3 space-y-3">
                    <div v-for="item in optionalItems" :key="item.key" class="rounded-2xl bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-ink font-semibold">{{ item.name }}</p>
                                <p class="mt-1 text-sm text-gray-500">{{ item.description }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(item.status)">
                                {{ adminStatusLabel(item.status) }}
                            </span>
                        </div>
                        <router-link
                            v-if="resolveAction(item.action_link)"
                            :to="resolveAction(item.action_link)"
                            class="text-accent mt-3 inline-block text-sm font-semibold"
                        >
                            {{ item.action_label }}
                        </router-link>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
