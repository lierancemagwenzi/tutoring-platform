import { defineStore } from 'pinia'
import api from '../services/api'

export const useAttemptsStore = defineStore('attempts', {
    actions: {
        // --- Student ---

        async fetchAttempts(bookingId, lessonBlockId) {
            const { data } = await api.get(`/bookings/${bookingId}/lesson-blocks/${lessonBlockId}/attempts`)
            return data.attempts
        },

        async startOrResumeAttempt(bookingId, lessonBlockId) {
            const { data } = await api.post(`/bookings/${bookingId}/lesson-blocks/${lessonBlockId}/attempts`)
            return data.attempt
        },

        async markInProgress(attemptId) {
            const { data } = await api.patch(`/attempts/${attemptId}/in-progress`)
            return data.attempt
        },

        async completeAttempt(attemptId, rawResult) {
            const { data } = await api.post(`/attempts/${attemptId}/complete`, { raw_result: rawResult })
            return data.attempt
        },

        // --- Tutor ---

        async fetchAttemptsForBlock(sessionLessonBlockId) {
            const { data } = await api.get(`/tutor/session-lesson-blocks/${sessionLessonBlockId}/attempts`)
            return data.attempts
        },

        async fetchAttempt(attemptId) {
            const { data } = await api.get(`/tutor/attempts/${attemptId}`)
            return data.attempt
        },
    },
})
