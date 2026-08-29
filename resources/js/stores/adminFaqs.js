import { defineStore } from 'pinia'
import api from '../services/api'

function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

export const useAdminFaqsStore = defineStore('adminFaqs', {
    state: () => ({
        faqs: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),

    actions: {
        async fetchList(params = {}) {
            const { data } = await api.get('/admin/faqs', { params: cleanParams(params) })
            this.faqs = data.faqs
            this.meta = data.meta
            return data
        },

        async create(payload) {
            const { data } = await api.post('/admin/faqs', payload)
            return data.faq
        },

        async update(id, payload) {
            const { data } = await api.patch(`/admin/faqs/${id}`, payload)
            const index = this.faqs.findIndex((faq) => faq.id === id)
            if (index !== -1) {
                this.faqs[index] = data.faq
            }
            return data.faq
        },

        async remove(id) {
            await api.delete(`/admin/faqs/${id}`)
            this.faqs = this.faqs.filter((faq) => faq.id !== id)
        },
    },
})
