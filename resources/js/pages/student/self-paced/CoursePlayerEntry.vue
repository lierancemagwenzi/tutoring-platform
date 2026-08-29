<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSelfPacedPlayerStore } from '../../../stores/selfPacedPlayer'

// The bare `/learn` path resolves here. By the time this mounts, the parent
// CoursePlayerLayout has already fetched the course (parents mount before
// their <router-view> children), so playerStore.course is ready to read.
const route = useRoute()
const router = useRouter()
const playerStore = useSelfPacedPlayerStore()

function resolveEntryRoute(course) {
    if (course.enrollment.status === 'completed') {
        return { name: 'student.self-paced.certificate', params: { courseId: course.id } }
    }

    const currentModule = course.modules.find((module) => module.state === 'current') ?? course.modules[0]

    if (!currentModule) return null

    const items = [
        ...currentModule.activities.map((activity) => ({ ...activity, kind: 'activity' })),
        ...currentModule.assessments.map((assessment) => ({ ...assessment, kind: 'assessment' })),
    ].sort((a, b) => a.position - b.position)

    const nextItem = items.find((item) => (item.kind === 'activity' ? !item.completed : !item.passed)) ?? items[0]

    if (!nextItem) return null

    return nextItem.kind === 'activity'
        ? { name: 'student.self-paced.activity', params: { courseId: course.id, activityId: nextItem.id } }
        : { name: 'student.self-paced.assessment', params: { courseId: course.id, assessmentId: nextItem.id } }
}

onMounted(() => {
    const target = playerStore.course ? resolveEntryRoute(playerStore.course) : null

    if (target) {
        router.replace(target)
    }
})
</script>

<template>
    <div class="flex justify-center py-24">
        <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
    </div>
</template>
