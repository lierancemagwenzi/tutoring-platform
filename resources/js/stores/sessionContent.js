import { defineStore } from 'pinia'
import api from '../services/api'

export const useSessionContentStore = defineStore('sessionContent', {
    actions: {
        // --- Session Lessons ---

        async fetchSessionLessons(sessionId) {
            const { data } = await api.get(`/tutor/sessions/${sessionId}/lessons`)
            return data.session_lessons
        },

        async assignLesson(sessionId, lessonId) {
            const { data } = await api.post(`/tutor/sessions/${sessionId}/lessons`, { lesson_id: lessonId })
            return data.session_lesson
        },

        async removeLesson(sessionId, sessionLessonId) {
            await api.delete(`/tutor/sessions/${sessionId}/lessons/${sessionLessonId}`)
        },

        async reorderLessons(sessionId, sessionLessonIds) {
            const { data } = await api.patch(`/tutor/sessions/${sessionId}/lessons/reorder`, {
                session_lesson_ids: sessionLessonIds,
            })
            return data.session_lessons
        },

        // --- Session Lesson Blocks ---

        async fetchLessonContent(sessionLessonId) {
            const { data } = await api.get(`/tutor/session-lessons/${sessionLessonId}/blocks`)
            return data
        },

        async assignBlock(sessionLessonId, payload) {
            const { data } = await api.post(`/tutor/session-lessons/${sessionLessonId}/blocks`, payload)
            return data.session_lesson_block
        },

        async updateBlockAvailability(sessionLessonId, sessionLessonBlockId, payload) {
            const { data } = await api.patch(`/tutor/session-lessons/${sessionLessonId}/blocks/${sessionLessonBlockId}`, payload)
            return data.session_lesson_block
        },

        async removeBlock(sessionLessonId, sessionLessonBlockId) {
            await api.delete(`/tutor/session-lessons/${sessionLessonId}/blocks/${sessionLessonBlockId}`)
        },
    },
})
