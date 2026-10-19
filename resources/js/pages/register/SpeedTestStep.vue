<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/auth'
import api from '../../services/api'
import SpeedTestPanel from '../../components/network/SpeedTestPanel.vue'
import { DASHBOARD_BY_ROLE } from '../../router/dashboardByRole'

// The mandatory tutor speed test. Shared by two routes: the registration
// wizard step straight after password creation (/register/speed-test,
// meta.afterPass = 'otp') and the standalone page an applicant who
// hasn't passed yet is redirected to on login (/speed-test,
// meta.afterPass = 'dashboard'). The browser measures; the server decides.

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const requirements = ref(null)
const verdict = ref(null)
const submitting = ref(false)
const errorMessage = ref('')

const alreadyPassed = computed(() => auth.user?.tutor_profile?.speed_test_passed === true)
const passed = computed(() => verdict.value?.passed === true || (verdict.value === null && alreadyPassed.value))

const requirementsSummary = computed(() => {
    const r = requirements.value
    if (!r) return ''
    return `at least ${r.min_download_mbps} Mbps download, ${r.min_upload_mbps} Mbps upload, and a response time of ${r.max_latency_ms} ms or less`
})

async function handleFinished(result) {
    submitting.value = true
    errorMessage.value = ''

    try {
        verdict.value = await auth.submitSpeedTest({
            download_mbps: result.metrics.download,
            upload_mbps: result.metrics.upload,
            latency_ms: result.metrics.latency,
            jitter_ms: result.metrics.jitter,
        })
    } catch (error) {
        errorMessage.value =
            error.response?.status === 422
                ? "We couldn't measure every part of your connection. Please test again."
                : (error.response?.data?.message ?? 'Something went wrong saving your result. Please test again.')
    } finally {
        submitting.value = false
    }
}

function handleRunning(isRunning) {
    if (isRunning) {
        verdict.value = null
        errorMessage.value = ''
    }
}

function continueRegistration() {
    if (route.meta.afterPass === 'otp') {
        router.push({ name: 'register.otp' })
    } else {
        router.push(DASHBOARD_BY_ROLE[auth.user?.role] ?? '/login')
    }
}

async function logOut() {
    await auth.logout()
    router.push('/login')
}

onMounted(async () => {
    try {
        const { data } = await api.get('/tutor/speed-test')
        requirements.value = data.requirements
    } catch {
        // The requirements line is a nicety — the server still checks the result.
    }
})
</script>

<template>
    <div class="w-full max-w-md">
        <div class="text-center">
            <h1 class="text-body text-3xl font-bold">Check your internet connection</h1>
            <p class="text-muted mt-2">
                You'll teach live video sessions, so a reliable connection is required to become a tutor.
                <template v-if="requirementsSummary">You'll need {{ requirementsSummary }}.</template>
            </p>
        </div>

        <div class="bg-card shadow-elevated mt-8 rounded-2xl p-6">
            <SpeedTestPanel :initial-result="null" :show-health="false" @running="handleRunning" @finished="handleFinished" />
        </div>

        <div v-if="submitting" class="text-muted mt-6 text-center text-sm" role="status">Checking your result…</div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-else-if="passed" class="mt-6 flex items-start gap-3 rounded-2xl bg-green-100 p-4 text-green-700" role="status">
            <CheckCircleIcon class="mt-0.5 h-6 w-6 shrink-0" />
            <div>
                <p class="font-bold">Your connection meets the requirements</p>
                <p class="text-sm">You can continue with your registration.</p>
            </div>
        </div>

        <div v-else-if="verdict" class="mt-6 flex items-start gap-3 rounded-2xl bg-red-100 p-4 text-red-700" role="alert">
            <XCircleIcon class="mt-0.5 h-6 w-6 shrink-0" />
            <div class="min-w-0">
                <p class="font-bold">Your connection doesn't meet the requirements yet</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                    <li v-for="failure in verdict.failures" :key="failure">{{ failure }}</li>
                </ul>
                <p class="mt-2 text-sm">
                    Try the tips above, or switch to a faster network, then test again. You can come back and finish
                    registering at any time — just log in and you'll return to this step.
                </p>
            </div>
        </div>

        <button
            type="button"
            :disabled="!passed || submitting"
            class="bg-amber mt-8 w-full rounded-full py-3.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            @click="continueRegistration"
        >
            Continue
        </button>

        <p class="mt-6 text-center">
            <button type="button" class="text-accent text-sm font-semibold underline" @click="logOut">Log out and finish later</button>
        </p>
    </div>
</template>
