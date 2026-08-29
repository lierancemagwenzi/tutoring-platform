<script setup>
const TABS = [
    { value: 'services', label: 'Tutoring Services' },
    { value: 'courses', label: 'Self-Paced Courses' },
]

defineProps({
    modelValue: { type: String, required: true },
})

defineEmits(['update:modelValue'])
</script>

<template>
    <div class="flex gap-2 border-b border-gray-200">
        <button
            v-for="tab in TABS"
            :key="tab.value"
            type="button"
            class="border-b-2 px-4 py-2.5 text-sm font-semibold transition"
            :class="modelValue === tab.value ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700'"
            @click="$emit('update:modelValue', tab.value)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
