<script setup>
defineProps({
    prerequisites: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="prerequisites.length" class="bg-card shadow-elevated rounded-2xl p-6">
        <h2 class="text-body text-lg font-bold">Prerequisites</h2>
        <ul class="text-muted mt-3 list-inside list-disc space-y-1">
            <li v-for="(prerequisite, index) in prerequisites" :key="index">{{ prerequisite }}</li>
        </ul>
    </div>
</template>
