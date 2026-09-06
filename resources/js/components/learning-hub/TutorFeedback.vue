<script setup>
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { formatDate } from '../../utils/formatDateTime'

defineProps({
    feedback: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="bg-card rounded-2xl p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Tutor Feedback</h2>

        <div v-if="feedback.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="bg-card-alt text-muted flex h-14 w-14 items-center justify-center rounded-full">
                <ChatBubbleLeftRightIcon class="h-7 w-7" />
            </span>
            <p class="text-muted mt-3 text-sm">No feedback from your tutors yet.</p>
        </div>

        <ul v-else class="divide-border mt-4 divide-y">
            <li v-for="(item, index) in feedback" :key="index" class="py-3 first:pt-0 last:pb-0">
                <component :is="item.url ? 'router-link' : 'div'" :to="item.url" class="block">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-body font-semibold">{{ item.title }}</p>
                        <span class="text-muted shrink-0 text-xs">{{ formatDate(item.date) }}</span>
                    </div>
                    <p v-if="item.tutor" class="text-muted text-sm">{{ item.tutor }}</p>
                    <div class="prose prose-sm text-muted mt-1 max-w-none" v-html="item.feedback" />
                </component>
            </li>
        </ul>
    </div>
</template>
