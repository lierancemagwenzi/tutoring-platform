<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, RectangleStackIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../../stores/selfPacedCourses'
import Modal from '../../../components/common/Modal.vue'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'

const STATUS_CLASSES = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    private: 'bg-amber-100 text-amber-700',
    archived: 'bg-gray-100 text-gray-500',
}

const router = useRouter()
const store = useSelfPacedCoursesStore()

const loading = ref(true)
const courses = ref([])
const createOpen = ref(false)
const title = ref('')
const creating = ref(false)
const error = ref('')

async function load() {
    loading.value = true
    courses.value = await store.fetchCourses()
    loading.value = false
}

onMounted(load)

async function create() {
    creating.value = true
    error.value = ''

    try {
        const course = await store.createCourse({ title: title.value })
        createOpen.value = false
        title.value = ''
        router.push(`/tutor/self-paced-courses/${course.id}`)
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        creating.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-ink text-2xl font-bold">Self-Paced Courses</h1>
                <p class="mt-1 text-gray-500">Structured, self-paced learning products — independent of your tutoring services.</p>
            </div>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="createOpen = true"
            >
                <PlusIcon class="h-4 w-4" />
                New Course
            </button>
        </div>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="courses.length === 0" class="mt-16 flex flex-col items-center text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <RectangleStackIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-gray-500">No self-paced courses yet. Create your first one to get started.</p>
        </div>

        <div v-else class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <router-link
                v-for="course in courses"
                :key="course.id"
                :to="`/tutor/self-paced-courses/${course.id}`"
                class="flex flex-col rounded-2xl bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="text-ink font-bold">{{ course.title }}</p>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="STATUS_CLASSES[course.status]">
                        {{ course.status }}
                    </span>
                </div>
                <p v-if="course.subtitle" class="mt-1 text-sm text-gray-500">{{ course.subtitle }}</p>
                <div class="mt-3 flex items-center justify-between text-sm text-gray-500">
                    <span>{{ course.modules_count ?? 0 }} module{{ course.modules_count === 1 ? '' : 's' }}</span>
                    <span v-if="course.price !== null">{{ course.currency }} {{ course.price }}</span>
                    <span v-else class="text-gray-400 italic">No price set</span>
                </div>
            </router-link>
        </div>

        <Modal v-model="createOpen" title="New Self-Paced Course">
            <form class="space-y-4" novalidate @submit.prevent="create">
                <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>
                <FloatingLabelInput id="new-course-title" v-model="title" label="Course Title" />
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700" @click="createOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="creating || !title"
                        class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ creating ? 'Creating…' : 'Create Course' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
