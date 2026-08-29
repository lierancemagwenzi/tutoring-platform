import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminAccountsStore = defineStore('adminAccounts', {
    state: () => ({
        admins: [],
    }),

    actions: {
        async fetchList() {
            const { data } = await api.get('/admin/admins')
            this.admins = data.admins
            return data.admins
        },

        async invite(payload) {
            const { data } = await api.post('/admin/admins', payload)
            this.admins.unshift(data.admin)
            return data.admin
        },

        async resendInvite(id) {
            await api.post(`/admin/admins/${id}/resend-invite`)
        },

        async deactivate(id) {
            const { data } = await api.post(`/admin/admins/${id}/deactivate`)
            this.replace(data.admin)
            return data.admin
        },

        async activate(id) {
            const { data } = await api.post(`/admin/admins/${id}/activate`)
            this.replace(data.admin)
            return data.admin
        },

        async remove(id) {
            await api.delete(`/admin/admins/${id}`)
            this.admins = this.admins.filter((admin) => admin.id !== id)
        },

        replace(admin) {
            const index = this.admins.findIndex((item) => item.id === admin.id)
            if (index !== -1) {
                this.admins[index] = admin
            }
        },
    },
})
