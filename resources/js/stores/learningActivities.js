import { defineStore } from 'pinia'
import api from '../services/api'

export const useLearningActivitiesStore = defineStore('learningActivities', {
    actions: {
        async fetchActivity(id) {
            const { data } = await api.get(`/tutor/learning-activities/${id}`)
            return data.activity
        },

        async updateActivity(id, payload) {
            const { data } = await api.put(`/tutor/learning-activities/${id}`, payload)
            return data.activity
        },

        async deleteActivity(id) {
            await api.delete(`/tutor/learning-activities/${id}`)
        },
    },
})
