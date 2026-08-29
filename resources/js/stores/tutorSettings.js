import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorSettingsStore = defineStore('tutorSettings', {
    state: () => ({
        connectedAccounts: [],
        meetingProvider: {
            selected: null,
            available: [],
        },
    }),

    actions: {
        async fetchConnectedAccounts() {
            const { data } = await api.get('/tutor/settings/connected-accounts')
            this.connectedAccounts = data.accounts
            return this.connectedAccounts
        },

        async getProviderRedirectUrl(provider) {
            const { data } = await api.get(`/tutor/settings/connected-accounts/${provider}/redirect`)
            return data.url
        },

        async disconnect(id) {
            await api.delete(`/tutor/settings/connected-accounts/${id}`)
            this.connectedAccounts = this.connectedAccounts.filter((account) => account.id !== id)
        },

        async fetchMeetingProviderSettings() {
            const { data } = await api.get('/tutor/settings/meeting-providers')
            this.meetingProvider = data
            return this.meetingProvider
        },

        async selectMeetingProvider(provider) {
            const { data } = await api.put('/tutor/settings/meeting-providers', { provider })
            this.meetingProvider.selected = data.selected
        },
    },
})
