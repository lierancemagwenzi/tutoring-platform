<script setup>
import { BookOpenIcon, CalendarIcon, PlayCircleIcon, UserGroupIcon } from '@heroicons/vue/24/outline'
import { computed } from 'vue'

const props = defineProps({
    continueLearning: { type: Array, default: () => [] },
})

const actions = computed(() => [
    { icon: UserGroupIcon, title: 'Find a tutor', to: '/student/marketplace' },
    { icon: CalendarIcon, title: 'My bookings', to: '/student/bookings' },
    props.continueLearning[0]?.url
        ? { icon: PlayCircleIcon, title: 'Continue learning', to: props.continueLearning[0].url }
        : { icon: BookOpenIcon, title: 'Browse subjects', to: '/student/marketplace' },
])
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <router-link
            v-for="action in actions"
            :key="action.title"
            :to="action.to"
            class="bg-card hover:shadow-popover flex items-center gap-3 rounded-2xl p-5 shadow-elevated transition"
        >
            <span class="bg-amber flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white">
                <component :is="action.icon" class="h-5 w-5" />
            </span>
            <p class="text-body font-semibold">{{ action.title }}</p>
        </router-link>
    </div>
</template>
