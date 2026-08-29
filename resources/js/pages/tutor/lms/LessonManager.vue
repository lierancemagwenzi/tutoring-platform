<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import { Bars3Icon, PencilSquareIcon, PlusIcon, Squares2X2Icon, TrashIcon } from '@heroicons/vue/24/outline'
import { useCoursesStore } from '../../../stores/courses'
import Modal from '../../../components/common/Modal.vue'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../../components/forms/TextareaInput.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const route = useRoute()
const store = useCoursesStore()
const chapterId = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const actionError = ref('')
const lessons = ref([])

const modalOpen = ref(false)
const editingLesson = ref(null)
const form = reactive({ title: '', description: '', estimatedDurationMinutes: '', status: 'draft' })

onMounted(async () => {
    lessons.value = await store.fetchLessons(chapterId.value)
    loading.value = false
})

function openCreate() {
    editingLesson.value = null
    form.title = ''
    form.description = ''
    form.estimatedDurationMinutes = ''
    form.status = 'draft'
    modalOpen.value = true
}

function openEdit(lesson) {
    editingLesson.value = lesson
    form.title = lesson.title
    form.description = lesson.description ?? ''
    form.estimatedDurationMinutes = lesson.estimated_duration_minutes ? String(lesson.estimated_duration_minutes) : ''
    form.status = lesson.status
    modalOpen.value = true
}

function buildPayload() {
    return {
        title: form.title,
        description: form.description,
        estimated_duration_minutes: form.estimatedDurationMinutes ? Number(form.estimatedDurationMinutes) : null,
        ...(editingLesson.value ? { status: form.status } : {}),
    }
}

async function save() {
    saving.value = true
    actionError.value = ''

    try {
        if (editingLesson.value) {
            const updated = await store.updateLesson(editingLesson.value.id, buildPayload())
            const index = lessons.value.findIndex((lesson) => lesson.id === updated.id)
            if (index !== -1) {
                lessons.value[index] = updated
            }
        } else {
            const created = await store.createLesson(chapterId.value, buildPayload())
            lessons.value.push(created)
        }
        modalOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}

async function remove(lesson) {
    if (!confirm(`Delete "${lesson.title}"? Its content blocks will be deleted too.`)) {
        return
    }

    actionError.value = ''
    try {
        await store.deleteLesson(lesson.id)
        lessons.value = lessons.value.filter((entry) => entry.id !== lesson.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function onReorder() {
    try {
        await store.reorderLessons(chapterId.value, lessons.value.map((lesson) => lesson.id))
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not save the new order. Please try again.'
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <button type="button" class="text-accent text-sm font-semibold" @click="$router.back()">&larr; Back to Chapters</button>
                <h1 class="text-ink mt-1 text-2xl font-bold">Lessons</h1>
                <p class="mt-1 text-gray-500">Organize this chapter's lessons. Drag to reorder.</p>
            </div>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="openCreate"
            >
                <PlusIcon class="h-4 w-4" />
                Add Lesson
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="lessons.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-gray-500">No lessons yet. Add your first lesson to get started.</p>
        </div>

        <draggable
            v-else
            v-model="lessons"
            item-key="id"
            handle=".drag-handle"
            class="mt-8 space-y-3"
            @end="onReorder"
        >
            <template #item="{ element: lesson }">
                <div class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm">
                    <span class="drag-handle cursor-grab text-gray-400">
                        <Bars3Icon class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-ink font-bold">{{ lesson.title }}</p>
                        <p class="mt-0.5 text-sm text-gray-500">
                            <span v-if="lesson.estimated_duration_minutes">{{ lesson.estimated_duration_minutes }} min</span>
                        </p>
                    </div>

                    <span
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize"
                        :class="lesson.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ lesson.status }}
                    </span>

                    <router-link
                        :to="`/tutor/lessons/${lesson.id}/builder`"
                        class="text-accent flex shrink-0 items-center gap-1 text-sm font-semibold"
                    >
                        <Squares2X2Icon class="h-4 w-4" />
                        Lesson Builder
                    </router-link>
                    <button type="button" class="shrink-0 text-gray-500 hover:text-gray-700" @click="openEdit(lesson)">
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="remove(lesson)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </draggable>

        <Modal v-model="modalOpen" :title="editingLesson ? 'Edit Lesson' : 'Add Lesson'">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <FloatingLabelInput id="lesson-title" v-model="form.title" label="Lesson Title" />
                <TextareaInput id="lesson-description" v-model="form.description" label="Description" />
                <FloatingLabelInput
                    id="lesson-duration"
                    v-model="form.estimatedDurationMinutes"
                    type="number"
                    label="Estimated Duration (minutes)"
                />
                <SelectInput v-if="editingLesson" id="lesson-status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700" @click="modalOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
