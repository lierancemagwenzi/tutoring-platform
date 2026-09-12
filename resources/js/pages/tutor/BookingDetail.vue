<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { VideoCameraIcon } from '@heroicons/vue/24/outline'
import { useTutorBookingsStore } from '../../stores/tutorBookings'
import { useTutorSettingsStore } from '../../stores/tutorSettings'
import Modal from '../../components/common/Modal.vue'
import LessonPicker from '../../components/lms/LessonPicker.vue'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../components/forms/TextareaInput.vue'
import BookingChat from '../../components/booking/BookingChat.vue'

const CHAT_VISIBLE_STATUSES = ['confirmed', 'completed', 'cancelled']

const STATUS_CLASSES = {
    scheduled: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-gray-100 text-gray-500',
    no_show: 'bg-red-100 text-red-700',
    rescheduled: 'bg-gray-100 text-gray-600',
}

const route = useRoute()
const router = useRouter()
const store = useTutorBookingsStore()
const settings = useTutorSettingsStore()

const loading = ref(true)
const errorMessage = ref('')

const formOpen = ref(false)
const pickerOpen = ref(false)
const selectedLesson = ref(null)
const form = ref({ date: '', start_time: '', end_time: '', tutor_notes: '' })
const submitting = ref(false)
const formError = ref('')

const actioningSessionId = ref(null)
const actionError = ref('')

const retryingMeetingId = ref(null)
const retryError = ref('')

async function load() {
    loading.value = true
    try {
        await store.fetchBookingDetail(route.params.id)
        await settings.fetchMeetingProviderSettings()
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This booking could not be found.'
    } finally {
        loading.value = false
    }
}

onMounted(load)

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

const canScheduleAnother = computed(() => (store.bookingProgress?.remaining_sessions ?? 0) > 0)

function openForm() {
    form.value = { date: '', start_time: '', end_time: '', tutor_notes: '' }
    selectedLesson.value = null
    formError.value = ''
    formOpen.value = true
}

function onLessonSelected(lesson) {
    selectedLesson.value = lesson
    pickerOpen.value = false
}

async function submitForm() {
    submitting.value = true
    formError.value = ''

    try {
        await store.scheduleSession(route.params.id, {
            lesson_id: selectedLesson.value?.id ?? null,
            date: form.value.date,
            start_time: form.value.start_time,
            end_time: form.value.end_time,
            tutor_notes: form.value.tutor_notes || null,
        })
        formOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Could not schedule this session. Please try again.')
    } finally {
        submitting.value = false
    }
}

