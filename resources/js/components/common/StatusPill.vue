<script setup>
import { CheckIcon, LockClosedIcon } from '@heroicons/vue/24/solid'
import { computed } from 'vue'

// Generalizes the status-map-object idiom already duplicated in
// StudentAttemptPanel.vue/StudentSubmissionPanel.vue into one shared pill.
const LABELS = {
    locked: 'Locked',
    current: 'Current',
    available: 'Available',
    completed: 'Completed',
    in_progress: 'In Progress',
    incomplete: 'Incomplete',
    passed: 'Passed',
    failed: 'Not Passed',
}

const CLASSES = {
    locked: 'bg-gray-100 text-gray-500',
    current: 'bg-accent/10 text-accent',
    available: 'bg-gray-100 text-gray-600',
    completed: 'bg-green-100 text-green-700',
    in_progress: 'bg-amber-100 text-amber-700',
    incomplete: 'bg-gray-100 text-gray-600',
    passed: 'bg-green-100 text-green-700',
    failed: 'bg-red-100 text-red-700',
}

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
})

const resolvedLabel = computed(() => props.label ?? LABELS[props.status] ?? props.status)
const resolvedClasses = computed(() => CLASSES[props.status] ?? 'bg-gray-100 text-gray-600')
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
        :class="resolvedClasses"
    >
        <LockClosedIcon v-if="status === 'locked'" class="h-3 w-3" />
        <CheckIcon v-else-if="status === 'completed' || status === 'passed'" class="h-3 w-3" />
        {{ resolvedLabel }}
    </span>
</template>
