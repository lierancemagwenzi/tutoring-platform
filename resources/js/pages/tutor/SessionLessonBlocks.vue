<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSessionContentStore } from '../../stores/sessionContent'
import Modal from '../../components/common/Modal.vue'
import SelectInput from '../../components/forms/SelectInput.vue'
import { blockRegistry } from '../../lms/blockRegistry'

const AVAILABILITY_OPTIONS = [
    { value: 'always_available', label: 'Always Available' },
    { value: 'immediate', label: 'Available Immediately' },
    { value: 'manual_release', label: 'Manual Release' },
    { value: 'scheduled_release', label: 'Scheduled Release' },
    { value: 'assessment_window', label: 'Assessment Window' },
]

const COMPLETION_MODE_OPTIONS = [
    { value: 'not_tracked', label: 'Not Tracked' },
    { value: 'optional', label: 'Optional' },
    { value: 'required', label: 'Required' },
]

const COMPLETION_RULE_OPTIONS = [
    { value: 'view_activity', label: 'Viewed' },
    { value: 'submit_work', label: 'Submitted' },
    { value: 'pass_activity', label: 'Passed' },
    { value: 'tutor_approval', label: 'Tutor Approval' },
]

const ATTEMPTS_OPTIONS = [
    { value: 'unlimited', label: 'Unlimited Attempts' },
    { value: 'limited', label: 'Limited Attempts' },
]

const VISIBILITY_OPTIONS = [
    { value: 'hidden', label: 'Hidden' },
    { value: 'visible', label: 'Visible' },
    { value: 'scheduled', label: 'Scheduled' },
]

// Mirrors the Submission Engine's eligible types (App\Models\LessonBlock::supportsSubmissions).
const SUBMISSION_ELIGIBLE_TYPES = ['assignment', 'homework', 'project', 'lab', 'reflection']

// Mirrors the Attempt Engine's eligible types (App\Models\LessonBlock::attemptProvider).
const ATTEMPT_ELIGIBLE_TYPES = ['h5p', 'quiz']

const route = useRoute()
const router = useRouter()
const store = useSessionContentStore()
const sessionLessonId = computed(() => Number(route.params.id))

const loading = ref(true)
const actionError = ref('')
const blocks = ref([])
const assignments = ref([])

const modalOpen = ref(false)
const activeBlock = ref(null)
const activeAssignment = ref(null)
const saving = ref(false)
const formError = ref('')

const form = ref({
    availabilityMode: 'always_available',
    availableFrom: '',
    availableUntil: '',
    opensAt: '',
    closesAt: '',
    isManuallyReleased: false,
    completionMode: 'not_tracked',
    completionRule: '',
    attemptsMode: 'unlimited',
    maxAttempts: '',
    passingScore: '',
    visibility: 'visible',
})

const assignmentByBlockId = computed(() => {
    const map = new Map()
    for (const assignment of assignments.value) {
        map.set(assignment.lesson_block_id, assignment)
    }
    return map
})

onMounted(async () => {
    await load()
    loading.value = false
})

async function load() {
    const data = await store.fetchLessonContent(sessionLessonId.value)
    blocks.value = data.blocks
    assignments.value = data.assignments
}

function assignmentFor(block) {
    return assignmentByBlockId.value.get(block.id) ?? null
}

function supportsSubmissions(block) {
    return SUBMISSION_ELIGIBLE_TYPES.includes(block.block_type) && block.learning_activity?.submission_type !== 'none'
}

function viewSubmissions(block) {
    router.push(`/tutor/session-lesson-blocks/${assignmentFor(block).id}/submissions`)
}

function supportsAttempts(block) {
    return ATTEMPT_ELIGIBLE_TYPES.includes(block.block_type)
}

function viewAttempts(block) {
    router.push(`/tutor/session-lesson-blocks/${assignmentFor(block).id}/attempts`)
}

function availabilityLabel(assignment) {
    return AVAILABILITY_OPTIONS.find((option) => option.value === assignment.availability_mode)?.label ?? assignment.availability_mode
}

function openConfigure(block) {
    activeBlock.value = block
    const assignment = assignmentFor(block)
    activeAssignment.value = assignment
    formError.value = ''

    form.value = {
        availabilityMode: assignment?.availability_mode ?? 'always_available',
        availableFrom: assignment?.available_from ? assignment.available_from.slice(0, 16) : '',
        availableUntil: assignment?.available_until ? assignment.available_until.slice(0, 16) : '',
        opensAt: assignment?.opens_at ? assignment.opens_at.slice(0, 16) : '',
        closesAt: assignment?.closes_at ? assignment.closes_at.slice(0, 16) : '',
        isManuallyReleased: assignment?.is_manually_released ?? false,
        completionMode: assignment?.completion_mode ?? 'not_tracked',
        completionRule: assignment?.completion_rule ?? '',
        attemptsMode: assignment?.attempts_mode ?? 'unlimited',
        maxAttempts: assignment?.max_attempts ?? '',
        passingScore: assignment?.passing_score ?? '',
        visibility: assignment?.visibility ?? 'visible',
    }

    modalOpen.value = true
}

