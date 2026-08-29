import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminActivityLogStore = defineStore('adminActivityLog', {
    state: () => ({
        logs: [],
        meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    }),

    actions: {
        async fetchList(params = {}) {
            const { data } = await api.get('/admin/activity-log', { params })
            this.logs = data.logs
            this.meta = data.meta
            return data
        },
    },
})
