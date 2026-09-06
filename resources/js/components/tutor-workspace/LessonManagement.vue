<script setup>
import { AcademicCapIcon, BookOpenIcon, PlusIcon, TagIcon } from '@heroicons/vue/24/outline'

const shortcuts = [
    { icon: BookOpenIcon, label: 'Manage Courses', to: '/tutor/courses' },
    { icon: PlusIcon, label: 'Create Course', to: '/tutor/courses/create' },
    { icon: TagIcon, label: 'Manage Subjects', to: '/tutor/subjects' },
    { icon: AcademicCapIcon, label: 'Manage Sessions', to: '/tutor/sessions' },
]
</script>

<template>
    <div class="rounded-2xl bg-card p-6 shadow-elevated">
        <h2 class="text-body text-lg font-bold">Lesson Management</h2>

        <ul class="mt-4 space-y-2">
            <li v-for="shortcut in shortcuts" :key="shortcut.label">
                <router-link
                    :to="shortcut.to"
                    class="text-body flex items-center justify-between rounded-xl bg-card-alt px-4 py-3 text-sm font-medium transition hover:brightness-95"
                >
                    <span class="flex items-center gap-2">
                        <component :is="shortcut.icon" class="h-4 w-4 text-muted" />
                        {{ shortcut.label }}
                    </span>
                </router-link>
            </li>
        </ul>
    </div>
</template>
