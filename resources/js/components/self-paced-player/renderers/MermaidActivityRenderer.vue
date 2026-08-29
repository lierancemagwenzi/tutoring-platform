<script setup>
// Thin prop adapter so the registry can bind every entry the same way
// (:activity="activity") — the actual rendering is entirely
// MermaidRender.vue's, reused unmodified.
import MermaidRender from '../../lms/MermaidRender.vue'

defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <MermaidRender :diagram="activity.content?.syntax" />
</template>
