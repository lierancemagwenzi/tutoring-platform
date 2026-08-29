<script setup>
import { computed } from 'vue'

const props = defineProps({
    percentage: { type: Number, required: true },
    size: { type: String, default: 'md' }, // 'sm' | 'md'
})

const clamped = computed(() => Math.max(0, Math.min(100, props.percentage ?? 0)))
const heightClass = computed(() => (props.size === 'sm' ? 'h-1.5' : 'h-2.5'))
</script>

<template>
    <div class="w-full overflow-hidden rounded-full bg-gray-100" :class="heightClass">
        <div
            class="bg-amber h-full rounded-full transition-all duration-300"
            :style="{ width: `${clamped}%` }"
        />
    </div>
</template>
