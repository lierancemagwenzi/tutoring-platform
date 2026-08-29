import { defineStore } from 'pinia'
import api from '../services/api'

// Mirrors stores/attempts.js's shape exactly, scoped to self-paced
// assessments instead of tutor-led lesson blocks.
export const useSelfPacedAssessmentAttemptsStore = defineStore('selfPacedAssessmentAttempts', {
    actions: {
        async startOrResumeAttempt(courseId, assessmentId) {
            const { data } = await api.post(`/student/self-paced-courses/${courseId}/assessments/${assessmentId}/attempts`)
            return data.attempt
        },

        async markInProgress(courseId, assessmentId, attemptId) {
            const { data } = await api.patch(
                `/student/self-paced-courses/${courseId}/assessments/${assessmentId}/attempts/${attemptId}/in-progress`,
            )
            return data.attempt
        },

        async completeAttempt(courseId, assessmentId, attemptId, rawResult) {
            const { data } = await api.post(
                `/student/self-paced-courses/${courseId}/assessments/${assessmentId}/attempts/${attemptId}/complete`,
                { raw_result: rawResult },
            )
            return data.attempt
        },
    },
})
