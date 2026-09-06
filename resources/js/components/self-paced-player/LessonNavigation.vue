<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSelfPacedPlayerStore } from '../../stores/selfPacedPlayer'

const playerStore = useSelfPacedPlayerStore()
const route = useRoute()
const router = useRouter()

const currentIndex = computed(() => {
    const items = playerStore.orderedItems

    if (route.name === 'student.self-paced.activity') {
        return items.findIndex((item) => item.kind === 'activity' && item.id === Number(route.params.activityId))
    }
    if (route.name === 'student.self-paced.assessment') {
        return items.findIndex((item) => item.kind === 'assessment' && item.id === Number(route.params.assessmentId))
    }

    return -1
})

const previousItem = computed(() => (currentIndex.value > 0 ? playerStore.orderedItems[currentIndex.value - 1] : null))
const nextItem = computed(() => {
    const items = playerStore.orderedItems

    return currentIndex.value >= 0 && currentIndex.value < items.length - 1 ? items[currentIndex.value + 1] : null
})

function isNavigable(item) {
    return Boolean(item) && item.moduleState !== 'locked'
}

function go(item) {
    if (!isNavigable(item)) return

    router.push(
        item.kind === 'activity'
            ? { name: 'student.self-paced.activity', params: { courseId: route.params.courseId, activityId: item.id } }
            : { name: 'student.self-paced.assessment', params: { courseId: route.params.courseId, assessmentId: item.id } },
    )
}
</script>

<template>
    <div class="bg-card border-border flex items-center justify-between gap-3 border-t px-4 py-3 sm:px-6">
        <button
            type="button"
            class="border-border text-body rounded-full border px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!previousItem"
            @click="go(previousItem)"
        >
            &larr; Previous
        </button>

        <button
            type="button"
            class="bg-amber shadow-elevated rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!isNavigable(nextItem)"
            @click="go(nextItem)"
        >
            Next &rarr;
        </button>
    </div>
</template>
