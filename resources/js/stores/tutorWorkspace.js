import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorWorkspaceStore = defineStore('tutorWorkspace', {
    actions: {
        async fetchWorkspace() {
            const { data } = await api.get('/tutor/workspace')
            return data
        },

        async fetchUpcomingSessions(page = 1, perPage = 10) {
            const { data } = await api.get('/tutor/workspace/upcoming-sessions', { params: { page, per_page: perPage } })
            return data
        },
    },
})
