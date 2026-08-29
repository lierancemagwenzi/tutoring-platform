<script setup>
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline'

defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <div class="space-y-3">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No download has been added yet.</p>
        <a
            v-for="attachment in activity.attachments ?? []"
            :key="attachment.id"
            :href="attachment.url"
            target="_blank"
            rel="noopener"
            download
            class="flex items-center gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-accent"
        >
            <span class="bg-accent/10 text-accent flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                <ArrowDownTrayIcon class="h-5 w-5" />
            </span>
            <span class="text-sm font-semibold text-gray-700">
                {{ attachment.title || attachment.original_name || 'Download file' }}
            </span>
        </a>
    </div>
</template>
