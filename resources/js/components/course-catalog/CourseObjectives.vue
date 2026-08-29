<script setup>
defineProps({
    objectives: { type: Array, default: () => [] },
})
</script>

<template>
    <div v-if="objectives.length" class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">What you'll learn</h2>
        <ul class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <li v-for="(objective, index) in objectives" :key="index" class="flex items-start gap-2 text-gray-600">
                <span class="mt-0.5 shrink-0 text-green-500">&check;</span>
                {{ objective }}
            </li>
        </ul>
    </div>
</template>
