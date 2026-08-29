import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminQuickSetupStore = defineStore('adminQuickSetup', {
    state: () => ({
        checklist: [],
        progress: { completed: 0, total: 0, required_completed: 0, required_total: 0, ready_for_production: false },
    }),

    actions: {
        async fetchChecklist() {
            const { data } = await api.get('/admin/quick-setup')
            this.checklist = data.checklist
            this.progress = data.progress
            return data
        },

        async sendTestEmail() {
            const { data } = await api.post('/admin/quick-setup/email/test')
            return data
        },
    },
})
