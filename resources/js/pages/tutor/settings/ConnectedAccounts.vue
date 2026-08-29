<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import { useTutorSettingsStore } from '../../../stores/tutorSettings'
import SettingsTabs from '../../../components/tutor/SettingsTabs.vue'

// Adding a future provider (Microsoft, Zoom, LiveKit) is one more entry
// here — nothing else on this page needs to change.
const PROVIDERS = [{ key: 'google', label: 'Google' }]

const route = useRoute()
const router = useRouter()
const store = useTutorSettingsStore()

const loading = ref(true)
const connecting = ref(null)
const disconnecting = ref(null)
const banner = ref(null)
const errorMessage = ref('')

function connectedAccountFor(providerKey) {
    return store.connectedAccounts.find((account) => account.provider === providerKey) ?? null
}

async function load() {
    loading.value = true
    try {
        await store.fetchConnectedAccounts()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    if (route.query.status) {
        banner.value = {
            type: route.query.status === 'connected' ? 'success' : 'error',
            message:
                route.query.status === 'connected'
                    ? 'Account connected successfully.'
                    : (route.query.message ?? 'The connection could not be completed.'),
        }
        router.replace({ path: route.path })
    }

    await load()
})

async function connect(providerKey) {
    connecting.value = providerKey
    errorMessage.value = ''

    try {
        const url = await store.getProviderRedirectUrl(providerKey)
        window.location.href = url
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Could not start the connection. Please try again.'
        connecting.value = null
    }
}

async function disconnect(account) {
    disconnecting.value = account.id
    errorMessage.value = ''

    try {
        await store.disconnect(account.id)
        banner.value = { type: 'success', message: 'Account disconnected.' }
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Could not disconnect this account. Please try again.'
    } finally {
        disconnecting.value = null
    }
}

const providerCards = computed(() =>
    PROVIDERS.map((provider) => ({
        ...provider,
        account: connectedAccountFor(provider.key),
    })),
)
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Settings</h1>

        <div class="mt-6">
            <SettingsTabs />
        </div>

        <div class="mt-6 max-w-2xl">
            <p
                v-if="banner"
                class="mb-4 rounded-lg px-4 py-3 text-sm"
                :class="banner.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'"
            >
                {{ banner.message }}
            </p>
            <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

            <div v-if="loading" class="flex justify-center py-16">
                <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
            </div>

            <div v-else class="space-y-4">
                <div v-for="card in providerCards" :key="card.key" class="rounded-2xl bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="bg-ink flex h-10 w-10 items-center justify-center rounded-full font-bold text-white">
                                {{ card.label.charAt(0) }}
                            </span>
                            <div>
                                <p class="text-ink font-bold">{{ card.label }}</p>
                                <p v-if="card.account" class="flex items-center gap-1 text-sm text-green-600">
                                    <CheckCircleIcon class="h-4 w-4" /> Connected
                                </p>
                                <p v-else class="flex items-center gap-1 text-sm text-gray-500">
                                    <XCircleIcon class="h-4 w-4" /> Not Connected
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="!card.account"
                            type="button"
                            :disabled="connecting === card.key"
                            class="bg-amber rounded-full px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="connect(card.key)"
                        >
                            {{ connecting === card.key ? 'Redirecting…' : `Connect ${card.label}` }}
                        </button>
                        <button
                            v-else
                            type="button"
                            :disabled="disconnecting === card.account.id"
                            class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="disconnect(card.account)"
                        >
                            {{ disconnecting === card.account.id ? 'Disconnecting…' : 'Disconnect' }}
                        </button>
                    </div>

                    <div v-if="card.account" class="mt-4 border-t border-gray-100 pt-4 text-sm text-gray-500">
                        <p>{{ card.account.email }}</p>
                        <p class="mt-1 text-xs text-gray-400">
                            Connected on {{ new Date(card.account.connected_at).toLocaleDateString() }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
