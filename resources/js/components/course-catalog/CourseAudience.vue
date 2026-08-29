<script setup>
defineProps({
    audience: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="audience.length" class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Who this course is for</h2>
        <ul class="mt-3 list-inside list-disc space-y-1 text-gray-600">
            <li v-for="(entry, index) in audience" :key="index">{{ entry }}</li>
        </ul>
    </div>
</template>
