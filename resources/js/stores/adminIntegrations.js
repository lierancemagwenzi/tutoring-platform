import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminIntegrationsStore = defineStore('adminIntegrations', {
    state: () => ({
        integrations: {},
    }),

    actions: {
        async fetchStatus() {
            const { data } = await api.get('/admin/integrations')
            this.integrations = data.integrations
            return data.integrations
        },
    },
})
