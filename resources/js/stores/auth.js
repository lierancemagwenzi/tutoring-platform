import { defineStore } from 'pinia'
import api from '../services/api'

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('auth_user') ?? 'null'),
        token: localStorage.getItem('auth_token'),
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token),
    },

    actions: {
        async login(email, password) {
            const { data } = await api.post('/login', { email, password })
            this.setSession(data.user, data.token)
            return data.user
        },

        async fetchUser() {
            const { data } = await api.get('/me')
            this.user = data.user
            localStorage.setItem('auth_user', JSON.stringify(data.user))
            return data.user
        },

        async verifyEmail(otp) {
            const { data } = await api.post('/email/verify', { otp })
            this.user = data.user
            localStorage.setItem('auth_user', JSON.stringify(data.user))
            return data.user
        },

        async resendVerification() {
            const { data } = await api.post('/email/resend')
            return data
        },

        async forgotPassword(email) {
            const { data } = await api.post('/forgot-password', { email })
            return data
        },

        async resetPassword(payload) {
            const { data } = await api.post('/reset-password', payload)
            return data
        },

        async acceptAdminInvite(payload) {
            const { data } = await api.post('/accept-admin-invite', payload)
            return data
        },

        async logout() {
            try {
                await api.post('/logout')
            } catch {
                // Session may already be invalid/expired server-side (e.g. a
                // 401) — that's fine, clearing local state below is what
                // actually logs the user out; it must not block on this.
            } finally {
                this.clearSession()
            }
        },

        markTutorOnboardingComplete() {
            if (this.user?.tutor_profile) {
                this.user.tutor_profile.onboarding_complete = true
                localStorage.setItem('auth_user', JSON.stringify(this.user))
            }
        },

        setSession(user, token) {
            this.user = user
            this.token = token
            localStorage.setItem('auth_user', JSON.stringify(user))
            localStorage.setItem('auth_token', token)
        },

        clearSession() {
            this.user = null
            this.token = null
            localStorage.removeItem('auth_user')
            localStorage.removeItem('auth_token')
        },
    },
})
