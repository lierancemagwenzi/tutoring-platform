<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSubmissionsStore } from '../../stores/submissions'

const STATUS_LABELS = {
    submitted: 'Submitted',
    under_review: 'Under Review',
    returned: 'Returned for Revision',
    graded: 'Graded',
}

const STATUS_CLASSES = {
    submitted: 'bg-amber-100 text-amber-700',
    under_review: 'bg-amber-100 text-amber-700',
    returned: 'bg-red-100 text-red-700',
    graded: 'bg-green-100 text-green-700',
}

const route = useRoute()
const router = useRouter()
const store = useSubmissionsStore()

const loading = ref(true)
const errorMessage = ref('')
const submissions = ref([])

onMounted(async () => {
    try {
        submissions.value = await store.fetchSubmissionsForBlock(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Submissions could not be loaded.'
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

function openSubmission(submission) {
    router.push(`/tutor/submissions/${submission.id}`)
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="router.back()">&larr; Back</button>
        <h1 class="text-ink mt-1 text-2xl font-bold">Student Submissions</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-else-if="submissions.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-gray-500">No students have submitted work for this activity yet.</p>
        </div>

        <div v-else class="mt-8 space-y-3">
            <button
                v-for="submission in submissions"
                :key="submission.id"
                type="button"
                class="flex w-full items-center gap-4 rounded-2xl bg-white p-5 text-left shadow-sm transition hover:bg-gray-50"
                @click="openSubmission(submission)"
            >
                <div class="min-w-0 flex-1">
                    <p class="text-ink font-bold">{{ submission.student.first_name }} {{ submission.student.last_name }}</p>
                    <p class="mt-1 text-sm text-gray-500">
                        Attempt {{ submission.attempt_number }} &middot; Submitted {{ new Date(submission.submitted_at).toLocaleString() }}
                    </p>
                </div>
                <p v-if="submission.status === 'graded'" class="text-sm font-semibold text-gray-700">
                    {{ submission.score }}<span v-if="submission.max_score"> / {{ submission.max_score }}</span>
                </p>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(submission.status)">
                    {{ statusLabel(submission.status) }}
                </span>
            </button>
        </div>
    </div>
</template>
