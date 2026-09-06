<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTutorBookingsStore } from '../../stores/tutorBookings'

const route = useRoute()
const router = useRouter()
const store = useTutorBookingsStore()

const loading = ref(true)
const errorMessage = ref('')
const retrying = ref(false)
const retryError = ref('')

onMounted(async () => {
    try {
        await store.fetchSession(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This session could not be found.'
    } finally {
        loading.value = false
    }
})

async function retryMeeting() {
    retrying.value = true
    retryError.value = ''

    try {
        await store.retryMeeting(route.params.id)
    } catch (error) {
        retryError.value = error.response?.data?.message ?? 'Could not retry meeting creation. Please try again.'
    } finally {
        retrying.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-body text-2xl font-bold">Session Details</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="store.currentSession">
            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    class="bg-amber shadow-elevated rounded-full px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95"
                    @click="router.push(`/tutor/sessions/${route.params.id}/content`)"
                >
                    Manage Content
                </button>
            </div>

            <div class="bg-card shadow-elevated mt-6 rounded-2xl p-6">
                <h2 class="text-body font-bold">Session Information</h2>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-muted">Service</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.service.title }}</dd>
                    <dt class="text-muted">Subject</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.service.subject.name }}</dd>
                    <dt class="text-muted">Date</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.date }}</dd>
                    <dt class="text-muted">Start Time</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.start_time }}</dd>
                    <dt class="text-muted">End Time</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.end_time }}</dd>
                    <dt class="text-muted">Delivery Format</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.service.session_format.name }}</dd>
                    <dt class="text-muted">Capacity</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.capacity }}</dd>
                    <dt class="text-muted">Current Participants</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.participants_count }}</dd>
                    <dt class="text-muted">Session Status</dt>
                    <dd class="text-body font-medium capitalize">{{ store.currentSession.status }}</dd>
                </dl>
            </div>

            <div v-if="store.currentSession.meeting" class="bg-card shadow-elevated mt-6 rounded-2xl p-6">
                <h2 class="text-body font-bold">Meeting Information</h2>
                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-muted">Provider</dt>
                    <dd class="text-body font-medium capitalize">{{ store.currentSession.meeting.provider.replace('_', ' ') }}</dd>
                    <dt class="text-muted">Meeting ID</dt>
                    <dd class="text-body font-medium">{{ store.currentSession.meeting.meeting_id }}</dd>
                    <dt class="text-muted">Meeting Status</dt>
                    <dd class="text-body font-medium capitalize">{{ store.currentSession.meeting.status }}</dd>
                </dl>
                <a
                    v-if="store.currentSession.meeting.meeting_url"
                    :href="store.currentSession.meeting.meeting_url"
                    target="_blank"
                    rel="noopener"
                    class="text-accent mt-3 block text-sm font-semibold underline"
                >
                    {{ store.currentSession.meeting.meeting_url }}
                </a>

                <div v-if="store.currentSession.meeting.status === 'failed'" class="border-border mt-4 border-t pt-4">
                    <p class="text-sm text-red-600">Meeting creation failed.</p>
                    <p v-if="retryError" class="mt-1 text-sm text-red-600">{{ retryError }}</p>
                    <button
                        type="button"
                        :disabled="retrying"
                        class="bg-amber shadow-elevated mt-2 rounded-full px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="retryMeeting"
                    >
                        {{ retrying ? 'Retrying…' : 'Retry' }}
                    </button>
                </div>
            </div>

            <div class="bg-card shadow-elevated mt-6 rounded-2xl p-6">
                <h2 class="text-body font-bold">Bookings</h2>
                <ul class="divide-border mt-4 divide-y">
                    <li v-for="booking in store.currentSession.bookings" :key="booking.id" class="flex items-center justify-between py-3">
                        <div>
                            <p class="text-body font-semibold">{{ booking.student.first_name }} {{ booking.student.last_name }}</p>
                            <p v-if="booking.message" class="text-muted text-sm">"{{ booking.message }}"</p>
                        </div>
                        <span class="text-muted text-sm font-semibold capitalize">{{ booking.status }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
