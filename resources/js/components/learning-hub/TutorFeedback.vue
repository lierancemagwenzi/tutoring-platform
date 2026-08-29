<script setup>
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'
import { formatDate } from '../../utils/formatDateTime'

defineProps({
    feedback: { type: Array, default: () => [] },
})
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Tutor Feedback</h2>

        <div v-if="feedback.length === 0" class="mt-6 flex flex-col items-center py-6 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <ChatBubbleLeftRightIcon class="h-7 w-7" />
            </span>
            <p class="mt-3 text-sm text-gray-500">No feedback from your tutors yet.</p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li v-for="(item, index) in feedback" :key="index" class="py-3 first:pt-0 last:pb-0">
                <component :is="item.url ? 'router-link' : 'div'" :to="item.url" class="block">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-ink font-semibold">{{ item.title }}</p>
                        <span class="shrink-0 text-xs text-gray-400">{{ formatDate(item.date) }}</span>
                    </div>
                    <p v-if="item.tutor" class="text-sm text-gray-500">{{ item.tutor }}</p>
                    <div class="prose prose-sm mt-1 max-w-none text-gray-600" v-html="item.feedback" />
                </component>
            </li>
        </ul>
    </div>
</template>
