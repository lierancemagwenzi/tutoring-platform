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
            <div v-for="n in 6" :key="n" class="bg-card shadow-elevated animate-pulse rounded-2xl p-5">
                <div class="bg-card-alt h-36 rounded-xl" />
                <div class="mt-4 space-y-2">
                    <div class="bg-card-alt h-4 w-2/3 rounded" />
                    <div class="bg-card-alt h-3 w-full rounded" />
                </div>
            </div>
        </div>

        <div v-else-if="courses.length === 0" class="bg-card shadow-elevated flex flex-col items-center rounded-2xl py-24 text-center">
            <span class="bg-card-alt text-muted flex h-16 w-16 items-center justify-center rounded-full">
                <RectangleStackIcon class="h-8 w-8" />
            </span>
            <p class="text-muted mt-4">No courses match your search.</p>
        </div>

        <template v-else>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <SelfPacedCourseCard v-for="course in courses" :key="course.id" :course="course" />
            </div>

            <div v-if="meta.last_page > 1" class="mt-8 flex items-center justify-center gap-4">
                <button
                    type="button"
                    :disabled="meta.current_page <= 1"
                    class="border-border text-muted flex h-10 w-10 items-center justify-center rounded-full border disabled:cursor-not-allowed disabled:opacity-40"
                    @click="goToPage(meta.current_page - 1)"
                >
                    <ChevronLeftIcon class="h-5 w-5" />
                </button>
                <span class="text-muted text-sm">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
                <button
                    type="button"
                    :disabled="meta.current_page >= meta.last_page"
                    class="border-border text-muted flex h-10 w-10 items-center justify-center rounded-full border disabled:cursor-not-allowed disabled:opacity-40"
                    @click="goToPage(meta.current_page + 1)"
                >
                    <ChevronRightIcon class="h-5 w-5" />
                </button>
            </div>
        </template>
    </div>
</template>
