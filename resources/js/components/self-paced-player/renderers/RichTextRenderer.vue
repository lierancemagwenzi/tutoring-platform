<script setup>
defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <div class="prose prose-sm max-w-none" v-html="activity.content?.html" />
</template>
