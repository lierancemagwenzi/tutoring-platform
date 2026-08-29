import { defineStore } from 'pinia'
import api from '../services/api'

function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

export const useMarketplaceStore = defineStore('marketplace', {
    state: () => ({
        tutors: [],
        meta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
        subjects: [],
        grades: [],
        categories: [],
        sessionFormats: [],
        curricula: [],
        tutorProfile: null,
        selfPacedCourses: [],
        selfPacedCoursesMeta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
        selfPacedCourseFilters: { subjects: [], grades: [], languages: [], tutors: [] },
    }),

    actions: {
        async fetchFilterLookups() {
            const [subjects, grades, categories, sessionFormats, curricula] = await Promise.all([
                api.get('/subjects'),
                api.get('/grades'),
                api.get('/service-categories'),
                api.get('/session-formats'),
                api.get('/curricula'),
            ])

            this.subjects = subjects.data.subjects
            this.grades = grades.data.grades
            this.categories = categories.data.categories
            this.sessionFormats = sessionFormats.data.formats
            this.curricula = curricula.data.curricula
        },

        async searchTutors(params = {}) {
            const { data } = await api.get('/marketplace/tutors', { params: cleanParams(params) })
            this.tutors = data.tutors
            this.meta = data.meta
            return data
        },

        async fetchTutorProfile(id) {
            const { data } = await api.get(`/marketplace/tutors/${id}`)
            this.tutorProfile = data.tutor
            return data.tutor
        },

        async searchSelfPacedCourses(params = {}) {
            const { data } = await api.get('/marketplace/self-paced-courses', { params: cleanParams(params) })
            this.selfPacedCourses = data.courses
            this.selfPacedCoursesMeta = data.meta
            return data
        },

        async fetchSelfPacedCourse(id) {
            const { data } = await api.get(`/marketplace/self-paced-courses/${id}`)
            return data.course
        },

        async fetchSelfPacedCourseFilters() {
            const { data } = await api.get('/marketplace/self-paced-courses/filters')
            this.selfPacedCourseFilters = data
            return data
        },
    },
})
