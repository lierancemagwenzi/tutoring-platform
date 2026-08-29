import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminTutorSubjectRequestsStore = defineStore('adminTutorSubjectRequests', {
    state: () => ({
        requests: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),

    actions: {
        async fetchPending(page = 1) {
            const { data } = await api.get('/admin/tutor-subject-requests', { params: { page } })
            this.requests = data.requests
            this.meta = data.meta
            return data
        },

        async approve(id) {
            const { data } = await api.post(`/admin/tutor-subject-requests/${id}/approve`)
            return data.request
        },

        async reject(id, reason) {
            const { data } = await api.post(`/admin/tutor-subject-requests/${id}/reject`, { reason })
            return data.request
        },

        async suspend(id, reason) {
            const { data } = await api.post(`/admin/tutor-subject-requests/${id}/suspend`, { reason })
            return data.request
        },
    },
})
