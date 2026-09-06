<script setup>
import { ChartBarIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'
import { statusBadgeClasses } from '../../utils/statusBadge'

defineProps({
    items: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="bg-card rounded-2xl p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Recent Results</h2>

        <div v-if="items.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="bg-card-alt text-muted flex h-14 w-14 items-center justify-center rounded-full">
                <ChartBarIcon class="h-7 w-7" />
            </span>
            <p class="text-muted mt-3 text-sm">No results yet. Your graded work will show up here.</p>
        </div>

        <ul v-else class="divide-border mt-4 divide-y">
            <li v-for="item in items" :key="item.session_lesson_block_id" class="py-3 first:pt-0 last:pb-0">
                <component
                    :is="item.url ? 'router-link' : 'div'"
                    :to="item.url"
                    class="flex items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3">
                        <component :is="blockRegistry[item.block_type]?.icon ?? ChartBarIcon" class="text-muted h-5 w-5 shrink-0" />
                        <div>
                            <p class="text-body font-semibold">{{ item.title }}</p>
                            <p class="text-muted text-sm">{{ item.lesson_title }}</p>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <p v-if="item.percentage !== null" class="text-body text-sm font-bold">{{ item.percentage }}%</p>
                        <span class="mt-1 inline-block rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClasses(item.status)">
                            {{ item.status_label }}
                        </span>
                    </div>
                </component>
            </li>
        </ul>
    </div>
</template>
