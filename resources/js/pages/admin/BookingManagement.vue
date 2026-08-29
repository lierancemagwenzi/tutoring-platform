<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useAdminBookingsStore } from '../../stores/adminBookings'
import Pagination from '../../components/common/Pagination.vue'
import Modal from '../../components/common/Modal.vue'
import { adminStatusBadge, adminStatusLabel } from '../../utils/adminStatusBadge'

const STATUS_TABS = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'awaiting_payment', label: 'Awaiting Payment' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'rejected', label: 'Rejected' },
]

const CANCELLABLE_STATUSES = ['confirmed', 'awaiting_payment']

const store = useAdminBookingsStore()
const loading = ref(true)
const errorMessage = ref('')
const filters = reactive({ status: '', search: '' })

async function applyFilters(page = 1) {
    loading.value = true
    errorMessage.value = ''
    try {
        await store.fetchBookings({ ...filters, page })
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong loading bookings.'
    } finally {
        loading.value = false
    }
}

onMounted(() => applyFilters())

function selectStatus(status) {
    filters.status = status
    applyFilters(1)
}

const cancelModalOpen = ref(false)
const cancelTarget = ref(null)
const cancelReason = ref('')
const cancelling = ref(false)
const cancelError = ref('')

function openCancel(booking) {
    cancelTarget.value = booking
    cancelReason.value = ''
    cancelError.value = ''
    cancelModalOpen.value = true
}

async function submitCancel() {
    cancelling.value = true
    cancelError.value = ''
    try {
        await store.cancel(cancelTarget.value.id, cancelReason.value)
        cancelModalOpen.value = false
    } catch (error) {
        cancelError.value = error.response?.data?.errors?.reason?.[0]
            ?? error.response?.data?.errors?.booking?.[0]
            ?? error.response?.data?.message
            ?? 'Could not cancel this booking.'
    } finally {
        cancelling.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Bookings</h1>
        <p class="mt-1 text-sm text-gray-500">Every tutoring booking on the platform.</p>

        <div class="mt-6 flex flex-wrap items-center gap-4">
            <div class="flex flex-wrap gap-2">
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
            <input
                v-model="filters.search"
                type="text"
                placeholder="Search student…"
                class="focus:border-accent rounded-xl border border-gray-300 px-3.5 py-2 text-sm text-gray-900 outline-none"
                @keyup.enter="applyFilters(1)"
            />
        </div>

        <p v-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.bookings.length === 0" class="mt-16 text-center text-gray-500">No bookings found.</div>

        <div v-else class="mt-6 overflow-x-auto rounded-2xl bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Reference</th>
                        <th class="px-5 py-3 font-semibold">Student</th>
                        <th class="px-5 py-3 font-semibold">Tutor</th>
                        <th class="px-5 py-3 font-semibold">Service</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Sessions</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="booking in store.bookings" :key="booking.id">
                        <td class="text-ink px-5 py-3 font-medium">{{ booking.reference }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ booking.student }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ booking.tutor }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ booking.service }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="adminStatusBadge(booking.status)">
                                {{ adminStatusLabel(booking.status) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ booking.completed_sessions }}/{{ booking.purchased_sessions }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ booking.created_at.slice(0, 10) }}</td>
                        <td class="px-5 py-3 text-right">
                            <button
                                v-if="CANCELLABLE_STATUSES.includes(booking.status)"
                                type="button"
                                class="text-sm font-semibold text-red-600"
                                @click="openCancel(booking)"
                            >
                                Cancel &amp; Refund
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :meta="store.meta" @change="applyFilters" />

        <Modal v-model="cancelModalOpen" title="Cancel &amp; Refund Booking">
            <div class="space-y-4">
                <p class="text-sm text-gray-500">
                    This cancels the booking and, if it was paid, reverses the commission/payout bookkeeping for tutor
                    <strong>{{ cancelTarget?.tutor }}</strong>. The actual refund to the student must still be issued manually via
                    the PayFast merchant dashboard.
                </p>
                <p v-if="cancelError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ cancelError }}</p>
                <div>
                    <label class="block text-sm font-semibold text-gray-700" for="cancel-reason">Reason</label>
                    <textarea
                        id="cancel-reason"
                        v-model="cancelReason"
                        rows="3"
                        class="focus:border-accent mt-1.5 w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 outline-none"
                    />
                </div>
            </div>
            <template #footer>
                <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700" @click="cancelModalOpen = false">
                    Close
                </button>
                <button
                    type="button"
                    :disabled="cancelling || !cancelReason.trim()"
                    class="rounded-full bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95 disabled:opacity-40"
                    @click="submitCancel"
                >
                    {{ cancelling ? 'Cancelling…' : 'Confirm Cancel & Refund' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
