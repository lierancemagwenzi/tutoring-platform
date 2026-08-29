<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import { Model } from 'survey-core'
import { SurveyComponent } from 'survey-vue3-ui'
import 'survey-core/survey-core.min.css'
import { Bars3Icon, EyeIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useQuizzesStore } from '../../../stores/quizzes'
import Modal from '../../../components/common/Modal.vue'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../../components/forms/TextareaInput.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'
import TagInput from '../../../components/forms/TagInput.vue'

const QUESTION_TYPE_OPTIONS = [
    { value: 'radiogroup', label: 'Single Choice' },
    { value: 'checkbox', label: 'Multiple Choice' },
    { value: 'boolean', label: 'True / False' },
    { value: 'text', label: 'Short Text' },
]

const BOOLEAN_OPTIONS = [
    { value: 'true', label: 'True' },
    { value: 'false', label: 'False' },
]

const route = useRoute()
const store = useQuizzesStore()
const quizId = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const actionError = ref('')
const quiz = ref(null)

const modalOpen = ref(false)
const editingQuestion = ref(null)
const form = reactive({
    type: 'radiogroup',
    text: '',
    choices: [],
    correctSingle: '',
    correctMultiple: [],
    correctBoolean: 'true',
    points: 1,
})

const previewOpen = ref(false)
const previewModel = ref(null)

const hasChoices = computed(() => ['radiogroup', 'checkbox'].includes(form.type))

onMounted(async () => {
    quiz.value = await store.fetchQuiz(quizId.value)
    loading.value = false
})

function resetForm() {
    form.type = 'radiogroup'
    form.text = ''
    form.choices = []
    form.correctSingle = ''
    form.correctMultiple = []
    form.correctBoolean = 'true'
    form.points = 1
}

function openCreate() {
    editingQuestion.value = null
    resetForm()
    modalOpen.value = true
}

function openEdit(question) {
    editingQuestion.value = question
    form.type = question.type
    form.text = question.text
    form.choices = question.choices ?? []
    form.correctSingle = question.type === 'radiogroup' ? (question.correct_answer ?? '') : ''
    form.correctMultiple = question.type === 'checkbox' ? (question.correct_answer ?? []) : []
    form.correctBoolean = question.type === 'boolean' ? String(question.correct_answer ?? true) : 'true'
    form.points = question.points
    modalOpen.value = true
}

function toggleMultipleChoice(choice) {
    const index = form.correctMultiple.indexOf(choice)
    if (index === -1) {
        form.correctMultiple.push(choice)
    } else {
        form.correctMultiple.splice(index, 1)
    }
}

function buildPayload() {
    const payload = {
        type: form.type,
        text: form.text,
        points: Number(form.points),
    }

    if (hasChoices.value) {
        payload.choices = form.choices
    }

    if (form.type === 'radiogroup') {
        payload.correct_answer = form.correctSingle
    } else if (form.type === 'checkbox') {
        payload.correct_answer = form.correctMultiple
    } else if (form.type === 'boolean') {
        payload.correct_answer = form.correctBoolean === 'true'
    }

    return payload
}

