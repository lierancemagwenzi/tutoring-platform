<script setup>
import { RectangleStackIcon } from '@heroicons/vue/24/outline'

defineProps({
    course: { type: Object, required: true },
})
</script>

<template>
    <div>
        <div class="overflow-hidden rounded-2xl bg-gray-100">
            <img
                v-if="course.thumbnail_path"
                :src="`/storage/${course.thumbnail_path}`"
                :alt="course.title"
                class="h-64 w-full object-cover"
            />
            <div v-else class="from-panel-start to-panel-end flex h-64 w-full items-center justify-center bg-gradient-to-b text-white/70">
                <RectangleStackIcon class="h-16 w-16" />
            </div>
        </div>

        <video v-if="course.promo_video_path" :src="`/storage/${course.promo_video_path}`" controls class="mt-4 w-full rounded-2xl bg-black" />

        <h1 class="text-ink mt-6 text-3xl font-bold">{{ course.title }}</h1>
        <p v-if="course.subtitle" class="mt-2 text-lg text-gray-500">{{ course.subtitle }}</p>

        <router-link
            v-if="course.tutor"
            :to="`/student/marketplace/tutors/${course.tutor.id}`"
            class="text-accent mt-2 inline-block text-sm font-semibold"
        >
            By {{ course.tutor.display_name }}
        </router-link>

        <div class="mt-4 flex flex-wrap gap-2 text-sm">
            <span v-if="course.subject" class="bg-accent/10 text-accent rounded-full px-3 py-1 font-medium">{{ course.subject.name }}</span>
            <span v-if="course.grade" class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">{{ course.grade.name }}</span>
            <span v-if="course.difficulty" class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700 capitalize">
                {{ course.difficulty }}
            </span>
            <span v-if="course.language" class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">{{ course.language }}</span>
            <span v-if="course.estimated_duration_minutes" class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">
                {{ Math.round(course.estimated_duration_minutes / 60) }}h estimated
            </span>
        </div>
    </div>
</template>
