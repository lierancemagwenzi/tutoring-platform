<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useMarketplaceStore } from '../../stores/marketplace'
import CourseHeader from '../../components/course-catalog/CourseHeader.vue'
import CourseDescription from '../../components/course-catalog/CourseDescription.vue'
import CourseObjectives from '../../components/course-catalog/CourseObjectives.vue'
import CoursePrerequisites from '../../components/course-catalog/CoursePrerequisites.vue'
import CourseAudience from '../../components/course-catalog/CourseAudience.vue'
import CourseCurriculum from '../../components/course-catalog/CourseCurriculum.vue'
import CourseInformation from '../../components/course-catalog/CourseInformation.vue'
import TutorProfileCard from '../../components/course-catalog/TutorProfileCard.vue'
import EnrollCard from '../../components/course-catalog/EnrollCard.vue'
import RelatedCourses from '../../components/course-catalog/RelatedCourses.vue'

const route = useRoute()
const store = useMarketplaceStore()

const loading = ref(true)
const errorMessage = ref('')
const course = ref(null)

onMounted(async () => {
    try {
        course.value = await store.fetchSelfPacedCourse(route.params.id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This course could not be found.'
    } finally {
        loading.value = false
    }
})
</script>

<template>
    <div class="p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="course">
            <router-link to="/student/marketplace" class="text-accent text-sm font-semibold">&larr; Back to Marketplace</router-link>

            <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <CourseHeader :course="course" />
                    <CourseDescription :course="course" />
                    <CourseObjectives :objectives="course.learning_objectives" />
                    <CoursePrerequisites :prerequisites="course.prerequisites" />
                    <CourseAudience :audience="course.target_audience" />
                    <CourseCurriculum :course="course" />
                    <RelatedCourses />
                </div>

                <div class="space-y-6">
                    <EnrollCard :course="course" />
                    <CourseInformation :course="course" />
                    <TutorProfileCard v-if="course.tutor" :tutor="course.tutor" />
                </div>
            </div>
        </template>
    </div>
</template>
