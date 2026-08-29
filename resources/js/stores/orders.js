import { defineStore } from 'pinia'
import api from '../services/api'

export const useOrderStore = defineStore('orders', {
    state: () => ({
        myOrders: [],
        ordersMeta: { current_page: 1, last_page: 1, per_page: 12, total: 0 },
        currentOrder: null,
        myCourses: [],
    }),

    actions: {
        async createOrder(courseOfferingId) {
            const { data } = await api.post('/orders', { course_offering_id: courseOfferingId })
            this.currentOrder = data.order
            return data.order
        },

        async fetchMyOrders(params = {}) {
            const { data } = await api.get('/orders', { params })
            this.myOrders = data.orders
            this.ordersMeta = data.meta
            return data
        },

        async fetchOrder(id) {
            const { data } = await api.get(`/orders/${id}`)
            this.currentOrder = data.order
            return data.order
        },

        async fetchPaymentFields(id) {
            const { data } = await api.get(`/orders/${id}/pay`)
            return data
        },

        async fetchMyCourses() {
            const { data } = await api.get('/enrollments')
            this.myCourses = data.enrollments
            return data.enrollments
        },
    },
})
