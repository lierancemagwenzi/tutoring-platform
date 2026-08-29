import { ref } from 'vue'
import { useSelfPacedPlayerStore } from '../stores/selfPacedPlayer'

// Shared by LessonActivity.vue and LessonAssessment.vue: both need to show
// a "Chapter Complete!" banner exactly once, the moment a completion action
// flips a module's state from not-completed to completed. Course-level
// completion (the "Congratulations" moment) is handled separately and
// centrally in CoursePlayerLayout.vue, since it can be triggered by either
// page and the celebration destination (the certificate route) is the same
// regardless of which page triggered it.
export function useModuleCompletionBanner() {
    const playerStore = useSelfPacedPlayerStore()
    const justCompletedModuleId = ref(null)

    function moduleState(moduleId) {
        return playerStore.course?.modules.find((module) => module.id === moduleId)?.state
    }

    function snapshot(moduleId) {
        return moduleState(moduleId)
    }

    function checkAfterRefresh(moduleId, previousState) {
        if (previousState !== 'completed' && moduleState(moduleId) === 'completed') {
            justCompletedModuleId.value = moduleId
        }
    }

    function dismiss() {
        justCompletedModuleId.value = null
    }

    return { justCompletedModuleId, snapshot, checkAfterRefresh, dismiss }
}
