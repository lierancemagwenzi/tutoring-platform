<script setup>
import { computed } from 'vue'
import { CalendarDaysIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    availability: { type: Object, default: () => ({ today: null, upcoming: [], unavailable_dates: [] }) },
})

const upcomingPreview = computed(() => props.availability.upcoming.slice(0, 5))
const unavailablePreview = computed(() => props.availability.unavailable_dates.slice(0, 5))
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <div class="flex items-center justify-between">
            <h2 class="text-body text-lg font-bold">Availability</h2>
            <router-link to="/tutor/calendar" class="text-accent text-sm font-semibold">Manage Availability</router-link>
        </div>

        <div v-if="!availability.today && upcomingPreview.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <CalendarDaysIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">You haven't set any availability yet.</p>
            <router-link to="/tutor/calendar" class="text-accent mt-2 text-sm font-semibold">Set your availability</router-link>
        </div>

        <template v-else>
            <div class="mt-4">
                <p class="text-xs font-semibold tracking-wide text-muted uppercase">Today</p>
                <p v-if="!availability.today" class="mt-1 text-sm text-muted">No availability set for today.</p>
                <div v-else class="mt-1 flex flex-wrap gap-2">
                    <span
                        v-for="slot in availability.today.slots"
                        :key="slot.id"
                        class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700"
                    >
                        {{ slot.start_time }} - {{ slot.end_time }}
                    </span>
                </div>
            </div>

            <div v-if="upcomingPreview.length" class="mt-4">
                <p class="text-xs font-semibold tracking-wide text-muted uppercase">Upcoming</p>
                <ul class="mt-1 space-y-1 text-sm text-muted">
                    <li v-for="date in upcomingPreview" :key="date.id">
                        {{ date.date }} &middot; {{ date.slots.length }} slot{{ date.slots.length === 1 ? '' : 's' }}
                    </li>
                </ul>
            </div>

            <div v-if="unavailablePreview.length" class="mt-4">
                <p class="text-xs font-semibold tracking-wide text-muted uppercase">Unavailable</p>
                <p class="mt-1 text-sm text-muted">{{ unavailablePreview.join(', ') }}</p>
            </div>
        </template>
    </div>
</template>
