import { defineStore } from 'pinia'
import api from '../services/api'

export const useLearningHubStore = defineStore('learningHub', {
    actions: {
        async fetchHub() {
            const { data } = await api.get('/student/learning-hub')
            return data
        },

        async fetchUpcomingSessions(page = 1, perPage = 10) {
            const { data } = await api.get('/student/learning-hub/upcoming-sessions', { params: { page, per_page: perPage } })
            return data
        },
    },
})
