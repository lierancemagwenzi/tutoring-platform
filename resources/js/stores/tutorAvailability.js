import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorAvailabilityStore = defineStore('tutorAvailability', {
    state: () => ({
        dates: [],
    }),

    getters: {
        slotsByDate: (state) => (date) => state.dates.find((entry) => entry.date === date)?.slots ?? [],
        datesWithAvailability: (state) => new Set(state.dates.map((entry) => entry.date)),
    },

    actions: {
        async fetchMonth(month) {
            const { data } = await api.get('/tutor/availability', { params: { month } })
            this.dates = data.dates
            return this.dates
        },

        mergeDate(date) {
            const index = this.dates.findIndex((entry) => entry.date === date.date)
            if (index !== -1) {
                this.dates[index] = date
            } else {
                this.dates.push(date)
            }
        },

        async addSlot(payload) {
            const { data } = await api.post('/tutor/availability', payload)
            this.mergeDate(data.date)
            return data.date
        },

        async updateSlot(id, payload) {
            const { data } = await api.put(`/tutor/availability/${id}`, payload)
            this.mergeDate(data.date)
            return data.date
        },

        async removeSlot(id, date) {
            const { data } = await api.delete(`/tutor/availability/${id}`)
            if (data.date) {
                this.mergeDate(data.date)
            } else {
                this.dates = this.dates.filter((entry) => entry.date !== date)
            }
        },
    },
})
