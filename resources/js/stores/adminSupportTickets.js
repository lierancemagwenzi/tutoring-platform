import { defineStore } from 'pinia'
import api from '../services/api'

function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

export const useAdminSupportTicketsStore = defineStore('adminSupportTickets', {
    state: () => ({
        tickets: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
        ticket: null,
        comments: [],
    }),

    actions: {
        async fetchList(params = {}) {
            const { data } = await api.get('/admin/support-tickets', { params: cleanParams(params) })
            this.tickets = data.tickets
            this.meta = data.meta
            return data
        },

        async fetchDetail(ticketId) {
            const { data } = await api.get(`/admin/support-tickets/${ticketId}`)
            this.ticket = data.ticket
            this.comments = data.comments
            return data
        },

        async updateStatus(ticketId, status) {
            const { data } = await api.patch(`/admin/support-tickets/${ticketId}/status`, { status })
            this.ticket = data.ticket
            return data.ticket
        },

        async addComment(ticketId, body) {
            const { data } = await api.post(`/admin/support-tickets/${ticketId}/comments`, { body })
            this.comments.push(data.comment)
            return data.comment
        },
    },
})
