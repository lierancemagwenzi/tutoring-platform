import { defineStore } from 'pinia'
import api from '../services/api'

export const useAdminBookingsStore = defineStore('adminBookings', {
    state: () => ({
        bookings: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }),

    actions: {
        async fetchBookings(params = {}) {
            const { data } = await api.get('/admin/bookings', { params })
            this.bookings = data.bookings
            this.meta = data.meta
            return data
        },

        async cancel(bookingId, reason) {
            const { data } = await api.post(`/admin/bookings/${bookingId}/cancel`, { reason })
            const index = this.bookings.findIndex((booking) => booking.id === bookingId)
            if (index !== -1) {
                this.bookings[index] = data.booking
            }
            return data.booking
        },
    },
})
