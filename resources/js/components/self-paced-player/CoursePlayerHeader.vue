<script setup>
import { Bars3Icon } from '@heroicons/vue/24/outline'
import { CheckBadgeIcon } from '@heroicons/vue/24/solid'
import ProgressBar from '../common/ProgressBar.vue'

defineProps({
    course: { type: Object, required: true },
})

const emit = defineEmits(['toggle-sidebar'])
</script>

<template>
    <header class="bg-ink flex flex-col gap-3 px-4 py-3 text-white sm:px-6">
        <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="text-white lg:hidden" aria-label="Toggle curriculum" @click="emit('toggle-sidebar')">
                    <Bars3Icon class="h-6 w-6" />
                </button>
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ course.title }}</p>
                    <p class="truncate text-xs text-white/60">{{ course.tutor?.display_name }}</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span
                    v-if="course.enrollment.status === 'completed'"
                    class="inline-flex items-center gap-1 rounded-full bg-green-500/20 px-3 py-1 text-xs font-semibold text-green-300"
                >
                    <CheckBadgeIcon class="h-4 w-4" />
                    Certificate Earned
                </span>
                <router-link to="/student/my-courses" class="text-xs font-semibold text-white/70 hover:text-white"> Exit </router-link>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex-1">
                <ProgressBar :percentage="course.progress.overall_percentage" size="sm" />
            </div>
            <span class="shrink-0 text-xs text-white/70">
                {{ course.progress.completed_chapters }} / {{ course.progress.total_chapters }} chapters &middot;
                {{ course.progress.overall_percentage }}%
            </span>
        </div>

        <p v-if="course.progress.current_chapter_title" class="text-xs text-white/50">
            Current chapter: {{ course.progress.current_chapter_title }}
        </p>
    </header>
</template>
