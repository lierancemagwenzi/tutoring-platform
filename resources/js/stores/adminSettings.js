import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminSettingsStore = defineStore('adminSettings', {
    state: () => ({
        settings: {},
    }),

    actions: {
        async fetchSettings() {
            const { data } = await api.get('/admin/settings')
            this.settings = data.settings
            return data.settings
        },

        async updateSettings(changed) {
            const { data } = await api.patch('/admin/settings', { settings: changed })
            this.settings = data.settings
            return data.settings
        },
    },
})
