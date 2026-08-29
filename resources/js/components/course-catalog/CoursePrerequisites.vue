<script setup>
defineProps({
    prerequisites: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="prerequisites.length" class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Prerequisites</h2>
        <ul class="mt-3 list-inside list-disc space-y-1 text-gray-600">
            <li v-for="(prerequisite, index) in prerequisites" :key="index">{{ prerequisite }}</li>
        </ul>
    </div>
</template>
