<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSelfPacedPlayerStore } from '../stores/selfPacedPlayer'
import CoursePlayerHeader from '../components/self-paced-player/CoursePlayerHeader.vue'
import CourseSidebar from '../components/self-paced-player/CourseSidebar.vue'
import LessonNavigation from '../components/self-paced-player/LessonNavigation.vue'
import CourseProgressFooter from '../components/self-paced-player/CourseProgressFooter.vue'

const route = useRoute()
const router = useRouter()
const playerStore = useSelfPacedPlayerStore()

const loading = ref(true)
const errorMessage = ref('')
const sidebarOpen = ref(false)

const course = computed(() => playerStore.course)
const onLessonRoute = computed(() => ['student.self-paced.activity', 'student.self-paced.assessment'].includes(route.name))

async function load(courseId) {
    loading.value = true
    errorMessage.value = ''

    try {
        await playerStore.fetchCourse(courseId)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This course is not available.'
    } finally {
        loading.value = false
    }
}

onMounted(() => load(route.params.courseId))

watch(
    () => route.params.courseId,
    (courseId, previous) => {
        if (courseId !== previous) load(courseId)
    },
)

// The one deliberate auto-navigation in the player: the moment the course
// transitions to Completed — from either an activity or an assessment page
// — jump to the certificate/congratulations view. Revisiting an
// already-completed course never force-redirects (previousStatus must be a
// genuine prior non-completed value, not the initial undefined).
watch(
    () => course.value?.enrollment.status,
    (status, previousStatus) => {
        if (status === 'completed' && previousStatus && previousStatus !== 'completed' && route.name !== 'student.self-paced.certificate') {
            router.push({ name: 'student.self-paced.certificate', params: { courseId: route.params.courseId } })
        }
    },
)
</script>

<template>
    <div v-if="loading" class="bg-surface flex min-h-screen items-center justify-center">
        <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
    </div>

    <div v-else-if="errorMessage" class="bg-surface flex min-h-screen flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-sm text-red-600">{{ errorMessage }}</p>
        <router-link to="/student/my-courses" class="text-accent text-sm font-semibold underline">Back to My Courses</router-link>
    </div>

    <div v-else-if="course" class="bg-surface flex h-screen flex-col">
        <CoursePlayerHeader :course="course" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

        <div class="flex flex-1 overflow-hidden">
            <CourseSidebar :course="course" :open="sidebarOpen" @close="sidebarOpen = false" />

            <div class="flex flex-1 flex-col overflow-y-auto">
                <main class="flex-1">
                    <router-view />
                </main>

                <LessonNavigation v-if="onLessonRoute" />
                <CourseProgressFooter :course="course" />
            </div>
        </div>
    </div>
</template>
