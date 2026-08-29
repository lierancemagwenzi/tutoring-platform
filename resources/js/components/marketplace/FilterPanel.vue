<script setup>
import SelectInput from '../forms/SelectInput.vue'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'

defineProps({
    filters: { type: Object, required: true },
    subjectOptions: { type: Array, required: true },
    gradeOptions: { type: Array, default: () => [] },
    curriculumOptions: { type: Array, required: true },
    categoryOptions: { type: Array, required: true },
    sessionFormatOptions: { type: Array, required: true },
})

const emit = defineEmits(['apply', 'clear'])
</script>

<template>
    <div class="space-y-5">
        <SelectInput id="filter-subject" v-model="filters.subjectId" label="Subject" :options="subjectOptions" />
        <SelectInput id="filter-grade" v-model="filters.gradeId" label="Grade" :options="gradeOptions" />
        <SelectInput id="filter-curriculum" v-model="filters.curriculumId" label="Curriculum" :options="curriculumOptions" />
        <SelectInput id="filter-category" v-model="filters.categoryId" label="Service Category" :options="categoryOptions" />
        <SelectInput id="filter-format" v-model="filters.sessionFormatId" label="Session Format" :options="sessionFormatOptions" />

        <div class="grid grid-cols-2 gap-3">
            <FloatingLabelInput id="filter-price-min" v-model="filters.priceMin" type="number" label="Min Price" />
            <FloatingLabelInput id="filter-price-max" v-model="filters.priceMax" type="number" label="Max Price" />
        </div>

        <FloatingLabelInput id="filter-language" v-model="filters.language" label="Language" />
        <FloatingLabelInput id="filter-experience" v-model="filters.yearsExperienceMin" type="number" label="Minimum Years of Experience" />

        <div class="flex gap-3 pt-2">
            <button
                type="button"
                class="flex-1 rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700"
                @click="emit('clear')"
            >
                Clear Filters
            </button>
            <button
                type="button"
                class="bg-amber flex-1 rounded-full px-5 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95"
                @click="emit('apply')"
            >
                Apply Filters
            </button>
        </div>
    </div>
</template>
