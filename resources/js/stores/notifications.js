import { defineStore } from 'pinia'
import api from '../services/api'

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        items: [],
        unreadCount: 0,
    }),

    actions: {
        async fetchList() {
            const { data } = await api.get('/notifications')
            this.items = data.notifications
            this.unreadCount = data.unread_count
        },

        async markRead(id) {
            const item = this.items.find((notification) => notification.id === id)
            if (!item || item.read) {
                return
            }
            item.read = true
            this.unreadCount = Math.max(0, this.unreadCount - 1)
            await api.post(`/notifications/${id}/read`)
        },

        async markAllRead() {
            this.items.forEach((item) => (item.read = true))
            this.unreadCount = 0
            await api.post('/notifications/read-all')
        },
    },
})
