<script setup>
import SelectInput from '../forms/SelectInput.vue'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'

const DIFFICULTY_OPTIONS = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'advanced', label: 'Advanced' },
]

const PRICE_OPTIONS = [
    { value: 'free', label: 'Free' },
    { value: 'paid', label: 'Paid' },
]

const props = defineProps({
    filters: { type: Object, required: true },
    subjectOptions: { type: Array, default: () => [] },
    gradeOptions: { type: Array, default: () => [] },
    languageOptions: { type: Array, default: () => [] },
    tutorOptions: { type: Array, default: () => [] },
})

defineEmits(['apply', 'clear'])
</script>

<template>
    <div class="space-y-4">
        <SelectInput id="course-filter-subject" v-model="filters.subjectId" label="Subject" :options="subjectOptions" />
        <SelectInput id="course-filter-grade" v-model="filters.gradeId" label="Grade" :options="gradeOptions" />
        <SelectInput id="course-filter-difficulty" v-model="filters.difficulty" label="Difficulty" :options="DIFFICULTY_OPTIONS" />
        <SelectInput id="course-filter-language" v-model="filters.language" label="Language" :options="languageOptions" />
        <SelectInput id="course-filter-price" v-model="filters.price" label="Price" :options="PRICE_OPTIONS" />
        <SelectInput id="course-filter-tutor" v-model="filters.tutorId" label="Tutor" :options="tutorOptions" />
        <FloatingLabelInput
            id="course-filter-duration"
            v-model="filters.durationMax"
            type="number"
            label="Max Duration (minutes)"
        />

        <div class="flex gap-3 pt-2">
            <button
                type="button"
                class="bg-amber shadow-elevated flex-1 rounded-full px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-95"
                @click="$emit('apply')"
            >
                Apply Filters
            </button>
            <button
                type="button"
                class="border-border text-body rounded-full border px-4 py-2.5 text-sm font-semibold"
                @click="$emit('clear')"
            >
                Clear
            </button>
        </div>
    </div>
</template>
