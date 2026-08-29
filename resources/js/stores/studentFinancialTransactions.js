import { defineStore } from 'pinia'
import api from '../services/api'

export const useStudentFinancialTransactionsStore = defineStore('studentFinancialTransactions', {
    state: () => ({
        transactions: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),

    actions: {
        async fetchList(params = {}) {
            const { data } = await api.get('/student/financial-transactions', { params })
            this.transactions = data.transactions
            this.meta = data.meta
            return data
        },
    },
})
