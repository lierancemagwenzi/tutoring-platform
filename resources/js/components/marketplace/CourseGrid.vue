<script setup>
import { ChevronLeftIcon, ChevronRightIcon, RectangleStackIcon } from '@heroicons/vue/24/outline'
import SelfPacedCourseCard from './SelfPacedCourseCard.vue'

const props = defineProps({
    courses: { type: Array, required: true },
    meta: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    errorMessage: { type: String, default: '' },
})

const emit = defineEmits(['page'])

function goToPage(page) {
    if (page < 1 || page > props.meta.last_page) {
        return
    }
    emit('page', page)
}
</script>

<template>
    <div>
        <p v-if="errorMessage" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <div v-if="loading" class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="n in 6" :key="n" class="animate-pulse rounded-2xl bg-white p-5 shadow-sm">
                <div class="h-36 rounded-xl bg-gray-200" />
                <div class="mt-4 space-y-2">
                    <div class="h-4 w-2/3 rounded bg-gray-200" />
                    <div class="h-3 w-full rounded bg-gray-200" />
                </div>
            </div>
        </div>

        <div v-else-if="courses.length === 0" class="flex flex-col items-center rounded-2xl bg-white py-24 text-center shadow-sm">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <RectangleStackIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-gray-500">No courses match your search.</p>
        </div>

        <template v-else>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <SelfPacedCourseCard v-for="course in courses" :key="course.id" :course="course" />
            </div>

            <div v-if="meta.last_page > 1" class="mt-8 flex items-center justify-center gap-4">
                <button
                    type="button"
                    :disabled="meta.current_page <= 1"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-600 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="goToPage(meta.current_page - 1)"
                >
                    <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <span class="text-sm text-gray-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
                <button
                    type="button"
                    :disabled="meta.current_page >= meta.last_page"
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-600 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="goToPage(meta.current_page + 1)"
                >
                    <ChevronRightIcon class="h-5 w-5" />
                </button>
            </div>
        </template>
    </div>
</template>
