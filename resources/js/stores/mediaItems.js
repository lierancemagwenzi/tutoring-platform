import { defineStore } from 'pinia'
import api from '../services/api'
import { toFormData } from '../services/formData'

export const useMediaItemsStore = defineStore('mediaItems', {
    actions: {
        async fetchMediaItems(lessonBlockId) {
            const { data } = await api.get(`/tutor/lesson-blocks/${lessonBlockId}/media-items`)
            return data.media_items
        },

        async createMediaItem(lessonBlockId, payload) {
            const form = toFormData(payload)
            const { data } = await api.post(`/tutor/lesson-blocks/${lessonBlockId}/media-items`, form)
            return data.media_item
        },

        async updateMediaItem(id, payload) {
            const form = toFormData({ ...payload, _method: 'PUT' })
            const { data } = await api.post(`/tutor/media-items/${id}`, form)
            return data.media_item
        },

        async deleteMediaItem(id) {
            await api.delete(`/tutor/media-items/${id}`)
        },

        async reorderMediaItems(lessonBlockId, mediaItemIds) {
            const { data } = await api.patch(`/tutor/lesson-blocks/${lessonBlockId}/media-items/reorder`, {
                media_item_ids: mediaItemIds,
            })
            return data.media_items
        },
    },
})
