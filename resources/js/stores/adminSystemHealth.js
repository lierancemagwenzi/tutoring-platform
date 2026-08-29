import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminSystemHealthStore = defineStore('adminSystemHealth', {
    state: () => ({
        checks: {},
    }),

    actions: {
        async fetchChecks() {
            const { data } = await api.get('/admin/system-health')
            this.checks = data.checks
            return data.checks
        },
    },
})
