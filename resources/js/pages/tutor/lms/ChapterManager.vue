<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import { Bars3Icon, BookOpenIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
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
const courseId = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const actionError = ref('')
const course = ref(null)
const chapters = ref([])

const modalOpen = ref(false)
const editingChapter = ref(null)
const form = reactive({ title: '', description: '', status: 'draft' })

onMounted(async () => {
    course.value = await store.fetchCourse(courseId.value)
    chapters.value = await store.fetchChapters(courseId.value)
    loading.value = false
})

function openCreate() {
    editingChapter.value = null
    form.title = ''
    form.description = ''
    form.status = 'draft'
    modalOpen.value = true
}

function openEdit(chapter) {
    editingChapter.value = chapter
    form.title = chapter.title
    form.description = chapter.description ?? ''
    form.status = chapter.status
    modalOpen.value = true
}

async function save() {
    saving.value = true
    actionError.value = ''

    try {
        if (editingChapter.value) {
            const updated = await store.updateChapter(editingChapter.value.id, { ...form })
            const index = chapters.value.findIndex((chapter) => chapter.id === updated.id)
            if (index !== -1) {
                chapters.value[index] = updated
            }
        } else {
            const created = await store.createChapter(courseId.value, { title: form.title, description: form.description })
            chapters.value.push(created)
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

async function remove(chapter) {
    if (!confirm(`Delete "${chapter.title}"? Its lessons will be deleted too.`)) {
        return
    }

    actionError.value = ''
    try {
        await store.deleteChapter(chapter.id)
        chapters.value = chapters.value.filter((entry) => entry.id !== chapter.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function onReorder() {
    try {
        await store.reorderChapters(courseId.value, chapters.value.map((chapter) => chapter.id))
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not save the new order. Please try again.'
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <router-link to="/tutor/courses" class="text-accent text-sm font-semibold">&larr; Back to My Courses</router-link>
                <h1 class="text-ink mt-1 text-2xl font-bold">{{ course?.title ?? 'Chapters' }}</h1>
                <p class="mt-1 text-gray-500">Organize your course into chapters. Drag to reorder.</p>
            </div>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="openCreate"
            >
                <PlusIcon class="h-4 w-4" />
                Add Chapter
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="chapters.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-gray-500">No chapters yet. Add your first chapter to get started.</p>
        </div>

        <draggable
            v-else
            v-model="chapters"
            item-key="id"
            handle=".drag-handle"
            class="mt-8 space-y-3"
            @end="onReorder"
        >
            <template #item="{ element: chapter }">
                <div class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm">
                    <span class="drag-handle cursor-grab text-gray-400">
                        <Bars3Icon class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-ink font-bold">{{ chapter.title }}</p>
                        <p v-if="chapter.description" class="mt-0.5 truncate text-sm text-gray-500">{{ chapter.description }}</p>
                    </div>

                    <span
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize"
                        :class="chapter.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ chapter.status }}
                    </span>

                    <router-link
                        :to="`/tutor/chapters/${chapter.id}/lessons`"
                        class="text-accent flex shrink-0 items-center gap-1 text-sm font-semibold"
                    >
                        <BookOpenIcon class="h-4 w-4" />
                        Lessons
                    </router-link>
                    <button type="button" class="shrink-0 text-gray-500 hover:text-gray-700" @click="openEdit(chapter)">
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="remove(chapter)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </draggable>

        <Modal v-model="modalOpen" :title="editingChapter ? 'Edit Chapter' : 'Add Chapter'">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <FloatingLabelInput id="chapter-title" v-model="form.title" label="Chapter Title" />
                <TextareaInput id="chapter-description" v-model="form.description" label="Description" />
                <SelectInput v-if="editingChapter" id="chapter-status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />
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
