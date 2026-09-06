<script setup>
defineProps({
    audience: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="audience.length" class="bg-card shadow-elevated rounded-2xl p-6">
        <h2 class="text-body text-lg font-bold">Who this course is for</h2>
        <ul class="text-muted mt-3 list-inside list-disc space-y-1">
            <li v-for="(entry, index) in audience" :key="index">{{ entry }}</li>
        </ul>
    </div>
</template>
