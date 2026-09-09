import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorServicesStore = defineStore('tutorServices', {
    state: () => ({
        services: [],
        subjects: [],
        // The approved tutor-subject records themselves (id, subject_id,
        // grades) — kept separately from `subjects` (used for the plain
        // subject dropdown) so the service form can look up which grades
        // the tutor is actually approved to teach a given subject for.
        approvedTutorSubjects: [],
        categories: [],
        sessionFormats: [],
        learningResources: [],
        assessmentTypes: [],
        curricula: [],
    }),

    actions: {
        async fetchLookups() {
            const [tutorSubjects, categories, sessionFormats, learningResources, assessmentTypes, curricula] = await Promise.all([
                api.get('/tutor/subjects'),
                api.get('/service-categories'),
                api.get('/session-formats'),
                api.get('/learning-resources'),
                api.get('/assessment-types'),
                api.get('/curricula'),
            ])

            this.approvedTutorSubjects = tutorSubjects.data.subjects.filter((tutorSubject) => tutorSubject.status === 'approved')
            this.subjects = this.approvedTutorSubjects.map((tutorSubject) => tutorSubject.subject)
            this.categories = categories.data.categories
            this.sessionFormats = sessionFormats.data.formats
            this.learningResources = learningResources.data.resources
            this.assessmentTypes = assessmentTypes.data.types
            this.curricula = curricula.data.curricula
        },

        async fetchServices() {
            const { data } = await api.get('/tutor/services')
            this.services = data.services
            return this.services
        },

        async fetchService(id) {
            const { data } = await api.get(`/tutor/services/${id}`)
            return data.service
        },

        async createService(payload) {
            const { data } = await api.post('/tutor/services', payload)
            this.services.unshift(data.service)
            return data.service
        },

        async updateService(id, payload) {
            const { data } = await api.put(`/tutor/services/${id}`, payload)
            const index = this.services.findIndex((service) => service.id === id)
            if (index !== -1) {
                this.services[index] = data.service
            }
            return data.service
        },

        async publishService(id) {
            const { data } = await api.patch(`/tutor/services/${id}/publish`)
            const index = this.services.findIndex((service) => service.id === id)
            if (index !== -1) {
                this.services[index] = data.service
            }
            return data.service
        },

        async pauseService(id) {
            const { data } = await api.patch(`/tutor/services/${id}/pause`)
            const index = this.services.findIndex((service) => service.id === id)
            if (index !== -1) {
                this.services[index] = data.service
            }
            return data.service
        },
    },
})
