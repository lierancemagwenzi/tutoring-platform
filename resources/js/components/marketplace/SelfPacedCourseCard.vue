<script setup>
import { RectangleStackIcon } from '@heroicons/vue/24/outline'

defineProps({
    course: { type: Object, required: true },
})
</script>

<template>
    <div class="flex flex-col rounded-2xl bg-white p-5 shadow-sm">
        <div class="relative -mx-5 -mt-5 mb-3 h-36 overflow-hidden rounded-t-2xl bg-gray-100">
            <img
                v-if="course.thumbnail_path"
                :src="`/storage/${course.thumbnail_path}`"
                :alt="course.title"
                class="h-full w-full object-cover"
            />
            <div v-else class="from-panel-start to-panel-end flex h-full w-full items-center justify-center bg-gradient-to-b text-white/70">
                <RectangleStackIcon class="h-10 w-10" />
            </div>
            <span
                v-if="course.pricing.is_free"
                class="absolute top-3 right-3 rounded-full bg-green-500 px-3 py-1 text-xs font-bold text-white shadow-sm"
            >
                Free
            </span>
            <span
                v-else-if="course.pricing.discount_percentage"
                class="absolute top-3 right-3 rounded-full bg-red-500 px-3 py-1 text-xs font-bold text-white shadow-sm"
            >
                {{ course.pricing.discount_percentage }}% off
            </span>
        </div>

        <p class="text-ink line-clamp-2 font-bold">{{ course.title }}</p>
        <p v-if="course.subtitle" class="mt-1 line-clamp-2 text-sm text-gray-500">{{ course.subtitle }}</p>
        <p v-if="course.description" class="mt-1 line-clamp-2 text-sm text-gray-500">{{ course.description }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500">
            <span v-if="course.subject" class="bg-accent/10 text-accent rounded-full px-3 py-1 font-medium">{{ course.subject.name }}</span>
            <span v-if="course.grade" class="rounded-full bg-gray-100 px-3 py-1 font-medium">{{ course.grade.name }}</span>
            <span v-if="course.difficulty" class="rounded-full bg-gray-100 px-3 py-1 font-medium capitalize">{{ course.difficulty }}</span>
            <span v-if="course.language" class="rounded-full bg-gray-100 px-3 py-1 font-medium">{{ course.language }}</span>
            <span v-if="course.estimated_duration_minutes" class="rounded-full bg-gray-100 px-3 py-1 font-medium">
                {{ Math.round(course.estimated_duration_minutes / 60) }}h
            </span>
        </div>

        <div class="mt-3 flex items-center gap-2 text-sm text-gray-500">
            <img
                v-if="course.tutor?.profile_photo"
                :src="`/storage/${course.tutor.profile_photo}`"
                :alt="course.tutor.display_name"
                class="h-6 w-6 rounded-full object-cover"
            />
            <span>{{ course.tutor?.display_name }}</span>
        </div>

        <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4">
            <div>
                <p v-if="course.pricing.is_free" class="text-ink font-bold">Free</p>
                <p v-else-if="course.pricing.discounted_price !== null" class="flex items-baseline gap-2">
                    <span class="text-ink font-bold">{{ course.pricing.currency }} {{ course.pricing.discounted_price }}</span>
                    <span class="text-xs text-gray-400 line-through">{{ course.pricing.original_price }}</span>
                </p>
                <p v-else class="text-ink font-bold">{{ course.pricing.currency }} {{ course.pricing.original_price }}</p>
                <p class="text-xs text-gray-500">{{ course.modules_count }} module{{ course.modules_count === 1 ? '' : 's' }}</p>
            </div>
            <router-link
                :to="`/student/marketplace/courses/${course.id}`"
                class="bg-amber rounded-full px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
            >
                {{ course.is_enrolled ? 'Go To Course' : 'View Course' }}
            </router-link>
        </div>
    </div>
</template>
