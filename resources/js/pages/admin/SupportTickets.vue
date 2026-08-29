<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminSupportTicketsStore } from '../../stores/adminSupportTickets'
import Pagination from '../../components/common/Pagination.vue'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'open', label: 'Open' },
    { value: 'in_review', label: 'In Review' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'rejected', label: 'Rejected' },
]

const store = useAdminSupportTicketsStore()
const loading = ref(true)
const errorMessage = ref('')
const filters = reactive({ status: '' })

async function load(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchList({ ...filters, page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading support tickets.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load())

function selectStatus(status) {
    filters.status = status
    load(1)
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Support Tickets</h1>
        <p class="mt-1 text-sm text-gray-500">General help requests raised by tutors and students.</p>

        <div class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="tab in STATUS_TABS"
                :key="tab.value"
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="filters.status === tab.value ? 'bg-amber text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                @click="selectStatus(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.tickets.length === 0" class="mt-16 text-center text-gray-500">No support tickets found.</div>

        <div v-else class="mt-6 space-y-3">
            <router-link
                v-for="ticket in store.tickets"
                :key="ticket.id"
                :to="{ name: 'admin.support-tickets.show', params: { id: ticket.id } }"
                class="block rounded-2xl bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-ink font-bold">{{ ticket.subject }}</p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ ticket.user.name }} &middot; <span class="capitalize">{{ ticket.user.role }}</span>
                        </p>
                        <p class="mt-1 text-sm text-gray-600">{{ ticket.message }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
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
