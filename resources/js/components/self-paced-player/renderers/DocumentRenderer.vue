<script setup>
import MediaItemView from '../../lms/MediaItemView.vue'

// Shares the lesson file frame (title, type, size, Open/Download, and an
// inline reader where one exists) so files look the same everywhere.
defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <div class="space-y-4">
        <p v-if="!activity.attachments?.length" class="text-sm text-gray-500">No file has been added yet.</p>
        <MediaItemView v-for="attachment in activity.attachments ?? []" :key="attachment.id" :item="attachment" />
    </div>
</template>
