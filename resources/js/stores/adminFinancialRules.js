import { defineStore } from 'pinia'
import api from '../services/api'

function cleanParams(params) {
    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined))
}

export const useAdminFinancialRulesStore = defineStore('adminFinancialRules', {
    state: () => ({
        global: null,
        overrides: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),

    actions: {
        async fetchGlobal() {
            const { data } = await api.get('/admin/financial-rules/global')
            this.global = data.rule
            return data.rule
        },

        async updateGlobal(payload) {
            const { data } = await api.patch('/admin/financial-rules/global', payload)
            this.global = data.rule
            return data.rule
        },

        async fetchOverrides(params = {}) {
            const { data } = await api.get('/admin/financial-rules', { params: cleanParams(params) })
            this.overrides = data.rules
            this.meta = data.meta
            return data
        },

        async createOverride(payload) {
            const { data } = await api.post('/admin/financial-rules', payload)
            return data.rule
        },

        async updateOverride(id, payload) {
            const { data } = await api.patch(`/admin/financial-rules/${id}`, payload)
            return data.rule
        },

        async activateOverride(id) {
            const { data } = await api.post(`/admin/financial-rules/${id}/activate`)
            return data.rule
        },

        async deactivateOverride(id) {
            const { data } = await api.post(`/admin/financial-rules/${id}/deactivate`)
            return data.rule
        },
    },
})
