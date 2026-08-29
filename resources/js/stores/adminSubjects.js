import { defineStore } from 'pinia'
import api from '../services/api'

function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

export const useAdminSubjectsStore = defineStore('adminSubjects', {
    state: () => ({
        subjects: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        current: null,
    }),

    actions: {
        async fetchSubjects(params = {}) {
            const { data } = await api.get('/admin/subjects', { params: cleanParams(params) })
            this.subjects = data.subjects
            this.meta = data.meta
            return data
        },

        async fetchSubject(id) {
            const { data } = await api.get(`/admin/subjects/${id}`)
            this.current = data.subject
            return data.subject
        },

        async createSubject(payload) {
            const { data } = await api.post('/admin/subjects', payload)
            return data.subject
        },

        async updateSubject(id, payload) {
            const { data } = await api.patch(`/admin/subjects/${id}`, payload)
            return data.subject
        },

        async activateSubject(id) {
            const { data } = await api.post(`/admin/subjects/${id}/activate`)
            return data.subject
        },

        async deactivateSubject(id) {
            const { data } = await api.post(`/admin/subjects/${id}/deactivate`)
            return data.subject
        },

        async archiveSubject(id) {
            const { data } = await api.post(`/admin/subjects/${id}/archive`)
            return data.subject
        },
    },
})
