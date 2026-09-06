<script setup>
import { computed, onMounted, ref } from 'vue'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useTutorSettingsStore } from '../../../stores/tutorSettings'
import SettingsTabs from '../../../components/tutor/SettingsTabs.vue'

// Adding a future provider (LiveKit, Zoom, Teams) is one more entry here —
// `implemented: false` renders it disabled/"Coming Soon" until it has a real
// MeetingProviderContract implementation on the backend.
const PROVIDERS = [
    { key: 'google', label: 'Google Meet', implemented: true },
    { key: 'livekit', label: 'LiveKit', implemented: false },
    { key: 'zoom', label: 'Zoom', implemented: false },
    { key: 'teams', label: 'Microsoft Teams', implemented: false },
]

const store = useTutorSettingsStore()

const loading = ref(true)
const selecting = ref(null)
const errorMessage = ref('')
const successMessage = ref('')

async function load() {
    loading.value = true
    try {
        await Promise.all([store.fetchConnectedAccounts(), store.fetchMeetingProviderSettings()])
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        loading.value = false
    }
}

onMounted(load)

function isConnected(providerKey) {
    return store.connectedAccounts.some((account) => account.provider === providerKey)
}

async function select(providerKey) {
    selecting.value = providerKey
    errorMessage.value = ''
    successMessage.value = ''

    try {
        await store.selectMeetingProvider(providerKey)
        successMessage.value = 'Meeting provider updated.'
    } catch (error) {
        errorMessage.value = error.response?.data?.errors?.provider?.[0] ?? error.response?.data?.message ?? 'Could not update your meeting provider. Please try again.'
    } finally {
        selecting.value = null
    }
}

const providerCards = computed(() =>
    PROVIDERS.map((provider) => ({
        ...provider,
        connected: isConnected(provider.key),
        selected: store.meetingProvider.selected === provider.key,
    })),
)
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Settings</h1>

        <div class="mt-6">
            <SettingsTabs />
        </div>

        <div class="mt-6 max-w-2xl">
            <p v-if="successMessage" class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ successMessage }}</p>
            <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

            <div v-if="loading" class="flex justify-center py-16">
                <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
            </div>

            <div v-else class="space-y-4">
                <p class="text-muted text-sm">
                    Choose which service is used to create video meetings for your online and hybrid sessions.
                </p>

                <div
                    v-for="card in providerCards"
                    :key="card.key"
                    class="bg-card shadow-elevated rounded-2xl p-6"
                    :class="{ 'opacity-50': !card.implemented }"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="bg-ink flex h-10 w-10 items-center justify-center rounded-full font-bold text-white">
                                {{ card.label.charAt(0) }}
                            </span>
                            <div>
                                <p class="text-body font-bold">{{ card.label }}</p>
                                <p v-if="card.selected" class="flex items-center gap-1 text-sm text-green-600">
                                    <CheckCircleIcon class="h-4 w-4" /> Selected
                                </p>
                                <p v-else-if="!card.implemented" class="text-muted text-sm">Coming Soon</p>
                            </div>
                        </div>

                        <button
                            v-if="card.implemented && card.connected && !card.selected"
                            type="button"
                            :disabled="selecting === card.key"
                            class="bg-amber shadow-elevated rounded-full px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="select(card.key)"
                        >
                            {{ selecting === card.key ? 'Selecting…' : 'Select' }}
                        </button>
                    </div>

                    <div v-if="card.implemented && !card.connected" class="border-border text-muted mt-4 border-t pt-4 text-sm">
                        Connect your Google account in
                        <router-link to="/tutor/settings/connected-accounts" class="text-amber font-semibold hover:underline">
                            Connected Accounts
                        </router-link>
                        before selecting it as a meeting provider.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
