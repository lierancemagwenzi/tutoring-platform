<script setup>
import { computed, ref } from 'vue'
import StatusPill from '../common/StatusPill.vue'
import H5pPlayerWidget from '../lms/H5pPlayerWidget.vue'
import SurveyJsPlayer from './SurveyJsPlayer.vue'
import { useSelfPacedAssessmentAttemptsStore } from '../../stores/selfPacedAssessmentAttempts'
import { useSelfPacedPlayerStore } from '../../stores/selfPacedPlayer'

// Self-paced analog of StudentAttemptPanel.vue: launch → in-progress →
// complete lifecycle, reusing H5pPlayerWidget unmodified and the new
// SurveyJsPlayer wrapper. Note: the backend exposes only the current/latest
// attempt (start/complete responses), not a full attempt-history list — no
// such endpoint exists yet, and this phase consumes the existing API as-is
// rather than adding one. "Attempts" is therefore shown as the current
// attempt's number, not a multi-row history table.
const props = defineProps({
    courseId: { type: [String, Number], required: true },
    assessment: { type: Object, required: true },
})

const emit = defineEmits(['completed'])

const attemptsStore = useSelfPacedAssessmentAttemptsStore()
const playerStore = useSelfPacedPlayerStore()

const attempt = ref(null)
const launching = ref(false)
const completing = ref(false)
const actionError = ref('')

const isLive = computed(() => attempt.value && ['started', 'in_progress'].includes(attempt.value.status))
const isFinished = computed(() => attempt.value && attempt.value.status === 'completed')
const canRetry = computed(
    () => props.assessment.attempts_mode !== 'limited' || !attempt.value || attempt.value.attempt_number < props.assessment.max_attempts,
)

async function launch() {
    launching.value = true
    actionError.value = ''

    try {
        const started = await attemptsStore.startOrResumeAttempt(props.courseId, props.assessment.id)
        attempt.value = started

        if (started.status === 'started') {
            attempt.value = await attemptsStore.markInProgress(props.courseId, props.assessment.id, started.id)
        }
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        launching.value = false
    }
}

async function submitResult(rawResult) {
    if (completing.value || !attempt.value) return

    completing.value = true
    actionError.value = ''

    try {
        attempt.value = await attemptsStore.completeAttempt(props.courseId, props.assessment.id, attempt.value.id, rawResult)
        const course = await playerStore.fetchCourse(props.courseId)
        emit('completed', { attempt: attempt.value, course })
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        completing.value = false
    }
}

function onH5pXapi(statement) {
    const result = statement?.result
    const verb = statement?.verb?.id ?? ''

    if (result?.completion === true || verb.endsWith('/completed') || verb.endsWith('/answered')) {
        submitResult({ statement })
    }
}

function loadH5pContent() {
    return playerStore.fetchH5pPlayerModel(props.courseId, props.assessment.id)
}
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink font-bold">Assessment</h2>

        <p v-if="actionError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="!attempt" class="mt-4">
            <button
                type="button"
                class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="launching"
                @click="launch"
            >
                {{ launching ? 'Starting…' : 'Start Assessment' }}
            </button>
        </div>

        <template v-else>
            <div class="mt-4 flex items-center gap-2">
                <StatusPill :status="isFinished ? 'completed' : 'in_progress'" />
                <span class="text-xs text-gray-500">Attempt {{ attempt.attempt_number }}</span>
            </div>

            <div v-if="isLive" class="mt-4">
                <H5pPlayerWidget
                    v-if="assessment.provider === 'h5p'"
                    :content-id="String(assessment.h5p_content_id)"
                    :load-content="loadH5pContent"
                    @xapi="onH5pXapi"
                />
                <SurveyJsPlayer
                    v-else-if="assessment.provider === 'surveyjs' && assessment.survey_json"
                    :survey-json="assessment.survey_json"
                    @submit="submitResult"
                />
                <p v-else class="text-sm text-gray-500">This assessment has not been configured yet.</p>

                <p v-if="completing" class="mt-4 text-center text-sm text-gray-500">Submitting…</p>
            </div>

            <div v-else-if="isFinished" class="mt-4 rounded-xl bg-gray-50 p-4">
                <p class="text-sm font-semibold text-gray-700">
                    Score: {{ attempt.raw_score }}<span v-if="attempt.max_score"> / {{ attempt.max_score }}</span>
                    <span v-if="attempt.percentage !== null"> ({{ attempt.percentage }}%)</span>
                </p>
                <p
                    v-if="attempt.passed !== null"
                    class="mt-1 text-sm font-semibold"
                    :class="attempt.passed ? 'text-green-700' : 'text-red-700'"
                >
                    {{ attempt.passed ? 'Passed' : 'Not Passed' }}
                </p>
                <p v-if="attempt.time_taken_seconds" class="mt-1 text-xs text-gray-500">
                    Time taken: {{ Math.round(attempt.time_taken_seconds / 60) }} min
                </p>

                <button
                    v-if="canRetry"
                    type="button"
                    class="mt-4 rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 disabled:opacity-40"
                    :disabled="launching"
                    @click="launch"
                >
                    Try Again
                </button>
                <p v-else class="mt-4 text-xs text-gray-400">You have used all of your attempts for this assessment.</p>
            </div>
        </template>
    </div>
</template>
