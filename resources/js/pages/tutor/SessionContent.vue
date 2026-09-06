<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import { Bars3Icon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useSessionContentStore } from '../../stores/sessionContent'
import Modal from '../../components/common/Modal.vue'
import LessonPicker from '../../components/lms/LessonPicker.vue'

const route = useRoute()
const router = useRouter()
const store = useSessionContentStore()
const sessionId = computed(() => Number(route.params.id))

const loading = ref(true)
const actionError = ref('')
const sessionLessons = ref([])
const pickerOpen = ref(false)

onMounted(async () => {
    sessionLessons.value = await store.fetchSessionLessons(sessionId.value)
    loading.value = false
})

async function onLessonSelected(lesson) {
    actionError.value = ''
    try {
        const created = await store.assignLesson(sessionId.value, lesson.id)
        sessionLessons.value.push(created)
        pickerOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    }
}

async function removeLesson(sessionLesson) {
    if (!confirm(`Remove "${sessionLesson.lesson.title}" from this session?`)) {
        return
    }

    actionError.value = ''
    try {
        await store.removeLesson(sessionId.value, sessionLesson.id)
        sessionLessons.value = sessionLessons.value.filter((entry) => entry.id !== sessionLesson.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function onReorder() {
    try {
        await store.reorderLessons(sessionId.value, sessionLessons.value.map((entry) => entry.id))
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not save the new order. Please try again.'
    }
}

function manageContent(sessionLesson) {
    router.push({
        path: `/tutor/session-lessons/${sessionLesson.id}/blocks`,
        query: { sessionId: sessionId.value, lessonTitle: sessionLesson.lesson.title },
    })
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <button type="button" class="text-accent text-sm font-semibold" @click="router.push(`/tutor/sessions/${sessionId}`)">
                    &larr; Back to Session
                </button>
                <h1 class="text-body mt-1 text-2xl font-bold">Session Content</h1>
                <p class="text-muted mt-1">Assign lessons to this session, then choose which of their blocks students can see.</p>
            </div>
            <button
                type="button"
                class="bg-amber shadow-elevated flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white transition hover:brightness-95"
                @click="pickerOpen = true"
            >
                <PlusIcon class="h-4 w-4" />
                Assign Lesson
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="sessionLessons.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-muted">No lessons assigned yet. Assign your first lesson to get started.</p>
        </div>

        <draggable
            v-else
            v-model="sessionLessons"
            item-key="id"
            handle=".drag-handle"
            class="mt-8 space-y-3"
            @end="onReorder"
        >
            <template #item="{ element: sessionLesson }">
                <div class="bg-card shadow-elevated flex items-center gap-4 rounded-2xl p-5">
                    <span class="drag-handle text-muted cursor-grab">
                        <Bars3Icon class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-body font-bold">{{ sessionLesson.lesson.title }}</p>
                        <p class="text-muted mt-1 text-sm">
                            {{ sessionLesson.assigned_blocks_count ?? 0 }}
                            {{ sessionLesson.assigned_blocks_count === 1 ? 'block' : 'blocks' }} assigned
                        </p>
                    </div>

                    <button
                        type="button"
                        class="text-accent border-border shrink-0 rounded-full border px-4 py-2 text-sm font-semibold"
                        @click="manageContent(sessionLesson)"
                    >
                        Manage Content
                    </button>
                    <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="removeLesson(sessionLesson)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </draggable>

        <Modal v-model="pickerOpen" title="Assign Lesson">
            <LessonPicker v-if="pickerOpen" @selected="onLessonSelected" @cancelled="pickerOpen = false" />
        </Modal>
    </div>
</template>
