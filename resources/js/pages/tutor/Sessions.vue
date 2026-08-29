<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTutorBookingsStore } from '../../stores/tutorBookings'

const STATUS_CLASSES = {
    scheduled: 'bg-blue-100 text-blue-700',
    completed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-gray-100 text-gray-500',
}

const MEETING_STATUS_CLASSES = {
    scheduled: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
}

const router = useRouter()
const store = useTutorBookingsStore()

const loading = ref(true)

onMounted(async () => {
    loading.value = true
    try {
        await store.fetchSessions()
    } finally {
        loading.value = false
    }
})

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

function meetingClasses(status) {
    return MEETING_STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

function viewSession(session) {
    router.push(`/tutor/sessions/${session.id}`)
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">Sessions</h1>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.sessions.length === 0" class="mt-16 text-center text-gray-500">No sessions scheduled yet.</div>

        <div v-else class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="session in store.sessions" :key="session.id" class="flex flex-col rounded-2xl bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm text-gray-500">{{ session.service.subject.name }}</p>
                        <p class="text-ink font-bold">{{ session.service.title }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusClasses(session.status)">
                        {{ session.status }}
                    </span>
                </div>

                <div class="mt-3 space-y-1 text-sm text-gray-500">
                    <p>{{ session.date }} &middot; {{ session.start_time }} - {{ session.end_time }}</p>
                    <p>{{ session.service.session_format.name }}</p>
                    <p>{{ session.participants_count }} / {{ session.capacity }} participants</p>
                </div>

                <span
                    v-if="session.meeting"
                    class="mt-3 w-fit rounded-full px-3 py-1 text-xs font-semibold capitalize"
                    :class="meetingClasses(session.meeting.status)"
                >
                    Meeting: {{ session.meeting.status }}
                </span>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    <button type="button" class="text-accent text-sm font-semibold" @click="viewSession(session)">View Session</button>
                </div>
            </div>
        </div>
    </div>
</template>
