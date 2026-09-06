import { defineStore } from 'pinia'
import api from '../services/api'

// The full tutor directory — every tutor, searchable — distinct from
// adminTutorApprovals.js, which is the pending-application review queue.
export const useAdminTutorsStore = defineStore('adminTutors', {
    state: () => ({
        tutors: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        current: null,
    }),

    actions: {
        async fetchTutors({ search = '', page = 1 } = {}) {
            const { data } = await api.get('/admin/tutor-profiles', { params: { search, page } })
            this.tutors = data.tutors
            this.meta = data.meta
            return data
        },

        async fetchDetail(tutorProfileId) {
            const { data } = await api.get(`/admin/tutor-profiles/${tutorProfileId}`)
            this.current = data.tutor
            return data.tutor
        },

        async disableUser(userId) {
            const { data } = await api.post(`/admin/users/${userId}/disable`)
            return data.user
        },

        async enableUser(userId) {
            const { data } = await api.post(`/admin/users/${userId}/enable`)
            return data.user
        },
    },
})
