import { defineStore } from 'pinia'
import api from '../services/api'

// The full student directory — every student, searchable.
export const useAdminStudentsStore = defineStore('adminStudents', {
    state: () => ({
        students: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        current: null,
    }),

    actions: {
        async fetchStudents({ search = '', page = 1 } = {}) {
            const { data } = await api.get('/admin/students', { params: { search, page } })
            this.students = data.students
            this.meta = data.meta
            return data
        },

        async fetchDetail(studentId) {
            const { data } = await api.get(`/admin/students/${studentId}`)
            this.current = data
            return data
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
