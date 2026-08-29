<script setup>
import { PlayCircleIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'
import { statusBadgeClasses } from '../../utils/statusBadge'

defineProps({
    items: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Continue Learning</h2>

        <div v-if="items.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <PlayCircleIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">Nothing in progress right now. Check your sessions to get started.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="item in items" :key="item.session_lesson_block_id" class="py-3 first:pt-0 last:pb-0">
                <component
                    :is="item.url ? 'router-link' : 'div'"
                    :to="item.url"
                    class="flex items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3">
                        <component :is="blockRegistry[item.block_type]?.icon ?? PlayCircleIcon" class="h-5 w-5 shrink-0 text-gray-400" />
                        <div>
                            <p class="text-ink font-semibold">{{ item.title }}</p>
                            <p class="text-sm text-gray-500">{{ item.lesson_title }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClasses(item.status)">
                        {{ item.status_label }}
                    </span>
                </component>
            </li>
        </ul>
    </div>
</template>
