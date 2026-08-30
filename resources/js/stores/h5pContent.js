import { defineStore } from 'pinia'
import api from '../services/api'

export const useH5pContentStore = defineStore('h5pContent', {
    actions: {
        async fetchLibrary(filters = {}) {
            const { data } = await api.get('/tutor/h5p-content', { params: filters })
            return data.contents
        },

        async fetchNewEditorModel() {
            const { data } = await api.get('/tutor/h5p-content/editor-model')
            return data
        },

        async fetchEditorModel(contentId) {
            const { data } = await api.get(`/tutor/h5p-content/${contentId}/editor-model`)
            return data
        },

        async fetchPlayerModel(contentId) {
            const { data } = await api.get(`/tutor/h5p-content/${contentId}/player-model`)
            return data
        },

        async saveContent(contentId, payload) {
            const { data } = contentId
                ? await api.patch(`/tutor/h5p-content/${contentId}`, payload)
                : await api.post('/tutor/h5p-content', payload)
            return data
        },

        async deleteContent(contentId) {
            await api.delete(`/tutor/h5p-content/${contentId}`)
        },

        async importPackage(file) {
            const formData = new FormData()
            formData.append('file', file)
            const { data } = await api.post('/tutor/h5p-content/import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            return data
        },

        exportUrl(contentId) {
            return `/api/tutor/h5p-content/${contentId}/export`
        },
    },
})
