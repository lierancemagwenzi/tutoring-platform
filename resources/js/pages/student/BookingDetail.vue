<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingStore } from '../../stores/booking'
import StudentLessonBlocks from '../../components/lms/StudentLessonBlocks.vue'
import BookingChat from '../../components/booking/BookingChat.vue'

const CHAT_VISIBLE_STATUSES = ['confirmed', 'completed', 'cancelled']

const route = useRoute()
const router = useRouter()
const store = useBookingStore()

function payNow() {
    router.push(`/student/orders/${store.currentBooking.order_id}/pay`)
}

const loading = ref(true)
const errorMessage = ref('')
const sessionLessons = ref([])

onMounted(async () => {
    try {
        await store.fetchBooking(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This booking could not be found.'
        loading.value = false
        return
    }

    try {
        sessionLessons.value = await store.fetchSessionLessons(route.params.id)
    } catch (error) {
        // Session content is a supplementary section; a failure here shouldn't hide the booking itself.
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Booking Details</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="store.currentBooking">
            <div class="bg-card shadow-elevated mt-6 rounded-2xl p-6">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-muted">Tutor</dt>
                    <dd class="text-body font-medium">{{ store.currentBooking.tutor.display_name }}</dd>
                    <dt class="text-muted">Service</dt>
                    <dd class="text-body font-medium">{{ store.currentBooking.service.title }}</dd>
                    <dt class="text-muted">Date</dt>
                    <dd class="text-body font-medium">{{ store.currentBooking.date }}</dd>
                    <dt class="text-muted">Time</dt>
                    <dd class="text-body font-medium">{{ store.currentBooking.start_time }} - {{ store.currentBooking.end_time }}</dd>
                    <dt class="text-muted">Booking Status</dt>
                    <dd class="text-body font-medium capitalize">{{ store.currentBooking.status.replace('_', ' ') }}</dd>
                    <template v-if="Number(store.currentBooking.platform_booking_fee) > 0">
                        <dt class="text-muted">Tutor Price</dt>
                        <dd class="text-body font-medium">{{ store.currentBooking.currency }} {{ store.currentBooking.price }}</dd>
                        <dt class="text-muted">Platform &amp; Booking Fee</dt>
                        <dd class="text-body font-medium">{{ store.currentBooking.currency }} {{ store.currentBooking.platform_booking_fee }}</dd>
                        <dt class="text-muted font-semibold">Total Payable</dt>
                        <dd class="text-body font-semibold">{{ store.currentBooking.currency }} {{ store.currentBooking.total_payable }}</dd>
                    </template>
                    <template v-else>
                        <dt class="text-muted">Price</dt>
                        <dd class="text-body font-medium">{{ store.currentBooking.currency }} {{ store.currentBooking.price }}</dd>
                    </template>
                </dl>
                <div v-if="store.currentBooking.message" class="border-border mt-4 border-t pt-4">
                    <p class="text-body text-sm font-semibold">Your message</p>
                    <p class="text-muted mt-1 text-sm">{{ store.currentBooking.message }}</p>
                </div>
                <button
                    v-if="store.currentBooking.status === 'awaiting_payment'"
                    type="button"
                    class="bg-amber shadow-elevated mt-4 rounded-full px-6 py-3 font-semibold text-white transition hover:brightness-95"
                    @click="payNow"
                >
                    Pay Now
                </button>
            </div>

            <div v-if="store.currentBooking.sessions?.length" class="mt-6 space-y-4">
                <h2 class="text-body font-bold">Sessions</h2>
                <div
                    v-for="(session, index) in store.currentBooking.sessions"
                    :key="session.id"
                    class="bg-card shadow-elevated rounded-2xl p-6"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-body font-semibold">Session {{ index + 1 }}</p>
                            <p class="text-muted mt-1 text-sm">{{ session.date }} &middot; {{ session.start_time }} - {{ session.end_time }}</p>
                            <p v-if="session.lessons?.length" class="text-muted mt-1 text-xs">{{ session.lessons.join(', ') }}</p>
                        </div>
                        <span class="text-body shrink-0 text-sm font-semibold capitalize">{{ session.status.replace('_', ' ') }}</span>
                    </div>

                    <div v-if="session.meeting?.meeting_url" class="bg-card-alt mt-4 rounded-xl p-4">
                        <p class="text-body text-sm font-semibold">Google Meet</p>
                        <a
                            :href="session.meeting.meeting_url"
                            target="_blank"
                            rel="noopener"
                            class="text-accent mt-1 block text-sm font-semibold underline"
                        >
                            Join Meeting
                        </a>
                    </div>
                    <p v-else-if="session.meeting" class="text-muted mt-4 text-sm">
                        Your tutor is still setting up the meeting link for this session — check back soon.
                    </p>
                </div>
            </div>

            <div v-if="sessionLessons.length" class="mt-6 space-y-6">
                <div v-for="sessionLesson in sessionLessons" :key="sessionLesson.id" class="bg-card shadow-elevated rounded-2xl p-6">
                    <h2 class="text-body font-bold">{{ sessionLesson.lesson.title }}</h2>
                    <p v-if="sessionLesson.lesson.description" class="text-muted mt-1 text-sm">{{ sessionLesson.lesson.description }}</p>

                    <p v-if="!sessionLesson.blocks.length" class="text-muted mt-4 text-sm">
                        Your tutor hasn't made any content available for this lesson yet.
                    </p>
                    <StudentLessonBlocks v-else class="mt-4" :blocks="sessionLesson.blocks" />
                </div>
            </div>

            <BookingChat
                v-if="CHAT_VISIBLE_STATUSES.includes(store.currentBooking.status)"
                :api-base-path="`/bookings/${route.params.id}`"
                :active="store.currentBooking.status === 'confirmed'"
            />
        </template>
    </div>
</template>
