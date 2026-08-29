<script setup>
import { reactive, ref } from 'vue'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import Modal from '../common/Modal.vue'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'
import TextareaInput from '../forms/TextareaInput.vue'
import SelectInput from '../forms/SelectInput.vue'
import AssessmentProviderSelector from './AssessmentProviderSelector.vue'

const TYPE_OPTIONS = [
    { value: 'practice_quiz', label: 'Practice Quiz' },
    { value: 'knowledge_check', label: 'Knowledge Check' },
    { value: 'chapter_test', label: 'Chapter Test' },
    { value: 'mock_examination', label: 'Mock Examination' },
    { value: 'final_examination', label: 'Final Examination' },
]

const ATTEMPTS_MODE_OPTIONS = [
    { value: 'unlimited', label: 'Unlimited' },
    { value: 'limited', label: 'Limited' },
]

const props = defineProps({
    moduleId: { type: Number, required: true },
    assessment: { type: Object, default: null },
})

const emit = defineEmits(['saved', 'cancelled'])

const store = useSelfPacedCoursesStore()
const saving = ref(false)
const error = ref('')

const form = reactive({
    assessment_type: props.assessment?.assessment_type ?? 'practice_quiz',
    title: props.assessment?.title ?? '',
    description: props.assessment?.description ?? '',
    required: props.assessment?.required ?? true,
    passing_score: props.assessment?.passing_score ?? '',
    attempts_mode: props.assessment?.attempts_mode ?? 'unlimited',
    max_attempts: props.assessment?.max_attempts ?? '',
    time_limit_minutes: props.assessment?.time_limit_minutes ?? '',
    randomize_questions: props.assessment?.randomize_questions ?? false,
    show_results: props.assessment?.show_results ?? true,
    show_correct_answers: props.assessment?.show_correct_answers ?? false,
    weight: props.assessment?.weight ?? '',
    provider: props.assessment?.provider ?? '',
    provider_config: props.assessment?.provider_config ?? {},
})

async function save() {
    saving.value = true
    error.value = ''

    const payload = {
        assessment_type: form.assessment_type,
        title: form.title,
        description: form.description || null,
        required: form.required,
        passing_score: form.passing_score === '' ? null : Number(form.passing_score),
        attempts_mode: form.attempts_mode,
        max_attempts: form.attempts_mode === 'limited' && form.max_attempts !== '' ? Number(form.max_attempts) : null,
        time_limit_minutes: form.time_limit_minutes === '' ? null : Number(form.time_limit_minutes),
        randomize_questions: form.randomize_questions,
        show_results: form.show_results,
        show_correct_answers: form.show_correct_answers,
        weight: form.weight === '' ? null : Number(form.weight),
        provider: form.provider || null,
        provider_config: form.provider ? form.provider_config : null,
    }

    try {
        const saved = props.assessment
            ? await store.updateAssessment(props.assessment.id, payload)
            : await store.createAssessment(props.moduleId, payload)
        emit('saved', saved)
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <Modal :model-value="true" :title="assessment ? 'Edit Assessment' : 'Add Assessment'" @update:model-value="$emit('cancelled')">
        <form class="max-h-[70vh] space-y-4 overflow-y-auto pr-1" novalidate @submit.prevent="save">
            <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

            <SelectInput id="assessment-type" v-model="form.assessment_type" label="Assessment Type" :options="TYPE_OPTIONS" />
            <FloatingLabelInput id="assessment-title" v-model="form.title" label="Title" />
            <TextareaInput id="assessment-description" v-model="form.description" label="Description (optional)" :rows="2" />

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="form.required" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                Required for module completion
            </label>

            <div class="grid grid-cols-2 gap-4">
                <FloatingLabelInput id="assessment-passing-score" v-model="form.passing_score" type="number" label="Passing Score (%)" />
                <FloatingLabelInput id="assessment-weight" v-model="form.weight" type="number" label="Weight" />
                <SelectInput id="assessment-attempts-mode" v-model="form.attempts_mode" label="Attempts" :options="ATTEMPTS_MODE_OPTIONS" />
                <FloatingLabelInput
                    v-if="form.attempts_mode === 'limited'"
                    id="assessment-max-attempts"
                    v-model="form.max_attempts"
                    type="number"
                    label="Max Attempts"
                />
                <FloatingLabelInput id="assessment-time-limit" v-model="form.time_limit_minutes" type="number" label="Time Limit (minutes)" />
            </div>

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.randomize_questions" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                    Randomize questions
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.show_results" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                    Show results to students
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.show_correct_answers" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                    Show correct answers
                </label>
            </div>

            <div class="rounded-xl border border-gray-200 p-4">
                <p class="mb-2 text-sm font-semibold text-gray-700">Provider</p>
                <AssessmentProviderSelector
                    v-model:provider="form.provider"
                    v-model:provider-config="form.provider_config"
                />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700" @click="$emit('cancelled')">
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="saving"
                    class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
