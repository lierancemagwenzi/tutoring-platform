import { defineStore } from 'pinia'
import api from '../services/api'
import { toFormData } from '../services/formData'

export const useSelfPacedCoursesStore = defineStore('selfPacedCourses', {
    actions: {
        // --- Courses ---

        async fetchCourses() {
            const { data } = await api.get('/tutor/self-paced-courses')
            return data.courses
        },

        async fetchCourse(id) {
            const { data } = await api.get(`/tutor/self-paced-courses/${id}`)
            return data.course
        },

        async fetchPublishingErrors(id) {
            const { data } = await api.get(`/tutor/self-paced-courses/${id}`, { params: { with_publishing_errors: 1 } })
            return data.course.publishing_errors
        },

        async createCourse(payload) {
            const { data } = await api.post('/tutor/self-paced-courses', payload)
            return data.course
        },

        async updateCourse(id, payload) {
            const hasFile = payload.thumbnail instanceof File || payload.promo_video instanceof File
            const body = hasFile ? toFormData({ ...payload, _method: 'PUT' }) : payload
            const { data } = hasFile ? await api.post(`/tutor/self-paced-courses/${id}`, body) : await api.put(`/tutor/self-paced-courses/${id}`, body)
            return data.course
        },

        async deleteCourse(id) {
            await api.delete(`/tutor/self-paced-courses/${id}`)
        },

        async publishCourse(id) {
            const { data } = await api.patch(`/tutor/self-paced-courses/${id}/publish`)
            return data.course
        },

        async unpublishCourse(id) {
            const { data } = await api.patch(`/tutor/self-paced-courses/${id}/unpublish`)
            return data.course
        },

        async makeCoursePrivate(id) {
            const { data } = await api.patch(`/tutor/self-paced-courses/${id}/make-private`)
            return data.course
        },

        async archiveCourse(id) {
            const { data } = await api.patch(`/tutor/self-paced-courses/${id}/archive`)
            return data.course
        },

        // --- Modules ---

        async fetchModules(courseId) {
            const { data } = await api.get(`/tutor/self-paced-courses/${courseId}/modules`)
            return data.modules
        },

        async createModule(courseId, payload) {
            const { data } = await api.post(`/tutor/self-paced-courses/${courseId}/modules`, payload)
            return data.module
        },

        async updateModule(moduleId, payload) {
            const { data } = await api.put(`/tutor/self-paced-modules/${moduleId}`, payload)
            return data.module
        },

        async deleteModule(moduleId) {
            await api.delete(`/tutor/self-paced-modules/${moduleId}`)
        },

        async reorderModules(courseId, moduleIds) {
            const { data } = await api.patch(`/tutor/self-paced-courses/${courseId}/modules/reorder`, { module_ids: moduleIds })
            return data.modules
        },

        // --- Activities ---

        async createActivity(moduleId, payload) {
            const { data } = await api.post(`/tutor/self-paced-modules/${moduleId}/activities`, payload)
            return data.activity
        },

        async updateActivity(activityId, payload) {
            const { data } = await api.put(`/tutor/self-paced-activities/${activityId}`, payload)
            return data.activity
        },

        async deleteActivity(activityId) {
            await api.delete(`/tutor/self-paced-activities/${activityId}`)
        },

        // --- Activity attachments ---

        async addAttachment(activityId, payload) {
            const form = toFormData(payload)
            const { data } = await api.post(`/tutor/self-paced-activities/${activityId}/attachments`, form)
            return data.attachment
        },

        async deleteAttachment(attachmentId) {
            await api.delete(`/tutor/self-paced-activity-attachments/${attachmentId}`)
        },

        // --- Assessments ---

        async createAssessment(moduleId, payload) {
            const { data } = await api.post(`/tutor/self-paced-modules/${moduleId}/assessments`, payload)
            return data.assessment
        },

        async updateAssessment(assessmentId, payload) {
            const { data } = await api.put(`/tutor/self-paced-assessments/${assessmentId}`, payload)
            return data.assessment
        },

        async deleteAssessment(assessmentId) {
            await api.delete(`/tutor/self-paced-assessments/${assessmentId}`)
        },

        // --- Combined module content ordering ---

        async reorderModuleContent(moduleId, items) {
            const { data } = await api.patch(`/tutor/self-paced-modules/${moduleId}/content/reorder`, { items })
            return data.module
        },

        // --- Discount codes ---

        async fetchDiscountCodes(courseId) {
            const { data } = await api.get(`/tutor/self-paced-courses/${courseId}/discount-codes`)
            return data.discount_codes
        },

        async createDiscountCode(courseId, payload) {
            const { data } = await api.post(`/tutor/self-paced-courses/${courseId}/discount-codes`, payload)
            return data.discount_code
        },

        async updateDiscountCode(codeId, payload) {
            const { data } = await api.put(`/tutor/self-paced-discount-codes/${codeId}`, payload)
            return data.discount_code
        },

        async deleteDiscountCode(codeId) {
            await api.delete(`/tutor/self-paced-discount-codes/${codeId}`)
        },

        async previewDiscountCode(courseId, code) {
            const { data } = await api.post(`/tutor/self-paced-courses/${courseId}/discount-codes/preview`, { code })
            return data
        },

        // --- H5P content tagged for self-paced course use ---
        // Never the same list as Tutor-Led Learning's /tutor/h5p-content —
        // this is scoped to only what's been tagged for self-paced use.

        async fetchH5pContents() {
            const { data } = await api.get('/tutor/self-paced-h5p-contents')
            return data.contents
        },

        async registerH5pContent(payload) {
            const { data } = await api.post('/tutor/self-paced-h5p-contents', payload)
            return data.content
        },
    },
})