async function save() {
    saving.value = true
    actionError.value = ''

    try {
        if (editingQuestion.value) {
            const updated = await store.updateQuestion(editingQuestion.value.id, buildPayload())
            const index = quiz.value.questions.findIndex((question) => question.id === updated.id)
            if (index !== -1) {
                quiz.value.questions[index] = updated
            }
        } else {
            const created = await store.createQuestion(quizId.value, buildPayload())
            quiz.value.questions.push(created)
        }
        modalOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}

async function remove(question) {
    if (!confirm('Delete this question?')) {
        return
    }

    actionError.value = ''
    try {
        await store.deleteQuestion(question.id)
        quiz.value.questions = quiz.value.questions.filter((entry) => entry.id !== question.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function onReorder() {
    try {
        await store.reorderQuestions(quizId.value, quiz.value.questions.map((question) => question.id))
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not save the new order. Please try again.'
    }
}

function openPreview() {
    previewModel.value = new Model({
        elements: quiz.value.questions.map((question) => ({
            type: question.type,
            name: `question_${question.id}`,
            title: question.text,
            choices: question.choices,
        })),
    })
    previewOpen.value = true
}

async function publish() {
    actionError.value = ''
    try {
        quiz.value = await store.publishQuiz(quizId.value)
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    }
}
</script>

<template>
    <div class="p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else>
            <div class="flex items-center justify-between">
                <div>
                    <button type="button" class="text-accent text-sm font-semibold" @click="$router.back()">&larr; Back to Lesson Builder</button>
                    <div class="mt-1 flex items-center gap-2">
                        <h1 class="text-ink text-2xl font-bold">{{ quiz.title }}</h1>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                            :class="quiz.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                        >
                            {{ quiz.status }}
                        </span>
                    </div>
                    <p class="mt-1 text-gray-500">Build and arrange the quiz's questions.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700"
                        @click="openPreview"
                    >
                        <EyeIcon class="h-4 w-4" />
                        Preview
                    </button>
                    <button
                        v-if="quiz.status !== 'published'"
                        type="button"
                        class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                        @click="publish"
                    >
                        Publish
                    </button>
                    <button
                        type="button"
                        class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                        @click="openCreate"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Add Question
                    </button>
                </div>
            </div>

            <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

            <div v-if="quiz.questions.length === 0" class="mt-16 flex flex-col items-center text-center">
                <p class="text-gray-500">No questions yet. Add your first question to get started.</p>
            </div>

            <draggable
                v-else
                v-model="quiz.questions"
                item-key="id"
                handle=".drag-handle"
                class="mt-8 space-y-3"
                @end="onReorder"
            >
                <template #item="{ element: question }">
                    <div class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm">
                        <span class="drag-handle cursor-grab text-gray-400">
                            <Bars3Icon class="h-5 w-5" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-ink truncate font-bold">{{ question.text }}</p>
                            <p class="mt-0.5 text-sm text-gray-500">
                                {{ QUESTION_TYPE_OPTIONS.find((option) => option.value === question.type)?.label }} &middot;
                                {{ question.points }} {{ question.points === 1 ? 'point' : 'points' }}
                            </p>
                        </div>

                        <button type="button" class="shrink-0 text-gray-500 hover:text-gray-700" @click="openEdit(question)">
                            <PencilSquareIcon class="h-4 w-4" />
                        </button>
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="remove(question)">
                            <TrashIcon class="h-4 w-4" />
                        </button>
                    </div>
                </template>
            </draggable>
        </template>

        <Modal v-model="modalOpen" :title="editingQuestion ? 'Edit Question' : 'Add Question'">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <SelectInput id="question-type" v-model="form.type" label="Question Type" :options="QUESTION_TYPE_OPTIONS" />
                <TextareaInput id="question-text" v-model="form.text" label="Question" :rows="3" />

                <TagInput v-if="hasChoices" id="question-choices" v-model="form.choices" label="Choices (press Enter to add)" />

                <div v-if="form.type === 'radiogroup' && form.choices.length > 0" class="space-y-2">
                    <p class="font-semibold text-gray-700">Correct Answer</p>
                    <label v-for="choice in form.choices" :key="choice" class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.correctSingle" type="radio" :value="choice" class="accent-accent h-4 w-4" />
                        {{ choice }}
                    </label>
                </div>

                <div v-if="form.type === 'checkbox' && form.choices.length > 0" class="space-y-2">
                    <p class="font-semibold text-gray-700">Correct Answers</p>
                    <label v-for="choice in form.choices" :key="choice" class="flex items-center gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            class="accent-accent h-4 w-4 rounded"
                            :checked="form.correctMultiple.includes(choice)"
                            @change="toggleMultipleChoice(choice)"
                        />
                        {{ choice }}
                    </label>
                </div>

                <SelectInput v-if="form.type === 'boolean'" id="question-boolean" v-model="form.correctBoolean" label="Correct Answer" :options="BOOLEAN_OPTIONS" />

                <FloatingLabelInput id="question-points" v-model="form.points" type="number" label="Points" />

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700" @click="modalOpen = false">
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

        <Modal v-model="previewOpen" title="Quiz Preview">
            <SurveyComponent v-if="previewModel" :model="previewModel" />
        </Modal>
    </div>
</template>
