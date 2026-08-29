import { defineStore } from 'pinia'
import api from '../services/api'

export const useFaqsStore = defineStore('faqs', {
    state: () => ({
        faqs: [],
    }),

    actions: {
        async fetchList() {
            const { data } = await api.get('/faqs')
            this.faqs = data.faqs
            return data.faqs
        },
    },
})
