import { defineStore } from 'pinia'
import api from '../services/api'

export const useTutorBookingsStore = defineStore('tutorBookings', {
    state: () => ({
        requests: [],
        sessions: [],
        currentSession: null,
        currentBooking: null,
        bookingProgress: null,
        bookingSessions: [],
    }),

    actions: {
        async fetchRequests(status) {
            const { data } = await api.get('/tutor/booking-requests', { params: status ? { status } : {} })
            this.requests = data.bookings
            return this.requests
        },

        async fetchRequest(id) {
            const { data } = await api.get(`/tutor/booking-requests/${id}`)
            return data.booking
        },

        async acceptRequest(id) {
            const { data } = await api.patch(`/tutor/booking-requests/${id}/accept`)
            this.updateRequestLocal(data.booking)
            return data.booking
        },

        async rejectRequest(id) {
            const { data } = await api.patch(`/tutor/booking-requests/${id}/reject`)
            this.updateRequestLocal(data.booking)
            return data.booking
        },

        async fetchSessions() {
            const { data } = await api.get('/tutor/sessions')
            this.sessions = data.sessions
            return this.sessions
        },

        async fetchSession(id) {
            const { data } = await api.get(`/tutor/sessions/${id}`)
            this.currentSession = data.session
            return data.session
        },

        async retryMeeting(id) {
            const { data } = await api.post(`/tutor/sessions/${id}/meeting/retry`)
            this.currentSession = data.session
            return data.session
        },

        async fetchBookingDetail(id) {
            const { data } = await api.get(`/tutor/bookings/${id}`)
            this.currentBooking = data.booking
            this.bookingProgress = data.progress
            this.bookingSessions = data.sessions
            return data
        },

        async scheduleSession(id, payload) {
            const { data } = await api.post(`/tutor/bookings/${id}/sessions`, payload)
            await this.fetchBookingDetail(id)
            return data.session
        },

        async completeSession(bookingId, sessionId, tutorNotes) {
            const { data } = await api.patch(`/tutor/sessions/${sessionId}/complete`, { tutor_notes: tutorNotes })
            await this.fetchBookingDetail(bookingId)
            return data.session
        },

        async cancelSession(bookingId, sessionId) {
            const { data } = await api.patch(`/tutor/sessions/${sessionId}/cancel`)
            await this.fetchBookingDetail(bookingId)
            return data.session
        },

        updateRequestLocal(booking) {
            const index = this.requests.findIndex((item) => item.id === booking.id)
            if (index !== -1) {
                this.requests[index] = booking
            }
        },
    },
})
