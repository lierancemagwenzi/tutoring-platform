<script setup>
import { UserCircleIcon } from '@heroicons/vue/24/outline'

defineProps({
    tutor: { type: Object, required: true },
})
</script>

<template>
    <div class="bg-card shadow-elevated rounded-2xl p-6">
        <h2 class="text-body mb-3 font-bold">Your Tutor</h2>
        <div class="flex items-center gap-3">
            <img
                v-if="tutor.profile_photo"
                :src="`/storage/${tutor.profile_photo}`"
                :alt="tutor.display_name"
                class="h-14 w-14 rounded-full object-cover"
            />
            <span v-else class="bg-amber flex h-14 w-14 items-center justify-center rounded-full text-white">
                <UserCircleIcon class="h-9 w-9" />
            </span>
            <div>
                <p class="text-body font-semibold">{{ tutor.display_name }}</p>
                <p v-if="tutor.years_experience" class="text-muted text-sm">{{ tutor.years_experience }} yrs experience</p>
            </div>
        </div>

        <p v-if="tutor.bio" class="text-muted mt-3 line-clamp-4 text-sm">{{ tutor.bio }}</p>

        <div v-if="tutor.subjects?.length" class="mt-3 flex flex-wrap gap-2">
            <span v-for="subject in tutor.subjects" :key="subject.id" class="bg-accent/10 text-accent rounded-full px-3 py-1 text-xs font-medium">
                {{ subject.name }}
            </span>
        </div>

        <div v-if="tutor.qualifications?.length" class="border-border mt-4 border-t pt-3">
            <p class="text-muted text-xs font-semibold tracking-wide uppercase">Qualifications</p>
            <ul class="text-muted mt-2 space-y-1.5 text-sm">
                <li v-for="qualification in tutor.qualifications" :key="qualification.id">
                    {{ qualification.title }}
                    <span v-if="qualification.institution" class="text-muted"> &middot; {{ qualification.institution }}</span>
                </li>
            </ul>
        </div>

        <p v-if="tutor.other_published_courses_count" class="text-muted mt-3 text-sm">
            {{ tutor.other_published_courses_count }} other published course{{ tutor.other_published_courses_count === 1 ? '' : 's' }}
        </p>

        <router-link :to="`/student/marketplace/tutors/${tutor.id}`" class="text-accent mt-3 block text-sm font-semibold">
            View Tutor Profile
        </router-link>
    </div>
</template>
