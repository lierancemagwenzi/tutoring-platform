<script setup>
import { computed } from 'vue'
import { CalendarDaysIcon, CheckBadgeIcon, ClipboardDocumentCheckIcon, InboxArrowDownIcon, UserGroupIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    stats: { type: Object, default: null },
})

const cards = computed(() => [
    { icon: UserGroupIcon, label: 'Active Students', value: props.stats?.active_students ?? 0, to: '/tutor/sessions' },
    { icon: CalendarDaysIcon, label: 'Upcoming Sessions', value: props.stats?.upcoming_sessions ?? 0, to: '/tutor/sessions' },
    { icon: ClipboardDocumentCheckIcon, label: 'Pending Reviews', value: props.stats?.pending_reviews ?? 0, to: '/tutor/sessions' },
    { icon: InboxArrowDownIcon, label: 'Booking Requests', value: props.stats?.pending_booking_requests ?? 0, to: '/tutor/booking-requests' },
    { icon: CheckBadgeIcon, label: 'Sessions Today', value: props.stats?.sessions_today ?? 0, to: '/tutor/sessions' },
])
</script>

<template>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <router-link
            v-for="card in cards"
            :key="card.label"
            :to="card.to"
            class="rounded-2xl bg-card p-5 shadow-elevated transition hover:shadow-popover"
        >
            <span class="bg-amber/10 text-amber flex h-9 w-9 items-center justify-center rounded-lg">
                <component :is="card.icon" class="h-5 w-5" />
            </span>
            <p class="text-body mt-3 text-xl font-bold">{{ card.value }}</p>
            <p class="mt-1 text-xs text-muted">{{ card.label }}</p>
        </router-link>
    </div>
</template>
