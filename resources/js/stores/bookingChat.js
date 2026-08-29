import { defineStore } from 'pinia'
import api from '../services/api'

export const useBookingChatStore = defineStore('bookingChat', {
    state: () => ({
        messages: [],
    }),

    actions: {
        async fetchMessages(basePath) {
            const { data } = await api.get(`${basePath}/messages`)
            this.messages = data.messages
            return data.messages
        },

        async sendMessage(basePath, body) {
            const { data } = await api.post(`${basePath}/messages`, { body })
            this.messages.push(data.message)
            return data.message
        },

        reset() {
            this.messages = []
        },
    },
})
