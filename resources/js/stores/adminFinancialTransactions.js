import { defineStore } from 'pinia'
import api from '../services/api'

function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

export const useAdminFinancialTransactionsStore = defineStore('adminFinancialTransactions', {
    state: () => ({
        transactions: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),

    actions: {
        async fetchTransactions(params = {}) {
            const { data } = await api.get('/admin/financial-transactions', { params: cleanParams(params) })
            this.transactions = data.transactions
            this.meta = data.meta
            return data
        },

        async markPaid(transactionId) {
            const { data } = await api.post(`/admin/financial-transactions/${transactionId}/mark-paid`)
            this.replace(data.transaction)
            return data.transaction
        },

        async updatePayoutStatus(transactionId, status) {
            const { data } = await api.patch(`/admin/financial-transactions/${transactionId}/payout-status`, { status })
            this.replace(data.transaction)
            return data.transaction
        },

        replace(transaction) {
            const index = this.transactions.findIndex((item) => item.id === transaction.id)
            if (index !== -1) {
                this.transactions[index] = transaction
            }
        },
    },
})
