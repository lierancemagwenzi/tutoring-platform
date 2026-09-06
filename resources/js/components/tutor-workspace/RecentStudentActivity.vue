<script setup>
import { ClockIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'
import { formatRelativeTime } from '../../utils/formatDateTime'

defineProps({
    activity: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Recent Student Activity</h2>

        <div v-if="activity.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <ClockIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">No recent activity from your students yet.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-border">
            <li v-for="item in activity" :key="`${item.session_lesson_block_id}-${item.student_id}`" class="py-3 first:pt-0 last:pb-0">
                <router-link :to="item.url" class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <component :is="blockRegistry[item.block_type]?.icon ?? ClockIcon" class="h-5 w-5 shrink-0 text-muted" />
                        <div>
                            <p class="text-body font-semibold">{{ item.student_name }}</p>
                            <p class="text-sm text-muted">{{ item.status_label }} &middot; {{ item.title }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-xs text-muted">{{ formatRelativeTime(item.activity_at) }}</span>
                </router-link>
            </li>
        </ul>
    </div>
</template>
