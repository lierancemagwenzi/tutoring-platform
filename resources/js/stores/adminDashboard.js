import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminDashboardStore = defineStore('adminDashboard', {
    state: () => ({
        overview: null,
        actionRequired: [],
    }),

    actions: {
        async fetchOverview() {
            const { data } = await api.get('/admin/dashboard')
            this.overview = data.overview
            this.actionRequired = data.action_required
            return data
        },
    },
})
