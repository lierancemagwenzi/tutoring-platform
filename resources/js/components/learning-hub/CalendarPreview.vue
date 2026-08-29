<script setup>
import { computed } from 'vue'
import { CalendarDaysIcon } from '@heroicons/vue/24/outline'
import { formatDate } from '../../utils/formatDateTime'

const props = defineProps({
    calendarPreview: { type: Object, default: () => ({ sessions: [], deadlines: [] }) },
})

const entries = computed(() => {
    const sessionEntries = (props.calendarPreview.sessions ?? []).map((entry) => ({
        key: `session-${entry.session.id}`,
        date: entry.session.date,
        label: `Session with ${entry.tutor.display_name}`,
        to: `/student/bookings/${entry.booking_id}`,
        kind: 'Session',
    }))

    const deadlineEntries = (props.calendarPreview.deadlines ?? []).map((item) => ({
        key: `deadline-${item.session_lesson_block_id}`,
        date: item.due_date,
        label: item.title,
        to: item.url,
        kind: 'Due',
    }))

    return [...sessionEntries, ...deadlineEntries].sort((a, b) => new Date(a.date) - new Date(b.date))
})
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Next Two Weeks</h2>

        <div v-if="entries.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <CalendarDaysIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">Nothing on your calendar for the next two weeks.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="entry in entries" :key="entry.key" class="py-3 first:pt-0 last:pb-0">
                <component :is="entry.to ? 'router-link' : 'div'" :to="entry.to" class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-ink font-semibold">{{ entry.label }}</p>
                        <p class="text-xs text-gray-400">{{ formatDate(entry.date) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">{{ entry.kind }}</span>
                </component>
            </li>
        </ul>
    </div>
</template>
