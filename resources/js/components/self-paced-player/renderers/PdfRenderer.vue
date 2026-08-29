<script setup>
import VuePdfEmbed from 'vue-pdf-embed'

defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <div class="space-y-4">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No PDF has been added yet.</p>
        <div v-for="attachment in activity.attachments ?? []" :key="attachment.id" class="overflow-hidden rounded-xl border border-gray-100">
            <VuePdfEmbed :source="attachment.url" />
        </div>
    </div>
</template>
