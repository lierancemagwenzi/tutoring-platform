<script setup>
import { computed } from 'vue'
import { CalendarDaysIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    calendarPreview: { type: Object, default: () => ({ sessions: [], content_release: [], booking_requests: [] }) },
})

const entries = computed(() => {
    const sessionEntries = (props.calendarPreview.sessions ?? []).map((entry) => ({
        key: `session-${entry.session.id}`,
        date: entry.session.date,
        label: `Session · ${entry.session.service.subject?.name ?? entry.session.service.title}`,
        to: `/tutor/sessions/${entry.session.id}`,
        kind: 'Session',
    }))

    const releaseEntries = (props.calendarPreview.content_release ?? []).map((item, index) => ({
        key: `release-${item.session_lesson_block_id}-${index}`,
        date: null,
        label: item.reason_label,
        to: item.manage_url,
        kind: 'Content',
    }))

    const requestEntries = (props.calendarPreview.booking_requests ?? []).map((request) => ({
        key: `request-${request.id}`,
        date: request.date,
        label: `Request · ${request.student.first_name} ${request.student.last_name}`,
        to: '/tutor/booking-requests',
        kind: 'Request',
    }))

    return [...sessionEntries, ...releaseEntries, ...requestEntries].sort((a, b) => {
        if (!a.date) return 1
        if (!b.date) return -1
        return new Date(a.date) - new Date(b.date)
    })
})
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Next Two Weeks</h2>

        <div v-if="entries.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <CalendarDaysIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">Nothing on your calendar for the next two weeks.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-border">
            <li v-for="entry in entries" :key="entry.key" class="py-3 first:pt-0 last:pb-0">
                <router-link :to="entry.to" class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-body font-semibold">{{ entry.label }}</p>
                        <p v-if="entry.date" class="text-xs text-muted">{{ entry.date }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-card-alt px-3 py-1 text-xs font-semibold text-muted">{{ entry.kind }}</span>
                </router-link>
            </li>
        </ul>
    </div>
</template>
