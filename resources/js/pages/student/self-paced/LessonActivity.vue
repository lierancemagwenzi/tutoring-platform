<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { CheckCircleIcon, SparklesIcon } from '@heroicons/vue/24/solid'
import { selfPacedActivityRegistry } from '../../../lms/selfPacedActivityRegistry'
import { useSelfPacedPlayerStore } from '../../../stores/selfPacedPlayer'
import { useModuleCompletionBanner } from '../../../composables/useModuleCompletionBanner'
import StatusPill from '../../../components/common/StatusPill.vue'

const route = useRoute()
const playerStore = useSelfPacedPlayerStore()
const { justCompletedModuleId, snapshot, checkAfterRefresh, dismiss } = useModuleCompletionBanner()

const loading = ref(true)
const errorMessage = ref('')
const activity = ref(null)
const completing = ref(false)

const registryEntry = computed(() => selfPacedActivityRegistry[activity.value?.type])

async function load() {
    loading.value = true
    errorMessage.value = ''
    activity.value = null
    dismiss()

    try {
        activity.value = await playerStore.fetchActivity(route.params.courseId, route.params.activityId)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This content is not available.'
    } finally {
        loading.value = false
    }
}

async function markComplete() {
    if (completing.value || activity.value?.completed) return

    completing.value = true
    errorMessage.value = ''

    try {
        const before = snapshot(activity.value.self_paced_module_id)
        await playerStore.completeActivity(route.params.courseId, activity.value.id)
        activity.value.completed = true
        checkAfterRefresh(activity.value.self_paced_module_id, before)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        completing.value = false
    }
}

onMounted(load)
watch(() => route.params.activityId, load)
</script>

<template>
    <div class="mx-auto max-w-3xl p-4 sm:p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="activity">
            <div
                v-if="justCompletedModuleId"
                class="mb-4 flex items-center gap-2 rounded-xl bg-green-50 px-4 py-3 text-sm font-semibold text-green-700"
            >
                <SparklesIcon class="h-5 w-5" />
                Chapter Complete! The next chapter is now unlocked.
            </div>

            <div class="flex items-center gap-3">
                <span class="bg-accent/10 text-accent flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                    <component :is="registryEntry?.icon" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <h1 class="text-ink text-2xl font-bold">{{ activity.title }}</h1>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">
                        {{ registryEntry?.label ?? activity.type }}
                    </span>
                </div>
                <StatusPill :status="activity.completed ? 'completed' : 'incomplete'" />
            </div>

            <p v-if="activity.description" class="mt-3 text-sm text-gray-600">{{ activity.description }}</p>

            <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
                <component
                    :is="registryEntry.component"
                    v-if="registryEntry"
                    :activity="activity"
                    @auto-complete="markComplete"
                />
                <p v-else class="text-sm text-gray-500">This content type isn't supported yet.</p>
            </div>

            <div v-if="!registryEntry?.autoComplete" class="mt-6 flex justify-end">
                <button
                    v-if="!activity.completed"
                    type="button"
                    class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="completing"
                    @click="markComplete"
                >
                    {{ completing ? 'Marking Complete…' : 'Mark Complete' }}
                </button>
                <p v-else class="flex items-center gap-1.5 text-sm font-semibold text-green-700">
                    <CheckCircleIcon class="h-5 w-5" />
                    Completed
                </p>
            </div>
        </template>
    </div>
</template>