async function save() {
    saving.value = true
    formError.value = ''

    const payload = {
        lesson_block_id: activeBlock.value.id,
        availability_mode: form.value.availabilityMode,
        available_from: form.value.availabilityMode === 'scheduled_release' ? form.value.availableFrom : null,
        available_until: form.value.availabilityMode === 'scheduled_release' ? form.value.availableUntil || null : null,
        opens_at: form.value.availabilityMode === 'assessment_window' ? form.value.opensAt : null,
        closes_at: form.value.availabilityMode === 'assessment_window' ? form.value.closesAt : null,
        is_manually_released: form.value.availabilityMode === 'manual_release' ? form.value.isManuallyReleased : false,
        completion_mode: form.value.completionMode,
        completion_rule: form.value.completionMode !== 'not_tracked' ? form.value.completionRule : null,
        attempts_mode: form.value.attemptsMode,
        max_attempts: form.value.attemptsMode === 'limited' ? form.value.maxAttempts : null,
        passing_score: form.value.passingScore || null,
        visibility: form.value.visibility,
    }

    try {
        if (activeAssignment.value) {
            const updated = await store.updateBlockAvailability(sessionLessonId.value, activeAssignment.value.id, payload)
            const index = assignments.value.findIndex((entry) => entry.id === updated.id)
            assignments.value[index] = updated
        } else {
            const created = await store.assignBlock(sessionLessonId.value, payload)
            assignments.value.push(created)
        }
        modalOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}

async function unassign(block) {
    const assignment = assignmentFor(block)
    if (!assignment || !confirm(`Unassign "${block.title || blockRegistry[block.block_type]?.label}" from this session?`)) {
        return
    }

    actionError.value = ''
    try {
        await store.removeBlock(sessionLessonId.value, assignment.id)
        assignments.value = assignments.value.filter((entry) => entry.id !== assignment.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function toggleManualRelease(block) {
    const assignment = assignmentFor(block)
    if (!assignment) return

    actionError.value = ''
    try {
        const updated = await store.updateBlockAvailability(sessionLessonId.value, assignment.id, {
            lesson_block_id: block.id,
            availability_mode: 'manual_release',
            is_manually_released: !assignment.is_manually_released,
        })
        const index = assignments.value.findIndex((entry) => entry.id === updated.id)
        assignments.value[index] = updated
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

function backToContent() {
    if (route.query.sessionId) {
        router.push(`/tutor/sessions/${route.query.sessionId}/content`)
    } else {
        router.back()
    }
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="backToContent">&larr; Back to Session Content</button>
        <h1 class="text-body mt-1 text-2xl font-bold">Manage Lesson Content</h1>
        <p class="text-muted mt-1">
            {{ route.query.lessonTitle ? `Choose which blocks of "${route.query.lessonTitle}" are available to students.` : 'Choose which blocks are available to students.' }}
        </p>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="blocks.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-muted">This lesson has no content blocks yet.</p>
        </div>

        <div v-else class="mt-8 space-y-3">
            <div v-for="block in blocks" :key="block.id" class="bg-card shadow-elevated flex items-start gap-4 rounded-2xl p-5">
                <span class="bg-accent/10 text-accent mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full">
                    <component :is="blockRegistry[block.block_type]?.icon" class="h-4 w-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-body font-bold">{{ block.title || blockRegistry[block.block_type]?.label }}</p>
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">
                            {{ blockRegistry[block.block_type]?.label }}
                        </span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                            :class="block.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                        >
                            {{ block.status }}
                        </span>

                        <template v-if="assignmentFor(block)">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="assignmentFor(block).is_available ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                            >
                                {{ assignmentFor(block).is_available ? 'Available to students' : 'Not yet available' }}
                            </span>
                            <span class="text-muted text-xs">{{ availabilityLabel(assignmentFor(block)) }}</span>
                            <span
                                v-if="assignmentFor(block).visibility !== 'visible'"
                                class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 capitalize"
                            >
                                {{ assignmentFor(block).visibility }}
                            </span>
                        </template>
                        <span v-else class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500">Not assigned</span>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="assignmentFor(block) && supportsSubmissions(block)"
                        type="button"
                        class="text-accent border-border rounded-full border px-4 py-2 text-sm font-semibold"
                        @click="viewSubmissions(block)"
                    >
                        View Submissions
                    </button>
                    <button
                        v-if="assignmentFor(block) && supportsAttempts(block)"
                        type="button"
                        class="text-accent border-border rounded-full border px-4 py-2 text-sm font-semibold"
                        @click="viewAttempts(block)"
                    >
                        View Attempts
                    </button>
                    <button
                        v-if="assignmentFor(block)?.availability_mode === 'manual_release'"
                        type="button"
                        class="border-border text-body rounded-full border px-4 py-2 text-sm font-semibold"
                        @click="toggleManualRelease(block)"
                    >
                        {{ assignmentFor(block).is_manually_released ? 'Hide from students' : 'Release now' }}
                    </button>
                    <span
                        v-if="!assignmentFor(block) && block.status !== 'published'"
                        class="text-muted text-sm font-semibold"
                        title="Publish this block before assigning it to a session."
                    >
                        Publish to assign
                    </span>
                    <button
                        v-else
                        type="button"
                        class="text-accent border-border rounded-full border px-4 py-2 text-sm font-semibold"
                        @click="openConfigure(block)"
                    >
                        {{ assignmentFor(block) ? 'Edit Availability' : 'Assign' }}
                    </button>
                    <button
                        v-if="assignmentFor(block)"
                        type="button"
                        class="border-border rounded-full border px-4 py-2 text-sm font-semibold text-red-500 hover:text-red-700"
                        @click="unassign(block)"
                    >
                        Unassign
                    </button>
                </div>
            </div>
        </div>

        <Modal v-model="modalOpen" :title="activeAssignment ? 'Edit Availability' : 'Assign Lesson Block'">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

                <SelectInput
                    id="availability-mode"
                    v-model="form.availabilityMode"
                    label="Availability Strategy"
                    :options="AVAILABILITY_OPTIONS"
                />

                <template v-if="form.availabilityMode === 'scheduled_release'">
                    <div>
                        <label for="available-from" class="text-body mb-1 block text-sm font-medium">Available From</label>
                        <input
                            id="available-from"
                            v-model="form.availableFrom"
                            type="datetime-local"
                            class="bg-card text-body border-border w-full rounded-xl border px-4 py-3"
                        />
                    </div>
                    <div>
                        <label for="available-until" class="text-body mb-1 block text-sm font-medium">Available Until (optional)</label>
                        <input
                            id="available-until"
                            v-model="form.availableUntil"
                            type="datetime-local"
                            class="bg-card text-body border-border w-full rounded-xl border px-4 py-3"
                        />
                    </div>
                </template>

                <template v-if="form.availabilityMode === 'assessment_window'">
                    <div>
                        <label for="opens-at" class="text-body mb-1 block text-sm font-medium">Opens At</label>
                        <input id="opens-at" v-model="form.opensAt" type="datetime-local" class="bg-card text-body border-border w-full rounded-xl border px-4 py-3" />
                    </div>
                    <div>
                        <label for="closes-at" class="text-body mb-1 block text-sm font-medium">Closes At</label>
                        <input id="closes-at" v-model="form.closesAt" type="datetime-local" class="bg-card text-body border-border w-full rounded-xl border px-4 py-3" />
                    </div>
                </template>

                <label v-if="form.availabilityMode === 'manual_release'" class="text-body flex items-center gap-2 text-sm">
                    <input v-model="form.isManuallyReleased" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                    Released to students
                </label>

                <hr class="border-border" />

                <SelectInput id="visibility" v-model="form.visibility" label="Visibility" :options="VISIBILITY_OPTIONS" />

                <SelectInput id="completion-mode" v-model="form.completionMode" label="Completion" :options="COMPLETION_MODE_OPTIONS" />
                <SelectInput
                    v-if="form.completionMode !== 'not_tracked'"
                    id="completion-rule"
                    v-model="form.completionRule"
                    label="Completion Rule"
                    :options="COMPLETION_RULE_OPTIONS"
                />

                <SelectInput id="attempts-mode" v-model="form.attemptsMode" label="Attempts" :options="ATTEMPTS_OPTIONS" />
                <div v-if="form.attemptsMode === 'limited'">
                    <label for="max-attempts" class="text-body mb-1 block text-sm font-medium">Maximum Attempts</label>
                    <input
                        id="max-attempts"
                        v-model="form.maxAttempts"
                        type="number"
                        min="1"
                        class="bg-card text-body border-border w-full rounded-xl border px-4 py-3"
                    />
                </div>

                <div>
                    <label for="passing-score" class="text-body mb-1 block text-sm font-medium">Passing Score (optional)</label>
                    <input
                        id="passing-score"
                        v-model="form.passingScore"
                        type="number"
                        min="0"
                        class="bg-card text-body border-border w-full rounded-xl border px-4 py-3"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="border-border text-body rounded-full border px-5 py-2.5 font-semibold"
                        @click="modalOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="bg-amber shadow-elevated rounded-full px-6 py-2.5 font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
