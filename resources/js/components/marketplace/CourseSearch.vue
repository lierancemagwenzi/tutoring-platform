<script setup>
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'
import SelectInput from '../forms/SelectInput.vue'

const SORT_OPTIONS = [
    { value: 'newest', label: 'Newest' },
    { value: 'most_popular', label: 'Most Popular' },
    { value: 'highest_rated', label: 'Highest Rated' },
    { value: 'price_low', label: 'Price: Low to High' },
    { value: 'price_high', label: 'Price: High to Low' },
    { value: 'alphabetical', label: 'Alphabetical' },
]

const search = defineModel('search', { type: String, default: '' })
const sort = defineModel('sort', { type: String, default: '' })

defineEmits(['search'])
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row">
        <FloatingLabelInput
            id="course-search"
            v-model="search"
            label="Search courses by title, tutor, or subject"
            class="flex-1"
            @keyup.enter="$emit('search')"
        />
        <SelectInput id="course-sort" v-model="sort" label="Sort by" :options="SORT_OPTIONS" class="sm:w-56" />
        <button
            type="button"
            class="bg-amber shadow-elevated flex items-center justify-center gap-2 rounded-full px-6 py-3 font-semibold text-white transition hover:brightness-95"
            @click="$emit('search')"
        >
            <MagnifyingGlassIcon class="h-5 w-5" />
            Search
        </button>
    </div>
</template>
