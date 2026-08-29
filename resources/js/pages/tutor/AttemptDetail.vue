<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAttemptsStore } from '../../stores/attempts'

const STATUS_LABELS = {
    started: 'Started',
    in_progress: 'In Progress',
    submitted: 'Submitted',
    completed: 'Completed',
    abandoned: 'Abandoned',
    timed_out: 'Timed Out',
}

const STATUS_CLASSES = {
    started: 'bg-gray-100 text-gray-600',
    in_progress: 'bg-amber-100 text-amber-700',
    submitted: 'bg-amber-100 text-amber-700',
    completed: 'bg-green-100 text-green-700',
    abandoned: 'bg-red-100 text-red-700',
    timed_out: 'bg-red-100 text-red-700',
}

const route = useRoute()
const router = useRouter()
const store = useAttemptsStore()

const loading = ref(true)
const errorMessage = ref('')
const attempt = ref(null)

onMounted(async () => {
    try {
        attempt.value = await store.fetchAttempt(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This attempt could not be found.'
    } finally {
        loading.value = false
    }
})

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

function formatTime(seconds) {
    if (!seconds) return '—'
    const minutes = Math.floor(seconds / 60)
    const remaining = seconds % 60
    return `${minutes}m ${remaining}s`
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="router.back()">&larr; Back</button>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="attempt">
            <div class="mt-4 flex items-center gap-3">
                <h1 class="text-ink text-2xl font-bold">{{ attempt.student.first_name }} {{ attempt.student.last_name }}</h1>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(attempt.status)">
                    {{ statusLabel(attempt.status) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500">
                Attempt {{ attempt.attempt_number }} &middot; {{ attempt.provider }} &middot; Started {{ new Date(attempt.started_at).toLocaleString() }}
            </p>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Result</h2>
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <dt class="text-gray-500">Score</dt>
                        <dd class="text-ink font-medium">
                            <span v-if="attempt.raw_score !== null">{{ attempt.raw_score }} / {{ attempt.max_score }}</span>
                            <span v-else>—</span>
                        </dd>
                        <dt class="text-gray-500">Percentage</dt>
                        <dd class="text-ink font-medium">{{ attempt.percentage !== null ? `${attempt.percentage}%` : '—' }}</dd>
                        <dt class="text-gray-500">Pass/Fail</dt>
                        <dd class="text-ink font-medium">
                            <span v-if="attempt.passed === true" class="text-green-700">Passed</span>
                            <span v-else-if="attempt.passed === false" class="text-red-700">Failed</span>
                            <span v-else>—</span>
                        </dd>
                        <dt class="text-gray-500">Time Taken</dt>
                        <dd class="text-ink font-medium">{{ formatTime(attempt.time_taken_seconds) }}</dd>
                        <dt class="text-gray-500">Completed At</dt>
                        <dd class="text-ink font-medium">{{ attempt.completed_at ? new Date(attempt.completed_at).toLocaleString() : '—' }}</dd>
                    </dl>
                </section>

                <section class="rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Provider Metadata</h2>
                    <pre class="mt-4 overflow-x-auto rounded-xl bg-gray-50 p-4 text-xs text-gray-700">{{ JSON.stringify(attempt.provider_metadata, null, 2) }}</pre>
                </section>

                <section class="rounded-2xl bg-white p-6 shadow-sm lg:col-span-2">
                    <h2 class="text-ink font-bold">Raw Provider Result</h2>
                    <pre class="mt-4 overflow-x-auto rounded-xl bg-gray-50 p-4 text-xs text-gray-700">{{ JSON.stringify(attempt.raw_provider_response, null, 2) }}</pre>
                </section>
            </div>
        </template>
    </div>
</template>
