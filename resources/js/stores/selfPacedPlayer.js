import { defineStore } from 'pinia'
import api from '../services/api'

// Holds shared, reactive course state — unlike most stores in this app
// (stateless action-wrappers), the player's header/sidebar/navigation/footer
// all need the same live, progress-updating course object at once without
// prop-drilling it through every routed page.
export const useSelfPacedPlayerStore = defineStore('selfPacedPlayer', {
    state: () => ({
        course: null,
    }),

    getters: {
        // Every activity/assessment across every module, in display order —
        // what Previous/Next navigation walks.
        orderedItems: (state) => {
            if (!state.course) return []

            return state.course.modules.flatMap((module) =>
                [
                    ...module.activities.map((activity) => ({ ...activity, kind: 'activity', moduleId: module.id, moduleState: module.state })),
                    ...module.assessments.map((assessment) => ({ ...assessment, kind: 'assessment', moduleId: module.id, moduleState: module.state })),
                ].sort((a, b) => a.position - b.position),
            )
        },
    },

    actions: {
        async fetchCourse(courseId) {
            const { data } = await api.get(`/student/self-paced-courses/${courseId}`)
            this.course = data.course
            return data.course
        },

        async fetchActivity(courseId, activityId) {
            const { data } = await api.get(`/student/self-paced-courses/${courseId}/activities/${activityId}`)
            return data.activity
        },

        async completeActivity(courseId, activityId, timeSpentSeconds = null) {
            await api.patch(`/student/self-paced-courses/${courseId}/activities/${activityId}/complete`, {
                time_spent_seconds: timeSpentSeconds,
            })
            // Progress/lock state may have changed (this activity's module,
            // or the next one, could now be complete/unlocked) — refetch so
            // every chrome component reflects it immediately.
            return this.fetchCourse(courseId)
        },

        async fetchAssessment(courseId, assessmentId) {
            const { data } = await api.get(`/student/self-paced-courses/${courseId}/assessments/${assessmentId}`)
            return data.assessment
        },

        async fetchH5pPlayerModel(courseId, assessmentId) {
            const { data } = await api.get(`/student/self-paced-courses/${courseId}/assessments/${assessmentId}/h5p-player-model`)
            return data
        },

        clear() {
            this.course = null
        },
    },
})
