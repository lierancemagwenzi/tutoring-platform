<script setup>
import { onMounted, ref } from 'vue'
import { useTutorPaymentTicketsStore } from '../../stores/tutorPaymentTickets'
import Pagination from '../../components/common/Pagination.vue'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const store = useTutorPaymentTicketsStore()
const loading = ref(true)
const errorMessage = ref('')

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchList({ page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading your tickets.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Payment Tickets</h1>
        <p class="mt-1 text-sm text-gray-500">Issues you've raised about specific earnings — raise a new one from the Earnings page.</p>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.tickets.length === 0" class="mt-16 text-center text-gray-500">You haven't raised any payment tickets.</div>

        <div v-else class="mt-6 space-y-3">
            <router-link
                v-for="ticket in store.tickets"
                :key="ticket.id"
                :to="{ name: 'tutor.payment-tickets.show', params: { id: ticket.id } }"
                class="block rounded-2xl bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-ink font-bold capitalize">{{ ticket.transaction.product_type.replace('_', ' ') }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ ticket.message }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span>{{ ticket.transaction.currency }} {{ ticket.transaction.tutor_amount }}</span>
                            <span>{{ ticket.comments_count }} comment{{ ticket.comments_count === 1 ? '' : 's' }}</span>
                            <span>{{ ticket.created_at.slice(0, 10) }}</span>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="adminStatusBadge(ticket.status)">
                        {{ adminStatusLabel(ticket.status) }}
                    </span>
                </div>
            </router-link>
        </div>

        <Pagination :meta="store.meta" @change="load" />
    </div>
</template>
