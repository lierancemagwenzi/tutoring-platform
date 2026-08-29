import { defineStore } from 'pinia'
import api from '../services/api'

export const useBookingStore = defineStore('booking', {
    state: () => ({
        myBookings: [],
        availability: {},
        currentBooking: null,
    }),

    actions: {
        async fetchAvailability(tutorId, serviceId, month) {
            const { data } = await api.get(`/marketplace/tutors/${tutorId}/services/${serviceId}/availability`, { params: { month } })
            this.availability = data.availability
            return this.availability
        },

        async createBooking(tutorId, serviceId, payload) {
            const { data } = await api.post(`/marketplace/tutors/${tutorId}/services/${serviceId}/bookings`, payload)
            return data.booking
        },

        async fetchMyBookings(status) {
            const { data } = await api.get('/bookings', { params: status ? { status } : {} })
            this.myBookings = data.bookings
            return this.myBookings
        },

        async fetchBooking(id) {
            const { data } = await api.get(`/bookings/${id}`)
            this.currentBooking = data.booking
            return data.booking
        },

        async fetchSessionLessons(id) {
            const { data } = await api.get(`/bookings/${id}/lessons`)
            return data.lessons
        },

        async fetchLessonBlock(bookingId, lessonBlockId) {
            const { data } = await api.get(`/bookings/${bookingId}/lesson-blocks/${lessonBlockId}`)
            return data.block
        },

        async fetchH5pPlayerModel(bookingId, lessonBlockId) {
            const { data } = await api.get(`/bookings/${bookingId}/lesson-blocks/${lessonBlockId}/h5p-player-model`)
            return data
        },

        async cancelBooking(id) {
            const { data } = await api.patch(`/bookings/${id}/cancel`)
            this.updateLocal(data.booking)
            return data.booking
        },

        updateLocal(booking) {
            const index = this.myBookings.findIndex((item) => item.id === booking.id)
            if (index !== -1) {
                this.myBookings[index] = booking
            }
            if (this.currentBooking?.id === booking.id) {
                this.currentBooking = booking
            }
        },
    },
})
