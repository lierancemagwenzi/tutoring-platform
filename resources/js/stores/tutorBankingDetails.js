import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorBankingDetailsStore = defineStore('tutorBankingDetails', {
    state: () => ({
        bankAccount: null,
    }),

    actions: {
        async fetch() {
            const { data } = await api.get('/tutor/banking-details')
            this.bankAccount = data.bank_account
            return data.bank_account
        },

        async save(payload) {
            const { data } = await api.put('/tutor/banking-details', payload)
            this.bankAccount = data.bank_account
            return data.bank_account
        },
    },
})
