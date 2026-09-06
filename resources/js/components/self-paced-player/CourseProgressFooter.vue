<script setup>
import { computed } from 'vue'
import ProgressBar from '../common/ProgressBar.vue'

const props = defineProps({
    course: { type: Object, required: true },
})

const currentModule = computed(() => props.course.modules.find((module) => module.id === props.course.progress.current_chapter_id))

const currentModuleTotal = computed(() => {
    if (!currentModule.value) return 0
    return currentModule.value.activities.length + currentModule.value.assessments.length
})

const currentModuleDone = computed(() => {
    if (!currentModule.value) return 0
    const activitiesDone = currentModule.value.activities.filter((activity) => activity.completed).length
    const assessmentsDone = currentModule.value.assessments.filter((assessment) => assessment.passed).length
    return activitiesDone + assessmentsDone
})
</script>

<template>
    <div class="bg-card border-border border-t px-4 py-3 sm:px-6">
        <div class="text-muted flex flex-wrap items-center justify-between gap-2 text-xs">
            <span>Overall Progress: {{ course.progress.overall_percentage }}%</span>
            <span v-if="currentModule">{{ currentModule.title }}: {{ currentModuleDone }} / {{ currentModuleTotal }}</span>
            <span>{{ course.progress.remaining_chapters }} chapter{{ course.progress.remaining_chapters === 1 ? '' : 's' }} remaining</span>
        </div>
        <div class="mt-2">
            <ProgressBar :percentage="course.progress.overall_percentage" size="sm" />
        </div>
    </div>
</template>
