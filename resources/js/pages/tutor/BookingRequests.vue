<script setup>
import { onMounted, ref } from 'vue'
import { useTutorBookingsStore } from '../../stores/tutorBookings'
import Modal from '../../components/common/Modal.vue'

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'awaiting_payment', label: 'Awaiting Payment' },
    { value: 'confirmed', label: 'Confirmed' },
]

const STATUS_CLASSES = {
    pending: 'bg-gray-100 text-gray-600',
    accepted: 'bg-blue-100 text-blue-700',
    rejected: 'bg-red-100 text-red-700',
    awaiting_payment: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
    completed: 'bg-emerald-100 text-emerald-700',
    expired: 'bg-gray-100 text-gray-500',
}

const store = useTutorBookingsStore()

const loading = ref(true)
const activeStatus = ref('')
const actioningId = ref(null)
const actionError = ref('')
const viewTarget = ref(null)

async function load() {
    loading.value = true
    try {
        await store.fetchRequests(activeStatus.value || undefined)
    } finally {
        loading.value = false
    }
}

onMounted(load)

function selectStatus(status) {
    activeStatus.value = status
    load()
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

async function accept(booking) {
    actioningId.value = booking.id
    actionError.value = ''

    try {
        await store.acceptRequest(booking.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}

async function reject(booking) {
    actioningId.value = booking.id
    actionError.value = ''

    try {
        await store.rejectRequest(booking.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}

function openView(booking) {
    viewTarget.value = booking
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Booking Requests</h1>

        <div class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="tab in STATUS_TABS"
                :key="tab.value"
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="activeStatus === tab.value ? 'bg-amber text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50'"
                @click="selectStatus(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.requests.length === 0" class="mt-16 text-center text-gray-500">No booking requests to show.</div>

        <div v-else class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="booking in store.requests" :key="booking.id" class="flex flex-col rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-ink font-bold">{{ booking.student.first_name }} {{ booking.student.last_name }}</p>
                        <p class="text-sm text-gray-500">{{ booking.service.title }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClasses(booking.status)">
                        {{ booking.status }}
                    </span>
                </div>

                <p class="mt-3 text-sm text-gray-500">{{ booking.date }} &middot; {{ booking.start_time }} - {{ booking.end_time }}</p>
                <p v-if="booking.message" class="mt-2 line-clamp-2 text-sm text-gray-600">"{{ booking.message }}"</p>

                <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4">
                    <button type="button" class="text-accent text-sm font-semibold" @click="openView(booking)">View Details</button>
                    <router-link
                        v-if="booking.status === 'confirmed'"
                        :to="`/tutor/bookings/${booking.id}`"
                        class="text-accent text-sm font-semibold"
                    >
                        Manage Sessions
                    </router-link>
                    <template v-if="booking.status === 'pending'">
                        <button
                            type="button"
                            :disabled="actioningId === booking.id"
                            class="text-sm font-semibold text-green-600 disabled:opacity-40"
                            @click="accept(booking)"
                        >
                            Accept
                        </button>
                        <button
                            type="button"
                            :disabled="actioningId === booking.id"
                            class="text-sm font-semibold text-red-600 disabled:opacity-40"
                            @click="reject(booking)"
                        >
                            Reject
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <Modal :model-value="viewTarget !== null" :title="viewTarget?.service.title ?? ''" @update:model-value="viewTarget = null">
            <div v-if="viewTarget" class="space-y-3 text-sm">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <dt class="text-gray-500">Student</dt>
                    <dd class="text-ink font-medium">{{ viewTarget.student.first_name }} {{ viewTarget.student.last_name }}</dd>
                    <dt class="text-gray-500">Date</dt>
                    <dd class="text-ink font-medium">{{ viewTarget.date }}</dd>
                    <dt class="text-gray-500">Time</dt>
                    <dd class="text-ink font-medium">{{ viewTarget.start_time }} - {{ viewTarget.end_time }}</dd>
                    <dt class="text-gray-500">Status</dt>
                    <dd class="text-ink font-medium capitalize">{{ viewTarget.status }}</dd>
                    <dt class="text-gray-500">Price</dt>
                    <dd class="text-ink font-medium">{{ viewTarget.currency }} {{ viewTarget.price }}</dd>
                </dl>
                <div v-if="viewTarget.message">
                    <p class="font-semibold text-gray-700">Message</p>
                    <p class="mt-1 text-gray-600">{{ viewTarget.message }}</p>
                </div>
            </div>
        </Modal>
    </div>
</template>
