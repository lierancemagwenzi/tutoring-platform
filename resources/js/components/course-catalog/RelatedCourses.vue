<script setup>
import { SparklesIcon } from '@heroicons/vue/24/outline'

// Layout placeholder only — recommendations are a future phase. Kept as
// its own component so a real implementation slots in later without
// touching the details page around it.
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Related Courses</h2>
        <div class="mt-4 flex flex-col items-center py-8 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <SparklesIcon class="h-6 w-6" />
            </span>
            <p class="mt-3 text-sm text-gray-500">Course recommendations are coming in a future update.</p>
        </div>
    </div>
</template>
