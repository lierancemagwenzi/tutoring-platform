import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorApplicationStore = defineStore('tutorApplication', {
    state: () => ({
        application: null,
    }),

    actions: {
        async fetch() {
            const { data } = await api.get('/tutor/application')
            this.application = data.application
            return this.application
        },

        async saveBasicInfo({ displayName, bio, yearsExperience, occupation, languages, profilePhoto }) {
            const form = new FormData()
            form.append('display_name', displayName)
            form.append('bio', bio)
            form.append('years_experience', yearsExperience)
            form.append('occupation', occupation)
            languages.forEach((language) => form.append('languages[]', language))
            if (profilePhoto) {
                form.append('profile_photo', profilePhoto)
            }

            const { data } = await api.post('/tutor/application/basic-info', form)
            this.application = data.application
            return this.application
        },

        async saveProfessionalProfile({ teachingStyle, aboutMe, whyChooseMe }) {
            const { data } = await api.post('/tutor/application/professional-profile', {
                teaching_style: teachingStyle,
                about_me: aboutMe,
                why_choose_me: whyChooseMe,
            })
            this.application = data.application
            return this.application
        },

        async addQualification(payload) {
            const { data } = await api.post('/tutor/application/qualifications', payload)
            this.application.qualifications.push(data.qualification)
            return data.qualification
        },

        async updateQualification(id, payload) {
            const { data } = await api.put(`/tutor/application/qualifications/${id}`, payload)
            const index = this.application.qualifications.findIndex((qualification) => qualification.id === id)
            if (index !== -1) {
                this.application.qualifications[index] = data.qualification
            }
            return data.qualification
        },

        async deleteQualification(id) {
            await api.delete(`/tutor/application/qualifications/${id}`)
            this.application.qualifications = this.application.qualifications.filter(
                (qualification) => qualification.id !== id,
            )
        },

        async uploadIdentityDocument(file) {
            const form = new FormData()
            form.append('file', file)
            const { data } = await api.post('/tutor/application/identity-document', form)
            this.application = data.application
            return this.application
        },

        async addDocument(type, file) {
            const form = new FormData()
            form.append('type', type)
            form.append('file', file)
            const { data } = await api.post('/tutor/application/documents', form)
            this.application.documents.push(data.document)
            return data.document
        },

        async replaceDocument(id, file) {
            const form = new FormData()
            form.append('file', file)
            const { data } = await api.post(`/tutor/application/documents/${id}/replace`, form)
            const index = this.application.documents.findIndex((document) => document.id === id)
            if (index !== -1) {
                this.application.documents[index] = data.document
            }
            return data.document
        },

        async deleteDocument(id) {
            await api.delete(`/tutor/application/documents/${id}`)
            this.application.documents = this.application.documents.filter((document) => document.id !== id)
        },

        async submit() {
            return api.post('/tutor/application/submit')
        },
    },
})