async function markCompleted(session) {
    actioningSessionId.value = session.id
    actionError.value = ''
    try {
        await store.completeSession(route.params.id, session.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not mark this session completed. Please try again.'
    } finally {
        actioningSessionId.value = null
    }
}

async function cancelSession(session) {
    if (!confirm('Cancel this session? This frees it up for another session to be scheduled.')) {
        return
    }

    actioningSessionId.value = session.id
    actionError.value = ''
    try {
        await store.cancelSession(route.params.id, session.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not cancel this session. Please try again.'
    } finally {
        actioningSessionId.value = null
    }
}

async function retryMeeting(session) {
    retryingMeetingId.value = session.id
    retryError.value = ''
    try {
        await store.retryMeeting(session.id)
        await store.fetchBookingDetail(route.params.id)
    } catch (error) {
        retryError.value = error.response?.data?.message ?? 'Could not retry meeting creation. Please try again.'
    } finally {
        retryingMeetingId.value = null
    }
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="router.push('/tutor/booking-requests')">
            &larr; Back to Bookings
        </button>
        <h1 class="text-body mt-1 text-2xl font-bold">Booking</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="store.currentBooking">
            <div class="bg-card shadow-elevated mt-6 rounded-2xl p-6">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-muted">Student</dt>
                    <dd class="text-body font-medium">
                        {{ store.currentBooking.student.first_name }} {{ store.currentBooking.student.last_name }}
                    </dd>
                    <dt class="text-muted">Service</dt>
                    <dd class="text-body font-medium">{{ store.currentBooking.service.title }}</dd>
                </dl>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <div class="bg-card shadow-elevated rounded-2xl p-4 text-center">
                    <p class="text-body text-2xl font-bold">{{ store.bookingProgress.purchased_sessions }}</p>
                    <p class="text-muted mt-1 text-xs">Purchased</p>
                </div>
                <div class="bg-card shadow-elevated rounded-2xl p-4 text-center">
                    <p class="text-body text-2xl font-bold">{{ store.bookingProgress.scheduled_sessions }}</p>
                    <p class="text-muted mt-1 text-xs">Scheduled</p>
                </div>
                <div class="bg-card shadow-elevated rounded-2xl p-4 text-center">
                    <p class="text-body text-2xl font-bold">{{ store.bookingProgress.completed_sessions }}</p>
                    <p class="text-muted mt-1 text-xs">Completed</p>
                </div>
                <div class="bg-card shadow-elevated rounded-2xl p-4 text-center">
                    <p class="text-body text-2xl font-bold">{{ store.bookingProgress.upcoming_sessions }}</p>
                    <p class="text-muted mt-1 text-xs">Upcoming</p>
                </div>
                <div class="bg-card shadow-elevated rounded-2xl p-4 text-center">
                    <p class="text-body text-2xl font-bold">{{ store.bookingProgress.remaining_sessions }}</p>
                    <p class="text-muted mt-1 text-xs">Remaining</p>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <h2 class="text-body font-bold">Sessions</h2>
                <button
                    v-if="canScheduleAnother"
                    type="button"
                    class="bg-amber shadow-elevated rounded-full px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95"
                    @click="openForm"
                >
                    Schedule Session
                </button>
            </div>

            <p v-if="actionError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

            <p v-if="store.bookingSessions.length === 0" class="text-muted mt-6 text-sm">No sessions scheduled yet.</p>

            <ul v-else class="mt-4 space-y-3">
                <li
                    v-for="(session, index) in store.bookingSessions"
                    :key="session.id"
                    class="bg-card shadow-elevated rounded-2xl p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-body font-bold">Session {{ index + 1 }}</p>
                            <p class="text-muted mt-1 text-sm">{{ session.date }} &middot; {{ session.start_time }} - {{ session.end_time }}</p>
                            <p v-if="session.lessons?.length" class="text-muted mt-1 text-xs">{{ session.lessons.join(', ') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClasses(session.status)">
                            {{ session.status.replace('_', ' ') }}
                        </span>
                    </div>

                    <div v-if="session.meeting && !session.meeting.meeting_url" class="border-border mt-4 border-t pt-4">
                        <p v-if="session.meeting.status === 'failed'" class="text-sm text-red-600">
                            We couldn't create the meeting link for this session.
                        </p>
                        <p v-else class="text-muted text-sm">Setting up the meeting link…</p>
                        <p v-if="retryError" class="mt-1 text-sm text-red-600">{{ retryError }}</p>
                        <button
                            v-if="session.meeting.status === 'failed'"
                            type="button"
                            :disabled="retryingMeetingId === session.id"
                            class="text-accent mt-2 text-sm font-semibold disabled:opacity-40"
                            @click="retryMeeting(session)"
                        >
                            {{ retryingMeetingId === session.id ? 'Retrying…' : 'Retry' }}
                        </button>
                    </div>

                    <div class="border-border mt-4 flex flex-wrap items-center gap-4 border-t pt-4">
                        <a
                            v-if="session.meeting?.meeting_url"
                            :href="session.meeting.meeting_url"
                            target="_blank"
                            rel="noopener"
                            class="text-accent flex items-center gap-1 text-sm font-semibold"
                        >
                            <VideoCameraIcon class="h-4 w-4" /> Open Meeting
                        </a>
                        <template v-if="session.status === 'scheduled'">
                            <button
                                type="button"
                                :disabled="actioningSessionId === session.id"
                                class="text-sm font-semibold text-green-600 disabled:opacity-40"
                                @click="markCompleted(session)"
                            >
                                Mark Completed
                            </button>
                            <button
                                type="button"
                                :disabled="actioningSessionId === session.id"
                                class="text-sm font-semibold text-red-600 disabled:opacity-40"
                                @click="cancelSession(session)"
                            >
                                Cancel
                            </button>
                        </template>
                    </div>
                </li>
            </ul>

            <BookingChat
                v-if="CHAT_VISIBLE_STATUSES.includes(store.currentBooking.status)"
                :api-base-path="`/tutor/bookings/${route.params.id}`"
                :active="store.currentBooking.status === 'confirmed'"
            />
        </template>

        <Modal v-model="formOpen" title="Schedule Session">
            <div class="space-y-4">
                <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

                <div>
                    <p class="text-body mb-1 text-sm font-medium">Lesson (optional)</p>
                    <button
                        type="button"
                        class="bg-card text-body border-border hover:bg-card-alt w-full rounded-xl border px-4 py-3 text-left text-sm"
                        @click="pickerOpen = true"
                    >
                        {{ selectedLesson ? selectedLesson.title : 'Choose a lesson…' }}
                    </button>
                </div>

                <FloatingLabelInput id="schedule-date" v-model="form.date" type="date" label="Date" />
                <div class="grid grid-cols-2 gap-3">
                    <FloatingLabelInput id="schedule-start" v-model="form.start_time" type="time" label="Start Time" />
                    <FloatingLabelInput id="schedule-end" v-model="form.end_time" type="time" label="End Time" />
                </div>

                <div>
                    <p class="text-body mb-1 text-sm font-medium">Meeting Provider</p>
                    <p class="bg-card-alt text-muted border-border rounded-xl border px-4 py-3 text-sm capitalize">
                        {{ settings.meetingProvider.selected ?? 'None selected' }}
                    </p>
                </div>

                <TextareaInput id="schedule-notes" v-model="form.tutor_notes" label="Notes (optional)" :rows="3" />
            </div>

            <template #footer>
                <button type="button" class="border-border text-body rounded-full border px-5 py-2.5 text-sm font-semibold" @click="formOpen = false">
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="submitting"
                    class="bg-amber shadow-elevated rounded-full px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95 disabled:opacity-40"
                    @click="submitForm"
                >
                    {{ submitting ? 'Scheduling…' : 'Schedule' }}
                </button>
            </template>
        </Modal>

        <Modal v-model="pickerOpen" title="Choose Lesson">
            <LessonPicker
                v-if="pickerOpen"
                :service-id="store.currentBooking?.service?.id"
                @selected="onLessonSelected"
                @cancelled="pickerOpen = false"
            />
        </Modal>
    </div>
</template>
