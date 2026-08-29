<script setup>
import { ClockIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'
import { formatRelativeTime } from '../../utils/formatDateTime'

defineProps({
    activity: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Recent Student Activity</h2>

        <div v-if="activity.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <ClockIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">No recent activity from your students yet.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="item in activity" :key="`${item.session_lesson_block_id}-${item.student_id}`" class="py-3 first:pt-0 last:pb-0">
                <router-link :to="item.url" class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <component :is="blockRegistry[item.block_type]?.icon ?? ClockIcon" class="h-5 w-5 shrink-0 text-gray-400" />
                        <div>
                            <p class="text-ink font-semibold">{{ item.student_name }}</p>
                            <p class="text-sm text-gray-500">{{ item.status_label }} &middot; {{ item.title }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-xs text-gray-400">{{ formatRelativeTime(item.activity_at) }}</span>
                </router-link>
            </li>
        </ul>
    </div>
</template>
