<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
    ArrowDownTrayIcon,
    DocumentDuplicateIcon,
    EyeIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline'
import { useCoursesStore } from '../../../stores/courses'
import { useH5pContentStore } from '../../../stores/h5pContent'
import Modal from '../../../components/common/Modal.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'
import H5pPlayerWidget from '../../../components/lms/H5pPlayerWidget.vue'

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()
const h5pStore = useH5pContentStore()
const lessonBlockId = computed(() => route.params.id)

const loading = ref(true)
const actionError = ref('')
const block = ref(null)
const library = ref([])
const browsing = ref(false)

const previewOpen = ref(false)
const previewContentId = ref(null)

async function load() {
    loading.value = true
    block.value = await coursesStore.fetchBlock(lessonBlockId.value)
    browsing.value = !block.value.h5p_content?.id
    if (browsing.value) {
        await loadLibrary()
    }
    loading.value = false
}

onMounted(load)

async function loadLibrary() {
    library.value = await h5pStore.fetchLibrary()
}

function browseExisting() {
    browsing.value = true
    loadLibrary()
}

function createNew() {
    router.push({ name: 'tutor.h5p-content.new', query: { lessonBlockId: lessonBlockId.value } })
}

function editContent(contentId) {
    router.push({ name: 'tutor.h5p-content.edit', params: { id: contentId }, query: { lessonBlockId: lessonBlockId.value } })
}

function preview(contentId) {
    previewContentId.value = contentId
    previewOpen.value = true
}

async function changeStatus(status) {
    actionError.value = ''
    try {
        block.value = await coursesStore.updateBlock(lessonBlockId.value, {
            block_type: 'h5p',
            status,
            h5p_content_id: block.value.h5p_content?.id ?? null,
        })
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function select(contentId) {
    actionError.value = ''
    try {
        block.value = await coursesStore.updateBlock(lessonBlockId.value, {
            block_type: 'h5p',
            status: block.value.status,
            h5p_content_id: contentId,
        })
        browsing.value = false
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function duplicate(item) {
    actionError.value = ''
    try {
        const editorModel = await h5pStore.fetchEditorModel(item.id)
        const created = await h5pStore.saveContent(null, {
            library: editorModel.library,
            params: { params: editorModel.params, metadata: { ...editorModel.metadata, title: `${editorModel.metadata.title} (Copy)` } },
        })
        await loadLibrary()
        await select(created.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not duplicate this content. Please try again.'
    }
}

async function remove(item) {
    if (!confirm(`Delete "${item.title}"? This cannot be undone.`)) {
        return
    }

    actionError.value = ''
    try {
        await h5pStore.deleteContent(item.id)
        library.value = library.value.filter((entry) => entry.id !== item.id)
        if (block.value.h5p_content?.id === item.id) {
            await load()
        }
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not delete this content. Please try again.'
    }
}

function exportUrl(contentId) {
    return h5pStore.exportUrl(contentId)
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <button type="button" class="text-accent text-sm font-semibold" @click="$router.back()">&larr; Back to Lesson Builder</button>
                <h1 class="text-ink mt-1 text-2xl font-bold">H5P Activity</h1>
                <p class="mt-1 text-gray-500">Create a new H5P activity or choose one from your library.</p>
            </div>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="createNew"
            >
                <PlusIcon class="h-4 w-4" />
                Create New
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else>
            <div class="mt-8 flex items-center justify-between rounded-2xl bg-white p-6 shadow-sm">
                <div>
                    <p class="text-sm font-semibold text-gray-500">Status</p>
                    <span
                        class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                        :class="block.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ block.status }}
                    </span>
                </div>
                <div class="w-48">
                    <SelectInput
                        id="h5p-status"
                        :model-value="block.status"
                        label="Change Status"
                        :options="STATUS_OPTIONS"
                        @update:model-value="changeStatus"
                    />
                </div>
            </div>

            <div v-if="!browsing && block.h5p_content" class="mt-8 rounded-2xl bg-white p-6 shadow-sm">
                <p class="text-sm font-semibold text-gray-500">Selected activity</p>
                <h2 class="text-ink mt-1 text-lg font-bold">{{ block.h5p_content.title }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ block.h5p_content.main_library }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700"
                        @click="preview(block.h5p_content.id)"
                    >
                        <EyeIcon class="h-4 w-4" />
                        Preview
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700"
                        @click="editContent(block.h5p_content.id)"
                    >
                        <PencilSquareIcon class="h-4 w-4" />
                        Edit
                    </button>
                    <a
                        :href="exportUrl(block.h5p_content.id)"
                        class="flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Export
                    </a>
                    <button type="button" class="text-accent text-sm font-semibold" @click="browseExisting">
                        Choose a different activity
                    </button>
                </div>
            </div>

            <div v-else class="mt-8">
                <div v-if="!block.h5p_content" class="mb-4 flex items-center justify-between">
                    <h2 class="text-ink text-lg font-bold">Choose from your library</h2>
                </div>

                <div v-if="library.length === 0 && !loading" class="mt-8 flex flex-col items-center text-center">
                    <p class="text-gray-500">No H5P activities yet. Create your first one to get started.</p>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="item in library" :key="item.id" class="rounded-2xl bg-white p-5 shadow-sm">
                        <p class="text-ink truncate font-bold">{{ item.title }}</p>
                        <p class="mt-0.5 text-sm text-gray-500">{{ item.main_library }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="text-accent text-sm font-semibold" @click="select(item.id)">Select</button>
                            <button type="button" class="text-gray-500 hover:text-gray-700" title="Preview" @click="preview(item.id)">
                                <EyeIcon class="h-4 w-4" />
                            </button>
                            <button type="button" class="text-gray-500 hover:text-gray-700" title="Edit" @click="editContent(item.id)">
                                <PencilSquareIcon class="h-4 w-4" />
                            </button>
                            <button type="button" class="text-gray-500 hover:text-gray-700" title="Duplicate" @click="duplicate(item)">
                                <DocumentDuplicateIcon class="h-4 w-4" />
                            </button>
                            <button type="button" class="text-red-500 hover:text-red-700" title="Delete" @click="remove(item)">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <Modal v-model="previewOpen" title="Preview">
            <H5pPlayerWidget v-if="previewContentId" :content-id="previewContentId" />
        </Modal>
    </div>
</template>
