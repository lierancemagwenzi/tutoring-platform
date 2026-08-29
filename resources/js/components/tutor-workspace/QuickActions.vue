<script setup>
import { BookOpenIcon, CalendarIcon, ClipboardDocumentCheckIcon, PlusCircleIcon, RectangleStackIcon, UserGroupIcon } from '@heroicons/vue/24/outline'

const actions = [
    { icon: BookOpenIcon, title: 'Create Lesson', to: '/tutor/courses' },
    { icon: UserGroupIcon, title: 'View Students', to: '/tutor/sessions' },
    { icon: CalendarIcon, title: 'Manage Availability', to: '/tutor/calendar' },
    { icon: ClipboardDocumentCheckIcon, title: 'Review Student Work', to: '/tutor/sessions' },
    { icon: PlusCircleIcon, title: 'Create Service', to: '/tutor/services/create' },
    { icon: RectangleStackIcon, title: 'Manage Services', to: '/tutor/services' },
]
</script>

<template>
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <h2 class="text-ink text-lg font-bold">Quick Actions</h2>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <router-link
                v-for="action in actions"
                :key="action.title"
                :to="action.to"
                class="flex flex-col items-center gap-2 rounded-xl bg-gray-50 px-3 py-4 text-center transition hover:bg-gray-100"
            >
                <span class="bg-amber flex h-9 w-9 items-center justify-center rounded-lg text-white">
                    <component :is="action.icon" class="h-5 w-5" />
                </span>
                <p class="text-ink text-xs font-semibold">{{ action.title }}</p>
            </router-link>
        </div>
    </div>
</template>
