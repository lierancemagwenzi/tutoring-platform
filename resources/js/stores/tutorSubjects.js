import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorSubjectsStore = defineStore('tutorSubjects', {
    state: () => ({
        subjects: [],
        grades: [],
        tutorSubjects: [],
    }),

    actions: {
        async fetchSubjects() {
            const { data } = await api.get('/subjects')
            this.subjects = data.subjects
            return this.subjects
        },

        async fetchGrades() {
            const { data } = await api.get('/grades')
            this.grades = data.grades
            return this.grades
        },

        async fetchTutorSubjects() {
            const { data } = await api.get('/tutor/subjects')
            this.tutorSubjects = data.subjects
            return this.tutorSubjects
        },

        async addTutorSubject(payload) {
            const { data } = await api.post('/tutor/subjects', payload)
            this.tutorSubjects.push(data.subject)
            return data.subject
        },

        async updateTutorSubject(id, payload) {
            const { data } = await api.put(`/tutor/subjects/${id}`, payload)
            const index = this.tutorSubjects.findIndex((tutorSubject) => tutorSubject.id === id)
            if (index !== -1) {
                this.tutorSubjects[index] = data.subject
            }
            return data.subject
        },

        async removeTutorSubject(id) {
            await api.delete(`/tutor/subjects/${id}`)
            this.tutorSubjects = this.tutorSubjects.filter((tutorSubject) => tutorSubject.id !== id)
        },
    },
})
