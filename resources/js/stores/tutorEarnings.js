import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorEarningsStore = defineStore('tutorEarnings', {
    state: () => ({
        summary: null,
        transactions: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),

    actions: {
        async fetchSummary() {
            const { data } = await api.get('/tutor/earnings')
            this.summary = data
            return data
        },

        async fetchTransactions(params = {}) {
            const { data } = await api.get('/tutor/earnings/transactions', { params })
            this.transactions = data.transactions
            this.meta = data.meta
            return data
        },
    },
})
