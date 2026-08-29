import { defineStore } from 'pinia'
import api from '../services/api'

export const useSelfPacedSurveyContentsStore = defineStore('selfPacedSurveyContents', {
    actions: {
        // --- Survey content (question banks) — dedicated to self-paced
        // courses, tagged by tutor/grade/subject/curriculum. ---

        async fetchSurveyContents(filters = {}) {
            const { data } = await api.get('/tutor/self-paced-survey-contents', { params: filters })
            return data.survey_contents
        },

        async fetchSurveyContent(id) {
            const { data } = await api.get(`/tutor/self-paced-survey-contents/${id}`)
            return data.survey_content
        },

        async createSurveyContent(payload) {
            const { data } = await api.post('/tutor/self-paced-survey-contents', payload)
            return data.survey_content
        },

        async updateSurveyContent(id, payload) {
            const { data } = await api.put(`/tutor/self-paced-survey-contents/${id}`, payload)
            return data.survey_content
        },

        async deleteSurveyContent(id) {
            await api.delete(`/tutor/self-paced-survey-contents/${id}`)
        },

        // --- Questions ---

        async createQuestion(surveyContentId, payload) {
            const { data } = await api.post(`/tutor/self-paced-survey-contents/${surveyContentId}/questions`, payload)
            return data.question
        },

        async updateQuestion(questionId, payload) {
            const { data } = await api.put(`/tutor/self-paced-survey-questions/${questionId}`, payload)
            return data.question
        },

        async deleteQuestion(questionId) {
            await api.delete(`/tutor/self-paced-survey-questions/${questionId}`)
        },

        async reorderQuestions(surveyContentId, questionIds) {
            const { data } = await api.patch(`/tutor/self-paced-survey-contents/${surveyContentId}/questions/reorder`, {
                question_ids: questionIds,
            })
            return data.questions
        },
    },
})
