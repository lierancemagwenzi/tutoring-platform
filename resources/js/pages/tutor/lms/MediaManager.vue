<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import {
    ArchiveBoxIcon,
    Bars3Icon,
    DocumentIcon,
    MusicalNoteIcon,
    PencilSquareIcon,
    PhotoIcon,
    PlusIcon,
    TrashIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline'
import { useMediaItemsStore } from '../../../stores/mediaItems'
import Modal from '../../../components/common/Modal.vue'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../../components/forms/TextareaInput.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'
import FileUploadInput from '../../../components/forms/FileUploadInput.vue'

const MEDIA_TYPE_OPTIONS = [
    { value: 'pdf', label: 'PDF', accept: '.pdf', icon: DocumentIcon },
    { value: 'image', label: 'Image', accept: '.jpg,.jpeg,.png,.webp,.gif', icon: PhotoIcon },
    { value: 'video_upload', label: 'Uploaded Video', accept: '.mp4,.mov,.avi,.webm', icon: VideoCameraIcon },
    { value: 'video_youtube', label: 'YouTube Video', icon: VideoCameraIcon },
    { value: 'video_vimeo', label: 'Vimeo Video', icon: VideoCameraIcon },
    { value: 'zip', label: 'ZIP Archive', accept: '.zip', icon: ArchiveBoxIcon },
    { value: 'doc', label: 'Word Document (.doc)', accept: '.doc', icon: DocumentIcon },
    { value: 'docx', label: 'Word Document (.docx)', accept: '.docx', icon: DocumentIcon },
    { value: 'ppt', label: 'PowerPoint (.ppt)', accept: '.ppt', icon: DocumentIcon },
    { value: 'pptx', label: 'PowerPoint (.pptx)', accept: '.pptx', icon: DocumentIcon },
    { value: 'xls', label: 'Excel (.xls)', accept: '.xls', icon: DocumentIcon },
    { value: 'xlsx', label: 'Excel (.xlsx)', accept: '.xlsx', icon: DocumentIcon },
    { value: 'csv', label: 'CSV', accept: '.csv', icon: DocumentIcon },
    { value: 'txt', label: 'Text File', accept: '.txt', icon: DocumentIcon },
    { value: 'mp3', label: 'Audio (MP3)', accept: '.mp3', icon: MusicalNoteIcon },
    { value: 'wav', label: 'Audio (WAV)', accept: '.wav', icon: MusicalNoteIcon },
]

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const EXTERNAL_TYPES = ['video_youtube', 'video_vimeo']

const route = useRoute()
const store = useMediaItemsStore()
const lessonBlockId = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const actionError = ref('')
const items = ref([])

const modalOpen = ref(false)
const editingItem = ref(null)
const form = reactive({
    mediaType: 'pdf',
    title: '',
    description: '',
    file: null,
    externalUrl: '',
    status: 'draft',
})
const fileName = ref('')

const isExternal = computed(() => EXTERNAL_TYPES.includes(form.mediaType))

onMounted(async () => {
    items.value = await store.fetchMediaItems(lessonBlockId.value)
    loading.value = false
})

function typeMeta(mediaType) {
    return MEDIA_TYPE_OPTIONS.find((option) => option.value === mediaType) ?? MEDIA_TYPE_OPTIONS[0]
}

function openCreate() {
    editingItem.value = null
    form.mediaType = 'pdf'
    form.title = ''
    form.description = ''
    form.file = null
    form.externalUrl = ''
    form.status = 'draft'
    fileName.value = ''
    modalOpen.value = true
}

function openEdit(item) {
    editingItem.value = item
    form.mediaType = item.media_type
    form.title = item.title
    form.description = item.description ?? ''
    form.file = null
    form.externalUrl = EXTERNAL_TYPES.includes(item.media_type) ? item.url : ''
    form.status = item.status
    fileName.value = item.original_name ?? ''
    modalOpen.value = true
}

function buildPayload() {
    return {
        media_type: form.mediaType,
        title: form.title,
        description: form.description || null,
        status: form.status,
        ...(isExternal.value ? { external_url: form.externalUrl } : {}),
        ...(form.file ? { file: form.file } : {}),
    }
}

async function save() {
    saving.value = true
    actionError.value = ''

    try {
        if (editingItem.value) {
            const updated = await store.updateMediaItem(editingItem.value.id, buildPayload())
            const index = items.value.findIndex((item) => item.id === updated.id)
            if (index !== -1) {
                items.value[index] = updated
            }
        } else {
            const created = await store.createMediaItem(lessonBlockId.value, buildPayload())
            items.value.push(created)
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

async function remove(item) {
    if (!confirm(`Remove "${item.title}"?`)) {
        return
    }

    actionError.value = ''
    try {
        await store.deleteMediaItem(item.id)
        items.value = items.value.filter((entry) => entry.id !== item.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function onReorder() {
    try {
        await store.reorderMediaItems(lessonBlockId.value, items.value.map((item) => item.id))
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not save the new order. Please try again.'
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <button type="button" class="text-accent text-sm font-semibold" @click="$router.back()">&larr; Back to Lesson Builder</button>
                <h1 class="text-ink mt-1 text-2xl font-bold">Media Resources</h1>
                <p class="mt-1 text-gray-500">Upload and arrange the supporting resources for this block.</p>
            </div>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="openCreate"
            >
                <PlusIcon class="h-4 w-4" />
                Add Resource
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="items.length === 0" class="mt-16 flex flex-col items-center text-center">
            <p class="text-gray-500">No resources yet. Add your first resource to get started.</p>
        </div>

        <draggable
            v-else
            v-model="items"
            item-key="id"
            handle=".drag-handle"
            class="mt-8 space-y-3"
            @end="onReorder"
        >
            <template #item="{ element: item }">
                <div class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm">
                    <span class="drag-handle cursor-grab text-gray-400">
                        <Bars3Icon class="h-5 w-5" />
                    </span>

                    <img v-if="item.media_type === 'image'" :src="item.url" :alt="item.title" class="h-12 w-12 shrink-0 rounded-lg object-cover" />
                    <span v-else class="bg-accent/10 text-accent flex h-12 w-12 shrink-0 items-center justify-center rounded-lg">
                        <component :is="typeMeta(item.media_type).icon" class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-ink truncate font-bold">{{ item.title }}</p>
                        <p class="mt-0.5 text-sm text-gray-500">{{ typeMeta(item.media_type).label }}</p>
                    </div>

                    <span
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold capitalize"
                        :class="item.status === 'published' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ item.status }}
                    </span>

                    <a :href="item.url" target="_blank" rel="noopener" class="text-accent shrink-0 text-sm font-semibold">Preview</a>
                    <button type="button" class="shrink-0 text-gray-500 hover:text-gray-700" @click="openEdit(item)">
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="remove(item)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </draggable>

        <Modal v-model="modalOpen" :title="editingItem ? 'Edit Resource' : 'Add Resource'">
            <form class="space-y-4" novalidate @submit.prevent="save">
                <SelectInput id="media-type" v-model="form.mediaType" label="Media Type" :options="MEDIA_TYPE_OPTIONS" />
                <FloatingLabelInput id="media-title" v-model="form.title" label="Title" />
                <TextareaInput id="media-description" v-model="form.description" label="Description (optional)" />

                <FileUploadInput
                    v-if="!isExternal"
                    id="media-file"
                    label="File"
                    :hint="typeMeta(form.mediaType).accept"
                    :accept="typeMeta(form.mediaType).accept"
                    :file-name="form.file?.name ?? fileName"
                    @select="(file) => { form.file = file; fileName = file.name }"
                />
                <FloatingLabelInput v-else id="media-url" v-model="form.externalUrl" label="Video URL" />

                <SelectInput v-if="editingItem" id="media-status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />

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
