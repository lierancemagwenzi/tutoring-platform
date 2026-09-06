<script setup>
import { CalendarDaysIcon, VideoCameraIcon } from '@heroicons/vue/24/outline'

defineProps({
    sessions: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="bg-card rounded-2xl p-6 shadow-elevated">
        <div class="flex items-center justify-between">
            <h2 class="text-body text-lg font-bold">Today's Sessions</h2>
            <router-link to="/student/bookings" class="text-accent text-sm font-semibold">View all</router-link>
        </div>

        <div v-if="sessions.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="bg-card-alt text-muted flex h-14 w-14 items-center justify-center rounded-full">
                <CalendarDaysIcon class="h-7 w-7" />
            </span>
            <p class="text-muted mt-3 text-sm">No sessions scheduled for today.</p>
        </div>

        <ul v-else class="divide-border mt-4 divide-y">
            <li v-for="entry in sessions" :key="entry.session.id" class="py-3 first:pt-0 last:pb-0">
                <router-link :to="`/student/bookings/${entry.booking_id}`" class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-body font-semibold">{{ entry.tutor.display_name }}</p>
                        <p class="text-muted text-sm">{{ entry.session.service.title }}</p>
                        <p v-if="entry.lessons.length" class="text-muted mt-1 text-xs">{{ entry.lessons.join(', ') }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-body text-sm font-semibold">{{ entry.session.start_time }} - {{ entry.session.end_time }}</p>
                        <p v-if="entry.session.meeting" class="mt-1 flex items-center justify-end gap-1 text-xs text-green-600">
                            <VideoCameraIcon class="h-4 w-4" /> Meeting ready
                        </p>
                    </div>
                </router-link>
            </li>
        </ul>
    </div>
</template>
