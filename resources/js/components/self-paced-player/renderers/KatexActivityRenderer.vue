<script setup>
import KatexRender from '../../lms/KatexRender.vue'

defineProps({
    activity: { type: Object, required: true },
})
</script>

<template>
    <KatexRender :latex="activity.content?.latex" :display-mode="true" />
</template>
