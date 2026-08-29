<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useSelfPacedCoursesStore } from '../../../stores/selfPacedCourses'
import CourseGeneralForm from '../../../components/self-paced/CourseGeneralForm.vue'
import CourseLearningForm from '../../../components/self-paced/CourseLearningForm.vue'
import ModuleManager from '../../../components/self-paced/ModuleManager.vue'
import CoursePricingForm from '../../../components/self-paced/CoursePricingForm.vue'
import DiscountCodeManager from '../../../components/self-paced/DiscountCodeManager.vue'
import CoursePublishingPanel from '../../../components/self-paced/CoursePublishingPanel.vue'

const STATUS_CLASSES = {
    draft: 'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    private: 'bg-amber-100 text-amber-700',
    archived: 'bg-gray-100 text-gray-500',
}

const TABS = [
    { key: 'general', label: 'General' },
    { key: 'learning', label: 'Learning' },
    { key: 'modules', label: 'Modules' },
    { key: 'pricing', label: 'Pricing & Discounts' },
    { key: 'publishing', label: 'Publishing' },
]

const route = useRoute()
const store = useSelfPacedCoursesStore()
const courseId = computed(() => Number(route.params.id))

const loading = ref(true)
const course = ref(null)
const activeTab = ref('general')

async function load() {
    loading.value = true
    course.value = await store.fetchCourse(courseId.value)
    loading.value = false
}

onMounted(load)

function onCourseUpdated(updated) {
    course.value = { ...course.value, ...updated }
}
</script>

<template>
    <div class="p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else-if="course">
            <router-link to="/tutor/self-paced-courses" class="text-accent text-sm font-semibold">&larr; Back to My Courses</router-link>

            <div class="mt-1 flex items-center gap-3">
                <h1 class="text-ink text-2xl font-bold">{{ course.title }}</h1>
                <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="STATUS_CLASSES[course.status]">
                    {{ course.status }}
                </span>
            </div>

            <div class="mt-6 flex gap-1 overflow-x-auto border-b border-gray-200">
                <button
                    v-for="tab in TABS"
                    :key="tab.key"
                    type="button"
                    class="shrink-0 border-b-2 px-4 py-2 text-sm font-semibold whitespace-nowrap transition"
                    :class="activeTab === tab.key ? 'border-accent text-accent' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="mt-6">
                <CourseGeneralForm v-if="activeTab === 'general'" :course="course" @updated="onCourseUpdated" />
                <CourseLearningForm v-else-if="activeTab === 'learning'" :course="course" @updated="onCourseUpdated" />
                <ModuleManager v-else-if="activeTab === 'modules'" :course-id="course.id" />
                <div v-else-if="activeTab === 'pricing'" class="space-y-6">
                    <CoursePricingForm :course="course" @updated="onCourseUpdated" />
                    <DiscountCodeManager :course-id="course.id" />
                </div>
                <CoursePublishingPanel v-else-if="activeTab === 'publishing'" :course="course" @updated="onCourseUpdated" />
            </div>
        </template>
    </div>
</template>
