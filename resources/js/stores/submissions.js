import { defineStore } from 'pinia'
import api from '../services/api'
import { toFormData } from '../services/formData'

export const useSubmissionsStore = defineStore('submissions', {
    actions: {
        // --- Student ---

        async fetchSubmissions(bookingId, lessonBlockId) {
            const { data } = await api.get(`/bookings/${bookingId}/lesson-blocks/${lessonBlockId}/submissions`)
            return data.submissions
        },

        async startOrResumeDraft(bookingId, lessonBlockId) {
            const { data } = await api.post(`/bookings/${bookingId}/lesson-blocks/${lessonBlockId}/submissions`)
            return data.submission
        },

        async updateDraft(submissionId, payload) {
            const { data } = await api.patch(`/submissions/${submissionId}`, payload)
            return data.submission
        },

        async submitDraft(submissionId) {
            const { data } = await api.post(`/submissions/${submissionId}/submit`)
            return data.submission
        },

        async addAttachment(submissionId, payload) {
            const form = toFormData(payload)
            const { data } = await api.post(`/submissions/${submissionId}/attachments`, form)
            return data.attachment
        },

        async removeAttachment(attachmentId) {
            await api.delete(`/submission-attachments/${attachmentId}`)
        },

        // --- Tutor ---

        async fetchSubmissionsForBlock(sessionLessonBlockId) {
            const { data } = await api.get(`/tutor/session-lesson-blocks/${sessionLessonBlockId}/submissions`)
            return data.submissions
        },

        async fetchSubmission(submissionId) {
            const { data } = await api.get(`/tutor/submissions/${submissionId}`)
            return data.submission
        },

        async markUnderReview(submissionId) {
            const { data } = await api.patch(`/tutor/submissions/${submissionId}/review`)
            return data.submission
        },

        async returnForRevision(submissionId) {
            const { data } = await api.patch(`/tutor/submissions/${submissionId}/return`)
            return data.submission
        },

        async gradeSubmission(submissionId, payload) {
            const { data } = await api.patch(`/tutor/submissions/${submissionId}/grade`, payload)
            return data.submission
        },

        async publishSubmission(submissionId) {
            const { data } = await api.patch(`/tutor/submissions/${submissionId}/publish`)
            return data.submission
        },

        async addFeedbackAttachment(submissionId, payload) {
            const form = toFormData(payload)
            const { data } = await api.post(`/tutor/submissions/${submissionId}/feedback-attachments`, form)
            return data.attachment
        },

        async removeFeedbackAttachment(attachmentId) {
            await api.delete(`/tutor/feedback-attachments/${attachmentId}`)
        },
    },
})
