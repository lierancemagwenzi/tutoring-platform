<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useMarketplaceStore } from '../../stores/marketplace'
import { useBookingStore } from '../../stores/booking'
import MonthCalendar from '../../components/calendar/MonthCalendar.vue'
import TextareaInput from '../../components/forms/TextareaInput.vue'

const route = useRoute()
const marketplace = useMarketplaceStore()
const bookingStore = useBookingStore()

const tutorId = computed(() => Number(route.params.tutorId))
const serviceId = computed(() => Number(route.params.serviceId))

const loading = ref(true)
const errorMessage = ref('')
const submitting = ref(false)
const submitted = ref(false)

const today = new Date()
const year = ref(today.getFullYear())
const month = ref(today.getMonth() + 1)
const selectedDate = ref(null)
const selectedSlot = ref(null)
const message = ref('')

const service = computed(() => marketplace.tutorProfile?.services.find((item) => item.id === serviceId.value))
const monthParam = computed(() => `${year.value}-${String(month.value).padStart(2, '0')}`)
const availableDates = computed(() => new Set(Object.keys(bookingStore.availability)))
const dayTimes = computed(() => (selectedDate.value ? (bookingStore.availability[selectedDate.value] ?? []) : []))

async function loadAvailability() {
    loading.value = true
    try {
        await bookingStore.fetchAvailability(tutorId.value, serviceId.value, monthParam.value)
    } finally {
        loading.value = false
    }
}

onMounted(async () => {
    try {
        await marketplace.fetchTutorProfile(tutorId.value)
    } catch {
        errorMessage.value = 'This tutor could not be found.'
    }
    await loadAvailability()
})

function selectDate(dateString) {
    selectedDate.value = dateString
    selectedSlot.value = null
}

function prevMonth() {
    if (month.value === 1) {
        month.value = 12
        year.value -= 1
    } else {
        month.value -= 1
    }
    selectedDate.value = null
    selectedSlot.value = null
    loadAvailability()
}

function nextMonth() {
    if (month.value === 12) {
        month.value = 1
        year.value += 1
    } else {
        month.value += 1
    }
    selectedDate.value = null
    selectedSlot.value = null
    loadAvailability()
}

function selectSlot(slot) {
    selectedSlot.value = slot
}

async function submitBooking() {
    submitting.value = true
    errorMessage.value = ''

    try {
        await bookingStore.createBooking(tutorId.value, serviceId.value, {
            availability_slot_id: selectedSlot.value.availability_slot_id,
            date: selectedDate.value,
            start_time: selectedSlot.value.start_time,
            message: message.value || null,
        })
        submitted.value = true
    } catch (error) {
        const errors = error.response?.data?.errors
        errorMessage.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Book This Service</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="submitted" class="mt-8 flex flex-col items-center rounded-2xl bg-white p-10 text-center shadow-sm">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                <CheckCircleIcon class="h-8 w-8" />
            </span>
            <h2 class="text-ink mt-4 text-xl font-bold">Booking request sent!</h2>
            <p class="mt-2 text-gray-500">
                The tutor will review your request. You'll be able to pay once it's accepted.
            </p>
            <router-link
                to="/student/bookings"
                class="bg-amber mt-6 rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
            >
                View My Bookings
            </router-link>
        </div>

        <template v-else>
            <div v-if="service" class="mt-2 rounded-2xl bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">{{ service.subject.name }} &middot; {{ service.category.name }}</p>
                <p class="text-ink font-bold">{{ service.title }}</p>
                <p class="mt-1 text-lg font-bold text-gray-900">{{ service.currency }} {{ service.price }}</p>
            </div>

            <p v-if="errorMessage" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <MonthCalendar
                    :year="year"
                    :month="month"
                    :selected-date="selectedDate"
                    :available-dates="availableDates"
                    @select-date="selectDate"
                    @prev-month="prevMonth"
                    @next-month="nextMonth"
                />

                <div class="rounded-2xl bg-white p-6 shadow-sm">
                    <div v-if="!selectedDate" class="py-12 text-center text-gray-500">Select a date to see available times.</div>

                    <template v-else>
                        <h2 class="text-ink font-bold">Available Times</h2>
                        <p v-if="dayTimes.length === 0" class="mt-4 text-gray-500">No times are available on this date.</p>
                        <div v-else class="mt-4 grid grid-cols-3 gap-3">
                            <button
                                v-for="slot in dayTimes"
                                :key="slot.start_time"
                                type="button"
                                class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                :class="
                                    selectedSlot?.start_time === slot.start_time
                                        ? 'bg-amber border-amber text-white'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                                "
                                @click="selectSlot(slot)"
                            >
                                {{ slot.start_time }}
                            </button>
                        </div>

                        <div v-if="selectedSlot" class="mt-6">
                            <TextareaInput id="message" v-model="message" label="Message (optional)" :rows="3" />
                            <button
                                type="button"
                                :disabled="submitting"
                                class="bg-amber mt-4 w-full rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="submitBooking"
                            >
                                {{ submitting ? 'Submitting…' : 'Submit Booking Request' }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</template>
