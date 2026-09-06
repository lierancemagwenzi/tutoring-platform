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
    <div class="border-border flex gap-2 border-b">
        <button
            v-for="tab in TABS"
            :key="tab.value"
            type="button"
            class="border-b-2 px-4 py-2.5 text-sm font-semibold transition"
            :class="modelValue === tab.value ? 'border-accent text-accent' : 'text-muted hover:text-body border-transparent'"
            @click="$emit('update:modelValue', tab.value)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
