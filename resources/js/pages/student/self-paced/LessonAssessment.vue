<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { SparklesIcon } from '@heroicons/vue/24/solid'
import AssessmentAttemptPanel from '../../../components/self-paced-player/AssessmentAttemptPanel.vue'
import { useSelfPacedPlayerStore } from '../../../stores/selfPacedPlayer'
import { useModuleCompletionBanner } from '../../../composables/useModuleCompletionBanner'

const route = useRoute()
const playerStore = useSelfPacedPlayerStore()
const { justCompletedModuleId, snapshot, checkAfterRefresh, dismiss } = useModuleCompletionBanner()

const loading = ref(true)
const errorMessage = ref('')
const assessment = ref(null)
const moduleSnapshot = ref(null)

async function load() {
    loading.value = true
    errorMessage.value = ''
    assessment.value = null
    dismiss()

    try {
        assessment.value = await playerStore.fetchAssessment(route.params.courseId, route.params.assessmentId)
        moduleSnapshot.value = snapshot(assessment.value.self_paced_module_id)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This assessment is not available.'
    } finally {
        loading.value = false
    }
}

function onCompleted() {
    // AssessmentAttemptPanel already refetched the course before emitting
    // this, so playerStore.course reflects any newly-earned completion.
    checkAfterRefresh(assessment.value.self_paced_module_id, moduleSnapshot.value)
}

onMounted(load)
watch(() => route.params.assessmentId, load)
</script>

<template>
    <div class="mx-auto max-w-3xl p-4 sm:p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="assessment">
            <div
                v-if="justCompletedModuleId"
                class="mb-4 flex items-center gap-2 rounded-xl bg-green-50 px-4 py-3 text-sm font-semibold text-green-700"
            >
                <SparklesIcon class="h-5 w-5" />
                Chapter Complete! The next chapter is now unlocked.
            </div>

            <h1 class="text-ink text-2xl font-bold">{{ assessment.title }}</h1>
            <p v-if="assessment.description" class="mt-2 text-sm text-gray-600">{{ assessment.description }}</p>
            <p v-if="assessment.passing_score !== null" class="mt-1 text-xs text-gray-400">Passing score: {{ assessment.passing_score }}%</p>

            <div class="mt-6">
                <AssessmentAttemptPanel :course-id="route.params.courseId" :assessment="assessment" @completed="onCompleted" />
            </div>
        </template>
    </div>
</template>
