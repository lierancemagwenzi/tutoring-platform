import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminTutorApprovalsStore = defineStore('adminTutorApprovals', {
    state: () => ({
        tutors: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        current: null,
    }),

    actions: {
        async fetchPending(page = 1) {
            const { data } = await api.get('/admin/tutors/pending', { params: { page } })
            this.tutors = data.tutors
            this.meta = data.meta
            return data
        },

        async fetchDetail(userId) {
            const { data } = await api.get(`/admin/tutors/${userId}`)
            this.current = data.tutor
            return data.tutor
        },

        async approve(userId) {
            const { data } = await api.post(`/admin/tutors/${userId}/approve`)
            return data.tutor
        },

        async reject(userId, reason) {
            const { data } = await api.post(`/admin/tutors/${userId}/reject`, { reason })
            return data.tutor
        },

        async requestChanges(userId, note) {
            const { data } = await api.post(`/admin/tutors/${userId}/request-changes`, { note })
            return data.tutor
        },
    },
})
