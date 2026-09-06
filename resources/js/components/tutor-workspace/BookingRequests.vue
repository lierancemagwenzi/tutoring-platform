<script setup>
import { ref, watch } from 'vue'
import { InboxArrowDownIcon } from '@heroicons/vue/24/outline'
import { useTutorBookingsStore } from '../../stores/tutorBookings'

const props = defineProps({
    requests: { type: Array, default: () => [] },
})

const store = useTutorBookingsStore()
const localRequests = ref([...props.requests])
const actioningId = ref(null)
const actionError = ref('')

watch(
    () => props.requests,
    (value) => {
        localRequests.value = [...value]
    },
)

async function accept(booking) {
    actioningId.value = booking.id
    actionError.value = ''

    try {
        await store.acceptRequest(booking.id)
        localRequests.value = localRequests.value.filter((request) => request.id !== booking.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}

async function decline(booking) {
    actioningId.value = booking.id
    actionError.value = ''

    try {
        await store.rejectRequest(booking.id)
        localRequests.value = localRequests.value.filter((request) => request.id !== booking.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <div class="flex items-center justify-between">
            <h2 class="text-body text-lg font-bold">Booking Requests</h2>
            <router-link to="/tutor/booking-requests" class="text-accent text-sm font-semibold">View all</router-link>
        </div>

        <p v-if="actionError" class="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="localRequests.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <InboxArrowDownIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">No booking requests to show.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-border">
            <li v-for="request in localRequests" :key="request.id" class="py-3 first:pt-0 last:pb-0">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-body font-semibold">{{ request.student.first_name }} {{ request.student.last_name }}</p>
                        <p class="text-sm text-muted">{{ request.service.title }}</p>
                        <p class="mt-1 text-xs text-muted">{{ request.date }} &middot; {{ request.start_time }} - {{ request.end_time }}</p>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-4 text-sm font-semibold">
                    <button
                        type="button"
                        :disabled="actioningId === request.id"
                        class="text-green-600 disabled:opacity-40"
                        @click="accept(request)"
                    >
                        Accept
                    </button>
                    <button
                        type="button"
                        :disabled="actioningId === request.id"
                        class="text-red-600 disabled:opacity-40"
                        @click="decline(request)"
                    >
                        Decline
                    </button>
                    <router-link to="/tutor/booking-requests" class="text-accent">View Request</router-link>
                </div>
            </li>
        </ul>
    </div>
</template>
