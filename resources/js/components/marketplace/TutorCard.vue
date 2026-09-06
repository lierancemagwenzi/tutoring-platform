<script setup>
import { UserCircleIcon } from '@heroicons/vue/24/outline'

defineProps({
    tutor: { type: Object, required: true },
})
</script>

<template>
    <div class="bg-card shadow-elevated flex flex-col rounded-2xl p-5">
        <div class="flex items-center gap-4">
            <img
                v-if="tutor.profile_photo"
                :src="tutor.profile_photo"
                :alt="tutor.display_name"
                class="h-16 w-16 shrink-0 rounded-full object-cover"
            />
            <span v-else class="bg-amber flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-white">
                <UserCircleIcon class="h-10 w-10" />
            </span>
            <div class="min-w-0">
                <p class="text-body truncate font-bold">{{ tutor.display_name }}</p>
                <p class="text-muted text-sm">{{ tutor.years_experience ?? 0 }} yrs experience</p>
            </div>
        </div>

        <p v-if="tutor.bio" class="text-muted mt-3 line-clamp-3 text-sm">{{ tutor.bio }}</p>

        <div v-if="tutor.subjects.length || tutor.grades?.length" class="mt-3 flex flex-wrap gap-2">
            <span
                v-for="subject in tutor.subjects"
                :key="`subject-${subject.id}`"
                class="bg-accent/10 text-accent rounded-full px-3 py-1 text-xs font-medium"
            >
                {{ subject.name }}
            </span>
            <span
                v-for="grade in tutor.grades"
                :key="`grade-${grade.id}`"
                class="bg-card-alt text-body rounded-full px-3 py-1 text-xs font-medium"
            >
                {{ grade.name }}
            </span>
        </div>

        <p v-if="tutor.languages.length" class="text-muted mt-3 text-sm">Speaks {{ tutor.languages.join(', ') }}</p>

        <div class="border-border mt-4 flex items-center justify-between border-t pt-4">
            <div>
                <p class="text-body font-bold">
                    <span v-if="tutor.starting_price">{{ tutor.currency }} {{ tutor.starting_price }}</span>
                    <span v-else class="text-muted">Price on request</span>
                </p>
                <p class="text-muted text-xs">
                    {{ tutor.published_services_count }} service{{ tutor.published_services_count === 1 ? '' : 's' }}
                </p>
            </div>
            <router-link
                :to="`/student/marketplace/tutors/${tutor.id}`"
                class="bg-amber shadow-elevated rounded-full px-4 py-2 text-sm font-bold text-white transition hover:brightness-95"
            >
                View Profile
            </router-link>
        </div>
    </div>
</template>
