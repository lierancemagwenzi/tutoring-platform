<script setup>
import { CheckCircleIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'
import { statusBadgeClasses } from '../../utils/statusBadge'
import { formatDate } from '../../utils/formatDateTime'

defineProps({
    items: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="bg-card rounded-2xl p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Pending Activities</h2>

        <div v-if="items.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="bg-card-alt text-muted flex h-14 w-14 items-center justify-center rounded-full">
                <CheckCircleIcon class="h-7 w-7" />
            </span>
            <p class="text-muted mt-3 text-sm">You're all caught up!</p>
        </div>

        <ul v-else class="divide-border mt-4 divide-y">
            <li v-for="item in items" :key="item.session_lesson_block_id" class="py-3 first:pt-0 last:pb-0">
                <component
                    :is="item.url ? 'router-link' : 'div'"
                    :to="item.url"
                    class="flex items-center justify-between gap-3"
                >
                    <div class="flex items-center gap-3">
                        <component :is="blockRegistry[item.block_type]?.icon ?? CheckCircleIcon" class="text-muted h-5 w-5 shrink-0" />
                        <div>
                            <p class="text-body font-semibold">{{ item.title }}</p>
                            <p class="text-muted text-sm">
                                {{ item.lesson_title }}<span v-if="item.due_date"> &middot; Due {{ formatDate(item.due_date) }}</span>
                            </p>
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
