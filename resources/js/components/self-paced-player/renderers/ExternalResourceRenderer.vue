<script setup>
import { LinkIcon } from '@heroicons/vue/24/outline'

defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <div>
        <p v-if="!activity.content?.url" class="text-sm text-gray-500">No external link has been added yet.</p>
        <a
            v-else
            :href="activity.content.url"
            target="_blank"
            rel="noopener"
            class="flex items-center gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-accent"
        >
            <span class="bg-accent/10 text-accent flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                <LinkIcon class="h-5 w-5" />
            </span>
            <span class="text-accent text-sm font-semibold break-all">{{ activity.content.url }}</span>
        </a>
    </div>
</template>
