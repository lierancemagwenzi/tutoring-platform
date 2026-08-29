import { defineStore } from 'pinia'
import api from '../services/api'
import { toFormData } from '../services/formData'

export const useCoursesStore = defineStore('courses', {
    state: () => ({
        courses: [],
        tutorSubjects: [],
        curricula: [],
    }),

    getters: {
        approvedTutorSubjects: (state) => state.tutorSubjects.filter((tutorSubject) => tutorSubject.status === 'approved'),
    },

    actions: {
        async fetchLookups() {
            const [tutorSubjects, curricula] = await Promise.all([
                api.get('/tutor/subjects'),
                api.get('/curricula'),
            ])

            this.tutorSubjects = tutorSubjects.data.subjects
            this.curricula = curricula.data.curricula
        },

        async fetchCourses() {
            const { data } = await api.get('/tutor/courses')
            this.courses = data.courses
            return this.courses
        },

        async fetchCourse(id) {
            const { data } = await api.get(`/tutor/courses/${id}`)
            return data.course
        },

        async createCourse(payload) {
            const form = toFormData(payload)
            const { data } = await api.post('/tutor/courses', form)
            this.courses.unshift(data.course)
            return data.course
        },

        async updateCourse(id, payload) {
            const form = toFormData({ ...payload, _method: 'PUT' })
            const { data } = await api.post(`/tutor/courses/${id}`, form)
            const index = this.courses.findIndex((course) => course.id === id)
            if (index !== -1) {
                this.courses[index] = data.course
            }
            return data.course
        },

        async archiveCourse(id) {
            const { data } = await api.patch(`/tutor/courses/${id}/archive`)
            const index = this.courses.findIndex((course) => course.id === id)
            if (index !== -1) {
                this.courses[index] = data.course
            }
            return data.course
        },

        async deleteCourse(id) {
            await api.delete(`/tutor/courses/${id}`)
            this.courses = this.courses.filter((course) => course.id !== id)
        },

        // --- Chapters ---

        async fetchChapters(courseId) {
            const { data } = await api.get(`/tutor/courses/${courseId}/chapters`)
            return data.chapters
        },

        async createChapter(courseId, payload) {
            const { data } = await api.post(`/tutor/courses/${courseId}/chapters`, payload)
            return data.chapter
        },

        async updateChapter(chapterId, payload) {
            const { data } = await api.put(`/tutor/chapters/${chapterId}`, payload)
            return data.chapter
        },

        async deleteChapter(chapterId) {
            await api.delete(`/tutor/chapters/${chapterId}`)
        },

        async reorderChapters(courseId, chapterIds) {
            const { data } = await api.patch(`/tutor/courses/${courseId}/chapters/reorder`, { chapter_ids: chapterIds })
            return data.chapters
        },

        // --- Lessons ---

        async fetchLessons(chapterId) {
            const { data } = await api.get(`/tutor/chapters/${chapterId}/lessons`)
            return data.lessons
        },

        async createLesson(chapterId, payload) {
            const { data } = await api.post(`/tutor/chapters/${chapterId}/lessons`, payload)
            return data.lesson
        },

        async updateLesson(lessonId, payload) {
            const { data } = await api.put(`/tutor/lessons/${lessonId}`, payload)
            return data.lesson
        },

        async deleteLesson(lessonId) {
            await api.delete(`/tutor/lessons/${lessonId}`)
        },

        async reorderLessons(chapterId, lessonIds) {
            const { data } = await api.patch(`/tutor/chapters/${chapterId}/lessons/reorder`, { lesson_ids: lessonIds })
            return data.lessons
        },

        // --- Lesson Blocks ---

        async fetchBlocks(lessonId) {
            const { data } = await api.get(`/tutor/lessons/${lessonId}/blocks`)
            return data.blocks
        },

        async fetchBlock(blockId) {
            const { data } = await api.get(`/tutor/lesson-blocks/${blockId}`)
            return data.block
        },

        async createBlock(lessonId, payload) {
            const form = toFormData(payload)
            const { data } = await api.post(`/tutor/lessons/${lessonId}/blocks`, form)
            return data.block
        },

        async updateBlock(blockId, payload) {
            const form = toFormData({ ...payload, _method: 'PUT' })
            const { data } = await api.post(`/tutor/lesson-blocks/${blockId}`, form)
            return data.block
        },

        async deleteBlock(blockId) {
            await api.delete(`/tutor/lesson-blocks/${blockId}`)
        },

        async duplicateBlock(blockId) {
            const { data } = await api.post(`/tutor/lesson-blocks/${blockId}/duplicate`)
            return data.block
        },

        async reorderBlocks(lessonId, blockIds) {
            const { data } = await api.patch(`/tutor/lessons/${lessonId}/blocks/reorder`, { block_ids: blockIds })
            return data.blocks
        },
    },
})
