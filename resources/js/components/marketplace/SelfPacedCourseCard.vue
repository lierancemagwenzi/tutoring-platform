<script setup>
import { RectangleStackIcon } from '@heroicons/vue/24/outline'

defineProps({
    course: { type: Object, required: true },
})
</script>

<template>
    <div class="bg-card shadow-elevated flex flex-col rounded-2xl p-5">
        <div class="bg-card-alt relative -mx-5 -mt-5 mb-3 h-36 overflow-hidden rounded-t-2xl">
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

        <p class="text-body line-clamp-2 font-bold">{{ course.title }}</p>
        <p v-if="course.subtitle" class="text-muted mt-1 line-clamp-2 text-sm">{{ course.subtitle }}</p>
        <p v-if="course.description" class="text-muted mt-1 line-clamp-2 text-sm">{{ course.description }}</p>

        <div class="text-muted mt-3 flex flex-wrap items-center gap-2 text-xs">
            <span v-if="course.subject" class="bg-accent/10 text-accent rounded-full px-3 py-1 font-medium">{{ course.subject.name }}</span>
            <span v-if="course.grade" class="bg-card-alt rounded-full px-3 py-1 font-medium">{{ course.grade.name }}</span>
            <span v-if="course.difficulty" class="bg-card-alt rounded-full px-3 py-1 font-medium capitalize">{{ course.difficulty }}</span>
            <span v-if="course.language" class="bg-card-alt rounded-full px-3 py-1 font-medium">{{ course.language }}</span>
            <span v-if="course.estimated_duration_minutes" class="bg-card-alt rounded-full px-3 py-1 font-medium">
                {{ Math.round(course.estimated_duration_minutes / 60) }}h
            </span>
        </div>

        <div class="text-muted mt-3 flex items-center gap-2 text-sm">
            <img
                v-if="course.tutor?.profile_photo"
                :src="`/storage/${course.tutor.profile_photo}`"
                :alt="course.tutor.display_name"
                class="h-6 w-6 rounded-full object-cover"
            />
            <span>{{ course.tutor?.display_name }}</span>
        </div>

        <div class="border-border mt-4 flex items-center justify-between border-t pt-4">
            <div>
                <p v-if="course.pricing.is_free" class="text-body font-bold">Free</p>
                <p v-else-if="course.pricing.discounted_price !== null" class="flex items-baseline gap-2">
                    <span class="text-body font-bold">{{ course.pricing.currency }} {{ course.pricing.discounted_price }}</span>
                    <span class="text-muted text-xs line-through">{{ course.pricing.original_price }}</span>
                </p>
                <p v-else class="text-body font-bold">{{ course.pricing.currency }} {{ course.pricing.original_price }}</p>
                <p class="text-muted text-xs">{{ course.modules_count }} module{{ course.modules_count === 1 ? '' : 's' }}</p>
            </div>
            <router-link
                :to="`/student/marketplace/courses/${course.id}`"
                class="bg-amber shadow-elevated rounded-full px-4 py-2 text-sm font-bold text-white transition hover:brightness-95"
            >
                {{ course.is_enrolled ? 'Go To Course' : 'View Course' }}
            </router-link>
        </div>
    </div>
</template>
