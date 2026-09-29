<script setup>
import { reactive } from 'vue'
import VuePdfEmbed from 'vue-pdf-embed'
import { ArrowTopRightOnSquareIcon } from '@heroicons/vue/24/outline'

defineProps({
    activity: { type: Object, required: true },
})

// The inline viewer fetches the file from JS, which cross-origin storage can
// refuse (CORS) even though a plain link to the same URL works — so always
// offer the link, and say so plainly when the inline viewer gives up.
const failed = reactive(new Set())
</script>

<template>
    <div class="space-y-6">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No PDF has been added yet.</p>
        <div v-for="attachment in activity.attachments ?? []" :key="attachment.id" class="space-y-2">
            <div v-if="!failed.has(attachment.id)" class="overflow-hidden rounded-xl border border-gray-100">
                <VuePdfEmbed
                    :source="attachment.url"
                    @loading-failed="failed.add(attachment.id)"
                    @rendering-failed="failed.add(attachment.id)"
                />
            </div>
            <p v-else class="rounded-xl bg-card-alt px-4 py-6 text-center text-sm text-muted">
                This PDF can't be shown here. Open it in a new tab to read or download it.
            </p>

            <a
                :href="attachment.url"
                target="_blank"
                rel="noopener"
                class="text-accent inline-flex items-center gap-1.5 text-sm font-semibold"
            >
                <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                Open {{ attachment.title || attachment.original_name || 'PDF' }} in a new tab
            </a>
        </div>
    </div>
</template>
