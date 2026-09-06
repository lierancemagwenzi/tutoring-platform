<script setup>
import { ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'
import { blockRegistry } from '../../lms/blockRegistry'
import { statusBadgeClasses } from '../../utils/statusBadge'
import { formatDate } from '../../utils/formatDateTime'

defineProps({
    reviews: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Pending Reviews</h2>

        <div v-if="reviews.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-card-alt text-muted">
                <ClipboardDocumentCheckIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-muted">No submissions awaiting review.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-border">
            <li v-for="item in reviews" :key="`${item.session_lesson_block_id}-${item.student_id}`" class="py-3 first:pt-0 last:pb-0">
                <router-link :to="item.url" class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <component :is="blockRegistry[item.block_type]?.icon ?? ClipboardDocumentCheckIcon" class="h-5 w-5 shrink-0 text-muted" />
                        <div>
                            <p class="text-body font-semibold">{{ item.student_name }}</p>
                            <p class="text-sm text-muted">{{ item.title }} &middot; {{ item.lesson_title }}</p>
                            <p class="mt-0.5 text-xs text-muted">Submitted {{ formatDate(item.submitted_at) }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="statusBadgeClasses(item.status)">
                        {{ item.status_label }}
                    </span>
                </router-link>
            </li>
        </ul>
    </div>
</template>
