<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingStore } from '../../stores/booking'
import Modal from '../../components/common/Modal.vue'

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'awaiting_payment', label: 'Awaiting Payment' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
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

const router = useRouter()
const store = useBookingStore()

const loading = ref(true)
const activeStatus = ref('')
const cancelTarget = ref(null)
const cancelling = ref(false)
const actionError = ref('')

async function load() {
    loading.value = true
    try {
        await store.fetchMyBookings(activeStatus.value || undefined)
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

function viewBooking(booking) {
    router.push(`/student/bookings/${booking.id}`)
}

function goToPayment(booking) {
    router.push(`/student/orders/${booking.order_id}/pay`)
}

function confirmCancel(booking) {
    cancelTarget.value = booking
}

async function cancelBooking() {
    cancelling.value = true
    actionError.value = ''

    try {
        await store.cancelBooking(cancelTarget.value.id)
        cancelTarget.value = null
        await load()
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        cancelling.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">My Bookings</h1>

        <div class="mt-6 flex flex-wrap gap-2">
            <button
                v-for="tab in STATUS_TABS"
                :key="tab.value"
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="activeStatus === tab.value ? 'bg-amber text-white' : 'border-border text-body hover:bg-card-alt border'"
                @click="selectStatus(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.myBookings.length === 0" class="text-muted mt-16 text-center">No bookings to show.</div>

        <div v-else class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="booking in store.myBookings" :key="booking.id" class="bg-card shadow-elevated flex flex-col rounded-2xl p-5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-body font-bold">{{ booking.tutor.display_name }}</p>
                        <p class="text-muted text-sm">{{ booking.service.title }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClasses(booking.status)">
                        {{ booking.status }}
                    </span>
                </div>

                <div class="text-muted mt-3 space-y-1 text-sm">
                    <p>{{ booking.date }} &middot; {{ booking.start_time }} - {{ booking.end_time }}</p>
                    <p v-if="Number(booking.platform_booking_fee) > 0">
                        {{ booking.currency }} {{ booking.total_payable }}
                        <span class="text-xs">(incl. booking fee)</span>
                    </p>
                    <p v-else>{{ booking.currency }} {{ booking.price }}</p>
                </div>

                <div class="border-border mt-4 flex flex-wrap items-center gap-4 border-t pt-4">
                    <button type="button" class="text-accent text-sm font-semibold" @click="viewBooking(booking)">View</button>
                    <button
                        v-if="booking.status === 'awaiting_payment'"
                        type="button"
                        class="text-sm font-semibold text-green-600"
                        @click="goToPayment(booking)"
                    >
                        Pay Now
                    </button>
                    <button
                        v-if="['pending', 'accepted', 'awaiting_payment'].includes(booking.status)"
                        type="button"
                        class="text-sm font-semibold text-red-600"
                        @click="confirmCancel(booking)"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

        <Modal :model-value="cancelTarget !== null" title="Cancel this booking?" @update:model-value="cancelTarget = null">
            <p class="text-muted">This will cancel your booking request. You can submit a new request later if you change your mind.</p>
            <template #footer>
                <button
                    type="button"
                    class="border-border text-body rounded-full border px-5 py-2.5 font-semibold"
                    @click="cancelTarget = null"
                >
                    Keep Booking
                </button>
                <button
                    type="button"
                    :disabled="cancelling"
                    class="shadow-elevated rounded-full bg-red-600 px-5 py-2.5 font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="cancelBooking"
                >
                    {{ cancelling ? 'Cancelling…' : 'Cancel Booking' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
