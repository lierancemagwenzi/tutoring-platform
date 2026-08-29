<script setup>
import { CalendarDaysIcon, UserGroupIcon, VideoCameraIcon } from '@heroicons/vue/24/outline'

defineProps({
    sessions: { type: Array, default: () => [] },
})

function studentNames(session) {
    return session.bookings
        .map((booking) => `${booking.student.first_name} ${booking.student.last_name}`)
        .join(', ')
}
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-ink text-lg font-bold">Today's Sessions</h2>
            <router-link to="/tutor/sessions" class="text-accent text-sm font-semibold">View all</router-link>
        </div>

        <div v-if="sessions.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <CalendarDaysIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">No sessions scheduled for today.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="entry in sessions" :key="entry.session.id" class="py-3 first:pt-0 last:pb-0">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-ink font-semibold">{{ entry.session.service.subject?.name ?? entry.session.service.title }}</p>
                        <p class="text-sm text-gray-500">{{ studentNames(entry.session) }}</p>
                        <p v-if="entry.lessons.length" class="mt-1 text-xs text-gray-400">{{ entry.lessons.join(', ') }}</p>
                        <p v-if="entry.session.participants_count > 1" class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                            <UserGroupIcon class="h-3.5 w-3.5" /> {{ entry.session.participants_count }} participants
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-ink text-sm font-semibold">{{ entry.session.start_time }} - {{ entry.session.end_time }}</p>
                        <p v-if="entry.session.meeting" class="mt-1 flex items-center justify-end gap-1 text-xs text-green-600">
                            <VideoCameraIcon class="h-4 w-4" /> Meeting ready
                        </p>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-4 text-sm font-semibold">
                    <a
                        v-if="entry.session.meeting"
                        :href="entry.session.meeting.meeting_url"
                        target="_blank"
                        rel="noopener"
                        class="text-green-600"
                    >
                        Join Meeting
                    </a>
                    <router-link :to="`/tutor/sessions/${entry.session.id}`" class="text-accent">Open Session</router-link>
                    <router-link :to="`/tutor/sessions/${entry.session.id}/content`" class="text-accent">Prepare Lesson</router-link>
                </div>
            </li>
        </ul>
    </div>
</template>
