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
const attempts = ref([])

onMounted(async () => {
    try {
        attempts.value = await store.fetchAttemptsForBlock(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Attempts could not be loaded.'
    } finally {
        loading.value = false
    }
})

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-card-alt text-muted'
}

function formatTime(seconds) {
    if (!seconds) return '—'
    const minutes = Math.floor(seconds / 60)
    const remaining = seconds % 60
    return `${minutes}m ${remaining}s`
}

function openAttempt(attempt) {
    router.push(`/tutor/attempts/${attempt.id}`)
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="router.back()">&larr; Back</button>
        <h1 class="text-body mt-1 text-2xl font-bold">Student Attempts</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-else-if="attempts.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-muted">No students have attempted this activity yet.</p>
        </div>

        <div v-else class="mt-8 overflow-x-auto">
            <table class="w-full min-w-max rounded-2xl bg-card shadow-elevated">
                <thead>
                    <tr class="border-b border-border text-left text-xs font-semibold tracking-wide text-muted uppercase">
                        <th class="px-5 py-3">Student</th>
                        <th class="px-5 py-3">Attempt</th>
                        <th class="px-5 py-3">Score</th>
                        <th class="px-5 py-3">Percentage</th>
                        <th class="px-5 py-3">Pass/Fail</th>
                        <th class="px-5 py-3">Time Taken</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Started</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="attempt in attempts"
                        :key="attempt.id"
                        class="cursor-pointer border-b border-border text-sm last:border-b-0 hover:brightness-95"
                        @click="openAttempt(attempt)"
                    >
                        <td class="px-5 py-3 font-medium text-body">{{ attempt.student.first_name }} {{ attempt.student.last_name }}</td>
                        <td class="px-5 py-3 text-muted">#{{ attempt.attempt_number }}</td>
                        <td class="px-5 py-3 text-muted">
                            <span v-if="attempt.raw_score !== null">{{ attempt.raw_score }} / {{ attempt.max_score }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-5 py-3 text-muted">{{ attempt.percentage !== null ? `${attempt.percentage}%` : '—' }}</td>
                        <td class="px-5 py-3">
                            <span
                                v-if="attempt.passed !== null"
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="attempt.passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ attempt.passed ? 'Passed' : 'Failed' }}
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="px-5 py-3 text-muted">{{ formatTime(attempt.time_taken_seconds) }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(attempt.status)">
                                {{ statusLabel(attempt.status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-muted">{{ new Date(attempt.started_at).toLocaleString() }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
