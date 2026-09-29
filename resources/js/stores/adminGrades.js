import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminGradesStore = defineStore('adminGrades', {
    state: () => ({
        grades: [],
    }),

    actions: {
        async fetchGrades() {
            const { data } = await api.get('/admin/grades')
            this.grades = data.grades
            return data.grades
        },

        async createGrade(payload) {
            const { data } = await api.post('/admin/grades', payload)
            return data.grade
        },

        async updateGrade(id, payload) {
            const { data } = await api.patch(`/admin/grades/${id}`, payload)
            return data.grade
        },

        async activateGrade(id) {
            const { data } = await api.post(`/admin/grades/${id}/activate`)
            return data.grade
        },

        async deactivateGrade(id) {
            const { data } = await api.post(`/admin/grades/${id}/deactivate`)
            return data.grade
        },
    },
})
