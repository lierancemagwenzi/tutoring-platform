<script setup>
import { onMounted, ref } from 'vue'
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import SelectInput from '../forms/SelectInput.vue'

const VISIBILITY_OPTIONS = [
    { value: 'public', label: 'Public' },
    { value: 'private', label: 'Private' },
    { value: 'unlisted', label: 'Unlisted' },
]

const props = defineProps({
    course: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const store = useSelfPacedCoursesStore()
const errors = ref([])
const loadingErrors = ref(true)
const busy = ref(false)
const actionError = ref('')
const visibility = ref(props.course.visibility)

async function loadErrors() {
    loadingErrors.value = true
    errors.value = await store.fetchPublishingErrors(props.course.id)
    loadingErrors.value = false
}

onMounted(loadErrors)

async function publish() {
    busy.value = true
    actionError.value = ''

    try {
        const updated = await store.publishCourse(props.course.id)
        emit('updated', updated)
        await loadErrors()
    } catch (err) {
        const validationErrors = err.response?.data?.errors?.course
        actionError.value = validationErrors ? validationErrors.join(' ') : (err.response?.data?.message ?? 'Could not publish.')
    } finally {
        busy.value = false
    }
}

async function unpublish() {
    busy.value = true
    const updated = await store.unpublishCourse(props.course.id)
    emit('updated', updated)
    busy.value = false
}

async function archive() {
    if (!confirm('Archive this course? It will no longer be visible in the marketplace.')) return

    busy.value = true
    const updated = await store.archiveCourse(props.course.id)
    emit('updated', updated)
    busy.value = false
}

async function updateVisibility() {
    const updated = await store.updateCourse(props.course.id, { visibility: visibility.value })
    emit('updated', updated)
}
</script>

<template>
    <div class="max-w-2xl space-y-6">
        <div class="rounded-2xl bg-card p-6 shadow-elevated">
            <h2 class="text-body text-lg font-bold">Publishing Checklist</h2>

            <div v-if="loadingErrors" class="flex justify-center py-6">
                <div class="border-amber h-6 w-6 animate-spin rounded-full border-4 border-t-transparent" />
            </div>

            <div v-else-if="errors.length === 0" class="mt-4 flex items-center gap-2 text-sm text-green-700">
                <CheckCircleIcon class="h-5 w-5" /> This course is ready to publish.
            </div>

            <ul v-else class="mt-4 space-y-2">
                <li v-for="(message, index) in errors" :key="index" class="flex items-start gap-2 text-sm text-amber-700">
                    <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                    {{ message }}
                </li>
            </ul>

            <p v-if="actionError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

            <div class="mt-6 flex flex-wrap gap-3">
                <button
                    v-if="course.status !== 'published'"
                    type="button"
                    :disabled="busy || errors.length > 0"
                    class="bg-amber rounded-full px-6 py-2.5 text-sm font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="publish"
                >
                    Publish Course
                </button>
                <button
                    v-else
                    type="button"
                    :disabled="busy"
                    class="rounded-full border border-border px-6 py-2.5 text-sm font-semibold text-body disabled:opacity-40"
                    @click="unpublish"
                >
                    Move Back to Draft
                </button>
                <button
                    v-if="course.status !== 'archived'"
                    type="button"
                    :disabled="busy"
                    class="rounded-full border border-red-300 px-6 py-2.5 text-sm font-semibold text-red-600 disabled:opacity-40"
                    @click="archive"
                >
                    Archive Course
                </button>
            </div>
        </div>

        <div class="rounded-2xl bg-card p-6 shadow-elevated">
            <h2 class="text-body text-lg font-bold">Visibility</h2>
            <p class="mt-1 text-sm text-muted">Controls who can find this course once it's published.</p>
            <div class="mt-4 max-w-xs">
                <SelectInput id="course-visibility" v-model="visibility" label="Visibility" :options="VISIBILITY_OPTIONS" @update:model-value="updateVisibility" />
            </div>
        </div>
    </div>
</template>
