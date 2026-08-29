<script setup>
import { computed, onMounted, ref } from 'vue'
import { Model } from 'survey-core'
import { SurveyComponent } from 'survey-vue3-ui'
import 'survey-core/survey-core.min.css'
import { useAttemptsStore } from '../../stores/attempts'
import { useBookingStore } from '../../stores/booking'
import H5pPlayerWidget from './H5pPlayerWidget.vue'

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

const props = defineProps({
    bookingId: { type: [String, Number], required: true },
    block: { type: Object, required: true },
})

const attemptsStore = useAttemptsStore()
const bookingStore = useBookingStore()

const loading = ref(true)
const actionError = ref('')
const attempts = ref([])
const launching = ref(false)
const completing = ref(false)
const resultReady = ref(false)
const surveyModel = ref(null)

const current = computed(() => attempts.value[0] ?? null)
const history = computed(() => attempts.value.slice(1))
const isLive = computed(() => current.value && ['started', 'in_progress'].includes(current.value.status) && !resultReady.value)

onMounted(async () => {
    attempts.value = await attemptsStore.fetchAttempts(props.bookingId, props.block.id)
    loading.value = false
})

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

async function launch() {
    launching.value = true
    actionError.value = ''
    resultReady.value = false

    try {
        const attempt = await attemptsStore.startOrResumeAttempt(props.bookingId, props.block.id)
        attempts.value.unshift(attempt)

        if (attempt.status === 'started') {
            attempts.value[0] = await attemptsStore.markInProgress(attempt.id)
        }

        if (props.block.block_type === 'quiz') {
            buildSurveyModel()
        }
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        launching.value = false
    }
}

function buildSurveyModel() {
    const model = new Model({
        elements: (props.block.quiz?.questions ?? []).map((question) => ({
            type: question.type,
            name: `question_${question.id}`,
            title: question.text,
            choices: question.choices,
            isRequired: true,
        })),
    })

    model.onComplete.add((sender) => completeAttempt(sender.data))
    surveyModel.value = model
}

async function completeAttempt(rawResult) {
    if (completing.value || resultReady.value) return

    completing.value = true
    actionError.value = ''
    try {
        attempts.value[0] = await attemptsStore.completeAttempt(current.value.id, rawResult)
        resultReady.value = true
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
        completeAttempt({ statement })
    }
}

function loadH5pContent() {
    return bookingStore.fetchH5pPlayerModel(props.bookingId, props.block.id)
}
</script>

<template>
    <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink font-bold">Activity</h2>

        <p v-if="actionError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="mt-6 flex justify-center py-8">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="!current || (!isLive && !resultReady && current.status !== 'completed')" class="mt-4">
            <button
                type="button"
                class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="launching"
                @click="launch"
            >
                {{ launching ? 'Launching…' : 'Launch Activity' }}
            </button>
        </div>

        <template v-else>
            <div class="mt-4 flex items-center gap-2">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(current.status)">
                    {{ statusLabel(current.status) }}
                </span>
                <span class="text-xs text-gray-500">Attempt {{ current.attempt_number }}</span>
            </div>

            <div v-if="isLive" class="mt-4">
                <H5pPlayerWidget
                    v-if="block.block_type === 'h5p' && block.h5p_content"
                    :content-id="block.h5p_content.id"
                    :load-content="loadH5pContent"
                    @xapi="onH5pXapi"
                />
                <SurveyComponent v-else-if="block.block_type === 'quiz' && surveyModel" :model="surveyModel" />
                <p v-if="completing" class="mt-4 text-center text-sm text-gray-500">Submitting…</p>
            </div>

            <div v-else class="mt-4 rounded-xl bg-gray-50 p-4">
                <p class="text-sm font-semibold text-gray-700">
                    Score: {{ current.raw_score }}<span v-if="current.max_score"> / {{ current.max_score }}</span>
                    <span v-if="current.percentage !== null"> ({{ current.percentage }}%)</span>
                </p>
                <p v-if="current.passed !== null" class="mt-1 text-sm font-semibold" :class="current.passed ? 'text-green-700' : 'text-red-700'">
                    {{ current.passed ? 'Passed' : 'Not Passed' }}
                </p>
                <p v-if="current.time_taken_seconds" class="mt-1 text-xs text-gray-500">
                    Time taken: {{ Math.round(current.time_taken_seconds / 60) }} min
                </p>

                <button
                    type="button"
                    class="mt-4 rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 disabled:opacity-40"
                    :disabled="launching"
                    @click="launch"
                >
                    Launch New Attempt
                </button>
            </div>

            <div v-if="history.length" class="mt-6 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Previous Attempts</p>
                <div v-for="past in history" :key="past.id" class="mt-2 flex items-center justify-between text-sm">
                    <span class="text-gray-600">Attempt {{ past.attempt_number }}</span>
                    <span v-if="past.percentage !== null" class="text-gray-600">{{ past.percentage }}%</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(past.status)">
                        {{ statusLabel(past.status) }}
                    </span>
                </div>
            </div>
        </template>
    </div>
</template>
