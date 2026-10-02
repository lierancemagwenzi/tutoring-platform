<script setup>
import ContentItemsView from '../../lms/ContentItemsView.vue'

// A Content activity: the Insider's text, maths, diagram and file items, in
// order. File items point at the activity's attachments.
defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <ContentItemsView :items="activity.content?.items ?? []" :media="activity.attachments ?? []" />
</template>
