import { defineStore } from 'pinia'
import api from '../services/api'
import { toFormData } from '../services/formData'

export const useActivityAttachmentsStore = defineStore('activityAttachments', {
    actions: {
        async fetchAttachments(activityId) {
            const { data } = await api.get(`/tutor/learning-activities/${activityId}/attachments`)
            return data.attachments
        },

        async createAttachment(activityId, payload) {
            const form = toFormData(payload)
            const { data } = await api.post(`/tutor/learning-activities/${activityId}/attachments`, form)
            return data.attachment
        },

        async updateAttachment(id, payload) {
            const form = toFormData({ ...payload, _method: 'PUT' })
            const { data } = await api.post(`/tutor/activity-attachments/${id}`, form)
            return data.attachment
        },

        async deleteAttachment(id) {
            await api.delete(`/tutor/activity-attachments/${id}`)
        },

        async reorderAttachments(activityId, attachmentIds) {
            const { data } = await api.patch(`/tutor/learning-activities/${activityId}/attachments/reorder`, {
                attachment_ids: attachmentIds,
            })
            return data.attachments
        },
    },
})
