<script setup>
import { RectangleStackIcon } from '@heroicons/vue/24/outline'
import ProgressBar from '../common/ProgressBar.vue'

defineProps({
    enrollment: { type: Object, required: true },
})
</script>

<template>
    <div class="flex flex-col rounded-2xl bg-white p-5 shadow-sm">
        <div class="relative -mx-5 -mt-5 mb-3 h-36 overflow-hidden rounded-t-2xl bg-gray-100">
            <img
                v-if="enrollment.course.thumbnail_path"
                :src="`/storage/${enrollment.course.thumbnail_path}`"
                :alt="enrollment.course.title"
                class="h-full w-full object-cover"
            />
            <div v-else class="from-panel-start to-panel-end flex h-full w-full items-center justify-center bg-gradient-to-b text-white/70">
                <RectangleStackIcon class="h-10 w-10" />
            </div>
        </div>

        <p class="text-ink line-clamp-2 font-bold">{{ enrollment.course.title }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ enrollment.course.tutor?.display_name }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500">
            <span v-if="enrollment.course.subject" class="bg-accent/10 text-accent rounded-full px-3 py-1 font-medium">
                {{ enrollment.course.subject.name }}
            </span>
            <span v-if="enrollment.course.grade" class="rounded-full bg-gray-100 px-3 py-1 font-medium">{{ enrollment.course.grade.name }}</span>
            <span v-if="enrollment.course.difficulty" class="rounded-full bg-gray-100 px-3 py-1 font-medium capitalize">
                {{ enrollment.course.difficulty }}
            </span>
        </div>

        <p class="mt-3 text-xs text-gray-400">Enrolled {{ new Date(enrollment.enrolled_at).toLocaleDateString() }}</p>

        <div v-if="enrollment.progress_percentage !== null" class="mt-3">
            <div class="flex items-center justify-between text-xs text-gray-500">
                <span>Progress</span>
                <span>{{ enrollment.progress_percentage }}%</span>
            </div>
            <div class="mt-1">
                <ProgressBar :percentage="enrollment.progress_percentage" size="sm" />
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4">
            <router-link
                :to="`/student/self-paced-courses/${enrollment.course.id}/learn`"
                class="bg-amber rounded-full px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
            >
                {{ enrollment.status === 'completed' ? 'Review Course' : 'Continue Course' }}
            </router-link>
            <router-link
                v-if="enrollment.status === 'completed' && enrollment.certificate"
                :to="`/student/self-paced-courses/${enrollment.course.id}/learn/certificate`"
                class="text-accent text-sm font-semibold"
            >
                View Certificate
            </router-link>
            <router-link v-else :to="`/student/marketplace/courses/${enrollment.course.id}`" class="text-accent text-sm font-semibold">
                View Course
            </router-link>
        </div>
    </div>
</template>
