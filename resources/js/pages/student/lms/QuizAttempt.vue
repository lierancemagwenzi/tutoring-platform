<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Model } from 'survey-core'
import { SurveyComponent } from 'survey-vue3-ui'
import 'survey-core/survey-core.min.css'
import api from '../../../services/api'

const route = useRoute()
const quizId = Number(route.params.id)

const loading = ref(true)
const accessDenied = ref(false)
const quiz = ref(null)
const attempt = ref(null)
const surveyModel = ref(null)
const result = ref(null)
const submitting = ref(false)
const error = ref('')

onMounted(async () => {
    try {
        const { data } = await api.get(`/quizzes/${quizId}`)
        quiz.value = data.quiz
    } catch (err) {
        if (err.response?.status === 403) {
            accessDenied.value = true
        } else {
            error.value = err.response?.data?.message ?? 'Something went wrong. Please try again.'
        }
    } finally {
        loading.value = false
    }
})

async function startAttempt() {
    error.value = ''

    try {
        const { data } = await api.post(`/quizzes/${quizId}/attempts`)
        attempt.value = data.attempt
        buildSurveyModel()
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

function buildSurveyModel() {
    const model = new Model({
        elements: quiz.value.questions.map((question) => ({
            type: question.type,
            name: `question_${question.id}`,
            title: question.text,
            choices: question.choices,
            isRequired: true,
        })),
    })

    model.onComplete.add((sender) => submit(sender.data))
    surveyModel.value = model
}

async function submit(surveyData) {
    submitting.value = true
    error.value = ''

    const answers = quiz.value.questions.map((question) => ({
        question_id: question.id,
        answer: surveyData[`question_${question.id}`] ?? null,
    }))

    try {
        const { data } = await api.post(`/quizzes/attempts/${attempt.value.id}/submit`, { answers })
        result.value = data.attempt
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="mx-auto max-w-3xl p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="accessDenied" class="mt-16 rounded-2xl bg-white p-8 text-center shadow-sm">
            <h1 class="text-ink text-xl font-bold">You don't have access to this quiz</h1>
            <p class="mt-2 text-gray-500">
                You'll need a paid booking for a service matching this course's subject, grade, and curriculum to attempt it.
            </p>
        </div>

        <template v-else-if="quiz">
            <p v-if="error" class="mb-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

            <div v-if="result" class="rounded-2xl bg-white p-8 text-center shadow-sm">
                <h1 class="text-ink text-2xl font-bold">Quiz Complete</h1>
                <p class="mt-4 text-4xl font-bold text-gray-900">{{ result.score }} / {{ result.max_score }}</p>
                <p class="mt-2 text-gray-500">Attempt #{{ result.attempt_number }}</p>
            </div>

            <div v-else-if="!attempt" class="rounded-2xl bg-white p-8 text-center shadow-sm">
                <h1 class="text-ink text-2xl font-bold">{{ quiz.title }}</h1>
                <p v-if="quiz.description" class="mt-2 text-gray-500">{{ quiz.description }}</p>
                <p class="mt-2 text-sm text-gray-400">{{ quiz.questions.length }} questions</p>
                <button
                    type="button"
                    class="bg-amber mt-6 rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
                    @click="startAttempt"
                >
                    Start Quiz
                </button>
            </div>

            <div v-else class="rounded-2xl bg-white p-8 shadow-sm">
                <SurveyComponent v-if="surveyModel" :model="surveyModel" />
                <p v-if="submitting" class="mt-4 text-center text-sm text-gray-500">Submitting…</p>
            </div>
        </template>
    </div>
</template>
