<script setup>
import { onMounted, ref } from 'vue'
import { AcademicCapIcon, ArchiveBoxIcon, PencilSquareIcon, PlusIcon, Squares2X2Icon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCoursesStore } from '../../../stores/courses'

const store = useCoursesStore()

const loading = ref(true)
const actioningId = ref(null)
const actionError = ref('')

const STATUS_LABELS = { draft: 'Draft', published: 'Published', archived: 'Archived' }
const STATUS_CLASSES = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    archived: 'bg-amber-100 text-amber-700',
}

onMounted(async () => {
    await store.fetchCourses()
    loading.value = false
})

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-card-alt text-muted'
}

async function archive(course) {
    actioningId.value = course.id
    actionError.value = ''
    try {
        await store.archiveCourse(course.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}

async function remove(course) {
    if (!confirm(`Delete "${course.title}"? This cannot be undone.`)) {
        return
    }

    actioningId.value = course.id
    actionError.value = ''
    try {
        await store.deleteCourse(course.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        actioningId.value = null
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <h1 class="text-body text-2xl font-bold">My Courses</h1>
            <router-link
                to="/tutor/courses/create"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
            >
                <PlusIcon class="h-4 w-4" />
                Add Course
            </router-link>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.courses.length === 0" class="mt-16 flex flex-col items-center text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-card-alt text-muted">
                <AcademicCapIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-muted">You haven't created any courses yet.</p>
            <router-link
                to="/tutor/courses/create"
                class="bg-amber mt-6 rounded-full px-6 py-3 font-semibold text-white shadow-elevated transition hover:brightness-95"
            >
                Add Course
            </router-link>
        </div>

        <div v-else class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="course in store.courses" :key="course.id" class="flex flex-col overflow-hidden rounded-2xl bg-card shadow-elevated">
                <div class="h-32 w-full bg-card-alt">
                    <img
                        v-if="course.thumbnail_url"
                        :src="course.thumbnail_url"
                        :alt="course.title"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center text-muted">
                        <AcademicCapIcon class="h-10 w-10" />
                    </div>
                </div>

                <div class="flex flex-1 flex-col p-5">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm text-muted">{{ course.subject.name }} &middot; {{ course.grade.name }}</p>
                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="statusClasses(course.status)">
                            {{ statusLabel(course.status) }}
                        </span>
                    </div>

                    <p class="text-body mt-1 font-bold">{{ course.title }}</p>
                    <p class="mt-2 line-clamp-2 text-sm text-muted">{{ course.description }}</p>

                    <dl class="mt-3 space-y-1 text-sm text-muted">
                        <div class="flex justify-between">
                            <dt>Curriculum</dt>
                            <dd>{{ course.curriculum.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Difficulty</dt>
                            <dd class="capitalize">{{ course.difficulty }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Duration</dt>
                            <dd>{{ course.estimated_duration_minutes }} min</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-border pt-4">
                        <router-link
                            :to="`/tutor/courses/${course.id}/chapters`"
                            class="text-accent flex items-center gap-1 text-sm font-semibold"
                        >
                            <Squares2X2Icon class="h-4 w-4" />
                            Manage Chapters
                        </router-link>
                        <router-link
                            :to="`/tutor/courses/${course.id}/edit`"
                            class="text-accent flex items-center gap-1 text-sm font-semibold"
                        >
                            <PencilSquareIcon class="h-4 w-4" />
                            Edit
                        </router-link>
                        <button
                            v-if="course.status !== 'archived'"
                            type="button"
                            :disabled="actioningId === course.id"
                            class="flex items-center gap-1 text-sm font-semibold text-amber-600 disabled:opacity-40"
                            @click="archive(course)"
                        >
                            <ArchiveBoxIcon class="h-4 w-4" />
                            Archive
                        </button>
                        <button
                            v-if="course.status === 'draft'"
                            type="button"
                            :disabled="actioningId === course.id"
                            class="flex items-center gap-1 text-sm font-semibold text-red-600 disabled:opacity-40"
                            @click="remove(course)"
                        >
                            <TrashIcon class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
