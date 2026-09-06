<script setup>
import { SparklesIcon } from '@heroicons/vue/24/outline'

// Layout placeholder only — recommendations are a future phase. Kept as
// its own component so a real implementation slots in later without
// touching the details page around it.
</script>

<template>
    <div class="bg-card shadow-elevated rounded-2xl p-6">
        <h2 class="text-body text-lg font-bold">Related Courses</h2>
        <div class="mt-4 flex flex-col items-center py-8 text-center">
            <span class="bg-card-alt text-muted flex h-12 w-12 items-center justify-center rounded-full">
                <SparklesIcon class="h-6 w-6" />
            </span>
            <p class="text-muted mt-3 text-sm">Course recommendations are coming in a future update.</p>
        </div>
    </div>
</template>
