import { defineStore } from 'pinia'
import api from '../services/api'

export const useQuizzesStore = defineStore('quizzes', {
    actions: {
        async fetchQuiz(quizId) {
            const { data } = await api.get(`/tutor/quizzes/${quizId}`)
            return data.quiz
        },

        async updateQuiz(quizId, payload) {
            const { data } = await api.put(`/tutor/quizzes/${quizId}`, payload)
            return data.quiz
        },

        async publishQuiz(quizId) {
            const { data } = await api.patch(`/tutor/quizzes/${quizId}/publish`)
            return data.quiz
        },

        async createQuestion(quizId, payload) {
            const { data } = await api.post(`/tutor/quizzes/${quizId}/questions`, payload)
            return data.question
        },

        async updateQuestion(questionId, payload) {
            const { data } = await api.put(`/tutor/quiz-questions/${questionId}`, payload)
            return data.question
        },

        async deleteQuestion(questionId) {
            await api.delete(`/tutor/quiz-questions/${questionId}`)
        },

        async reorderQuestions(quizId, questionIds) {
            const { data } = await api.patch(`/tutor/quizzes/${quizId}/questions/reorder`, { question_ids: questionIds })
            return data.questions
        },
    },
})
