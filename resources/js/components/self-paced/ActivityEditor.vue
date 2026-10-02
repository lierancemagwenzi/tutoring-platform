<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { EyeIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import { useH5pContentStore } from '../../stores/h5pContent'
import Modal from '../common/Modal.vue'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'
import TextareaInput from '../forms/TextareaInput.vue'
import SelectInput from '../forms/SelectInput.vue'
import RichTextEditor from '../lms/RichTextEditor.vue'
import MermaidRender from '../lms/MermaidRender.vue'
import KatexRender from '../lms/KatexRender.vue'
import ContentItemsEditor from '../lms/content/ContentItemsEditor.vue'
import MediaItemFormModal from '../lms/content/MediaItemFormModal.vue'
import { CONTENT_ITEM_TYPES, contentItemsSnapshot } from '../../lms/contentItemTypes'

const TYPE_OPTIONS = [
    { value: 'content', label: 'Content (text, maths, diagrams & files)' },
    { value: 'rich_text', label: 'Rich Text' },
    { value: 'video', label: 'Video' },
    { value: 'pdf', label: 'PDF' },
    { value: 'image_gallery', label: 'Image Gallery' },
    { value: 'audio', label: 'Audio' },
    { value: 'document', label: 'Document' },
    { value: 'presentation', label: 'Presentation' },
    { value: 'spreadsheet', label: 'Spreadsheet' },
    { value: 'download', label: 'Download' },
    { value: 'external_resource', label: 'External Resource' },
    { value: 'mermaid', label: 'Mermaid Diagram' },
    { value: 'katex', label: 'KaTeX' },
    { value: 'assignment', label: 'Assignment' },
    { value: 'homework', label: 'Homework' },
    { value: 'reading', label: 'Reading' },
    { value: 'h5p', label: 'H5P Activity' },
]

const ATTACHMENT_TYPES = ['video', 'pdf', 'image_gallery', 'audio', 'document', 'presentation', 'spreadsheet', 'download']

const MEDIA_TYPE_OPTIONS = {
    video: [
        { value: 'video_upload', label: 'Upload video' },
        { value: 'video_youtube', label: 'YouTube link' },
        { value: 'video_vimeo', label: 'Vimeo link' },
    ],
    pdf: [{ value: 'pdf', label: 'PDF file' }],
    image_gallery: [{ value: 'image', label: 'Image' }],
    audio: [
        { value: 'mp3', label: 'MP3' },
        { value: 'wav', label: 'WAV' },
    ],
    document: [
        { value: 'doc', label: 'DOC' },
        { value: 'docx', label: 'DOCX' },
        { value: 'txt', label: 'TXT' },
    ],
    presentation: [
        { value: 'ppt', label: 'PPT' },
        { value: 'pptx', label: 'PPTX' },
    ],
    spreadsheet: [
        { value: 'xls', label: 'XLS' },
        { value: 'xlsx', label: 'XLSX' },
        { value: 'csv', label: 'CSV' },
    ],
    download: [{ value: 'zip', label: 'ZIP' }],
}

const props = defineProps({
    moduleId: { type: Number, required: true },
    courseId: { type: Number, required: true },
    activity: { type: Object, default: null },
})

const emit = defineEmits(['saved', 'cancelled'])

const store = useSelfPacedCoursesStore()
const h5pStore = useH5pContentStore()
const saving = ref(false)
const error = ref('')
const current = ref(props.activity)
// Captured once, before current() starts tracking the just-created record —
// distinguishes "creating a new activity" (close automatically once its one
// attachment is uploaded) from "editing an existing one" (stay open so the
// tutor can review/add/remove multiple attachments).
const wasEditingExisting = props.activity !== null

const form = reactive({
    type: props.activity?.type ?? 'content',
    title: props.activity?.title ?? '',
    description: props.activity?.description ?? '',
    required: props.activity?.required ?? true,
    html: props.activity?.content?.html ?? '',
    diagram: props.activity?.content?.syntax ?? '',
    latex: props.activity?.content?.latex ?? '',
    url: props.activity?.content?.url ?? '',
    instructions: props.activity?.content?.instructions ?? '',
    h5pContentId: props.activity?.content?.h5p_content_id ?? '',
    // A plain deep copy so editing doesn't mutate the list we were handed.
    items: JSON.parse(JSON.stringify(props.activity?.content?.items ?? [])),
});

// --- Content activities: an ordered list of text/maths/diagram/file items.
// File items point at this activity's attachments, which can only be
// uploaded once the activity exists — so adding the first file saves it.
const itemsEditor = ref(null)
const mediaModalOpen = ref(false)
const editingMedia = ref(null)
const insertAt = ref(null)
const savedItemsSnapshot = ref(contentItemsSnapshot(form.items))
const mediaById = computed(() => Object.fromEntries((current.value?.attachments ?? []).map((attachment) => [attachment.id, attachment])))
const itemsDirty = computed(() => form.type === 'content' && contentItemsSnapshot(form.items) !== savedItemsSnapshot.value)

async function openAddMedia(index) {
    if (!current.value) {
        if (!form.title.trim()) {
            error.value = 'Give the activity a title before adding files.'
            return
        }
        const created = await persist()
        if (!created) return
    }
    insertAt.value = index
    editingMedia.value = null
    mediaModalOpen.value = true
}

function openEditMedia(item) {
    editingMedia.value = mediaById.value[item.media_item_id]
    mediaModalOpen.value = true
}

function saveMedia(payload, existing) {
    const { status, ...attachment } = payload
    return existing ? store.updateAttachment(existing.id, attachment) : store.addAttachment(current.value.id, attachment)
}

function onMediaSaved(attachment) {
    const others = (current.value.attachments ?? []).filter((item) => item.id !== attachment.id)
    current.value = { ...current.value, attachments: [...others, attachment] }
    if (!editingMedia.value) itemsEditor.value?.addMediaItem(attachment.id, insertAt.value)
}

const isAttachmentType = computed(() => ATTACHMENT_TYPES.includes(form.type))
const newAttachmentMediaType = ref('')
const newAttachmentFile = ref(null)
const newAttachmentUrl = ref('')
const newAttachmentInput = ref(null)
const uploadingAttachment = ref(false)

// Only H5P content classified under this course's exact Subject+Grade is
// eligible (Self-Paced courses have no curriculum to also match on) —
// mirrors how the Tutor-Led lesson block picker scopes H5pManager.vue.
const h5pContents = ref([])
const h5pContentsLoading = ref(false)

async function loadH5pContents() {
    h5pContentsLoading.value = true
    try {
        const course = await store.fetchCourse(props.courseId)
        h5pContents.value = await h5pStore.fetchLibrary({ subject_id: course.subject?.id, grade_id: course.grade?.id })
    } finally {
        h5pContentsLoading.value = false
    }
}

watch(
    () => form.type,
    (type) => {
        if (type === 'h5p' && h5pContents.value.length === 0 && !h5pContentsLoading.value) {
            loadH5pContents()
        }
    },
    { immediate: true },
)

function contentPayload() {
    switch (form.type) {
        case 'rich_text':
            return { html: form.html };
        case 'mermaid':
            return { syntax: form.diagram };
        case 'katex':
            return { latex: form.latex };
        case 'external_resource':
            return { url: form.url };
        case 'assignment':
        case 'homework':
        case 'reading':
            return { instructions: form.instructions };
        case 'h5p':
            return { h5p_content_id: form.h5pContentId };
        case 'content':
            return { items: form.items.map((item) => ({ ...item })) };
        default:
            return null;
    }
}

/**
 * Create or update the activity. Resolves with the saved activity, or null
 * if it failed (the error is shown).
 */
async function persist() {
    saving.value = true
    error.value = ''

    const payload = {
        title: form.title,
        description: form.description || null,
        required: form.required,
        content: contentPayload(),
    }

    try {
        current.value = current.value
            ? await store.updateActivity(current.value.id, payload)
            : await store.createActivity(props.moduleId, { ...payload, type: form.type })
        if (form.type === 'content') {
            savedItemsSnapshot.value = contentItemsSnapshot(form.items)
        }
        return current.value
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors ? [...new Set(Object.values(errors).flat())].join(' ') : (err.response?.data?.message ?? 'Something went wrong.')
        return null
    } finally {
        saving.value = false
    }
}

async function save() {
    const saved = await persist()

    // Attachment-backed types keep the modal open so files can be
    // attached to the now-existing activity; everything else is done.
    if (saved && !isAttachmentType.value) {
        emit('saved', saved)
    }
}

function pickFile() {
    newAttachmentInput.value.click()
}

async function addAttachment(event) {
    const file = event?.target?.files?.[0] ?? null
    if (file) newAttachmentFile.value = file

    if (!newAttachmentMediaType.value || (!newAttachmentFile.value && !newAttachmentUrl.value)) {
        return
    }

    uploadingAttachment.value = true
    try {
        const attachment = await store.addAttachment(current.value.id, {
            media_type: newAttachmentMediaType.value,
            title: form.title,
            file: newAttachmentFile.value,
            external_url: newAttachmentFile.value ? null : newAttachmentUrl.value,
        })
        current.value = { ...current.value, attachments: [...(current.value.attachments ?? []), attachment] }
        newAttachmentFile.value = null
        newAttachmentUrl.value = ''
        if (newAttachmentInput.value) newAttachmentInput.value.value = ''

        // Creating a new activity is a one-shot "add the file, you're
        // done" flow — close automatically instead of waiting for an
        // extra click. Editing an existing activity stays open so the
        // tutor can keep managing its attachments.
        if (!wasEditingExisting) {
            finishAttachments()
        }
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Could not add the attachment.'
    } finally {
        uploadingAttachment.value = false
    }
}

async function removeAttachment(attachment) {
    await store.deleteAttachment(attachment.id)
    current.value = { ...current.value, attachments: (current.value.attachments ?? []).filter((item) => item.id !== attachment.id) }
}

function finishAttachments() {
    if (itemsDirty.value && !confirm('You have unsaved changes to this activity. Close without saving?')) return
    emit('saved', current.value)
}

function previewUrl(attachment) {
    return attachment.url ?? attachment.external_url
}
</script>

<template>
    <Modal
        :model-value="true"
        :title="activity ? 'Edit Activity' : 'Add Learning Activity'"
        :size="form.type === 'content' ? 'xl' : 'md'"
        @update:model-value="current ? finishAttachments() : $emit('cancelled')"
    >
        <form class="space-y-4 pt-2" novalidate @submit.prevent="save">
            <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>

            <SelectInput
                v-if="!current"
                id="activity-type"
                v-model="form.type"
                label="Activity Type"
                :options="TYPE_OPTIONS"
            />
            <p v-else class="text-sm text-muted">Type: <span class="font-semibold capitalize">{{ form.type.replace('_', ' ') }}</span></p>

            <FloatingLabelInput id="activity-title" v-model="form.title" label="Title" />
            <TextareaInput id="activity-description" v-model="form.description" label="Description (optional)" :rows="2" />

            <label class="flex items-center gap-2 text-sm text-body">
                <input v-model="form.required" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                Required for module completion
            </label>

            <div v-if="form.type === 'content'" class="space-y-3">
                <p class="text-sm font-semibold text-body">Items</p>
                <p class="text-xs text-muted">Add as many text, maths, diagram and file items as you need, in any order.</p>
                <ContentItemsEditor ref="itemsEditor" v-model="form.items" :media-by-id="mediaById" @add-media="openAddMedia" @edit-media="openEditMedia" />
                <div v-if="form.items.length" class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-muted">Add at the end:</span>
                    <button
                        v-for="(meta, type) in CONTENT_ITEM_TYPES"
                        :key="type"
                        type="button"
                        class="flex items-center gap-1 rounded-full border border-border px-3 py-1.5 text-xs font-semibold text-body hover:border-accent"
                        @click="itemsEditor?.addItem(type)"
                    >
                        <component :is="meta.icon" class="text-accent h-3.5 w-3.5" />
                        {{ meta.label }}
                    </button>
                </div>
            </div>

            <div v-else-if="form.type === 'rich_text'">
                <p class="mb-1 text-sm font-semibold text-body">Content</p>
                <RichTextEditor v-model="form.html" />
            </div>

            <div v-else-if="form.type === 'mermaid'">
                <TextareaInput id="activity-mermaid" v-model="form.diagram" label="Mermaid Syntax" :rows="5" />
                <div class="mt-2 rounded-xl border border-border bg-card-alt p-4">
                    <MermaidRender :diagram="form.diagram" />
                </div>
            </div>

            <div v-else-if="form.type === 'katex'">
                <TextareaInput id="activity-latex" v-model="form.latex" label="LaTeX" :rows="3" />
                <div class="mt-2 rounded-xl border border-border bg-card-alt p-4">
                    <KatexRender :latex="form.latex" :display-mode="true" />
                </div>
            </div>

            <FloatingLabelInput v-else-if="form.type === 'external_resource'" id="activity-url" v-model="form.url" label="Resource URL" />

            <TextareaInput
                v-else-if="['assignment', 'homework', 'reading'].includes(form.type)"
                id="activity-instructions"
                v-model="form.instructions"
                label="Instructions"
                :rows="4"
            />

            <div v-else-if="form.type === 'h5p'">
                <SelectInput
                    id="activity-h5p-content"
                    v-model="form.h5pContentId"
                    label="H5P Content"
                    :options="h5pContents.map((content) => ({ value: content.id, label: content.title }))"
                />

                <div v-if="!h5pContentsLoading && h5pContents.length === 0" class="mt-1 text-xs text-muted">
                    No H5P content matches this course's subject and grade yet — create one below.
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-4">
                    <router-link
                        :to="{ name: 'tutor.h5p-content.new', query: { context: 'self_paced', selfPacedCourseId: courseId } }"
                        class="text-accent flex items-center gap-1 text-sm font-semibold"
                    >
                        <PlusIcon class="h-4 w-4" /> Create New H5P Content
                    </router-link>
                    <router-link
                        v-if="form.h5pContentId"
                        :to="{ name: 'tutor.h5p-content.edit', params: { id: form.h5pContentId }, query: { context: 'self_paced', selfPacedCourseId: courseId } }"
                        class="text-accent flex items-center gap-1 text-sm font-semibold"
                    >
                        <PencilSquareIcon class="h-4 w-4" /> Edit Selected Content
                    </router-link>
                </div>
                <p class="mt-1 text-xs text-muted">
                    Creating content here navigates away from this course editor — you'll return to select it once saved.
                </p>
            </div>

            <div v-if="isAttachmentType && current" class="space-y-3 rounded-xl border border-border p-4">
                <p class="text-sm font-semibold text-body">Attachments</p>

                <ul v-if="current.attachments?.length" class="space-y-2">
                    <li v-for="attachment in current.attachments" :key="attachment.id" class="flex items-center justify-between gap-3 text-sm">
                        <span class="truncate">{{ attachment.title || attachment.original_name || attachment.external_url }}</span>
                        <span class="flex shrink-0 items-center gap-3">
                            <a
                                :href="previewUrl(attachment)"
                                target="_blank"
                                rel="noopener"
                                class="text-muted hover:text-accent"
                                title="Preview"
                            >
                                <EyeIcon class="h-4 w-4" />
                            </a>
                            <button type="button" class="text-muted hover:text-red-600" title="Remove" @click="removeAttachment(attachment)">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </span>
                    </li>
                </ul>

                <div class="flex flex-wrap items-end gap-2">
                    <SelectInput
                        id="new-attachment-media-type"
                        v-model="newAttachmentMediaType"
                        label="Media Type"
                        :options="MEDIA_TYPE_OPTIONS[form.type] ?? []"
                    />
                    <template v-if="['video_youtube', 'video_vimeo'].includes(newAttachmentMediaType)">
                        <FloatingLabelInput id="new-attachment-url" v-model="newAttachmentUrl" label="Video URL" />
                        <button type="button" class="rounded-full border border-border px-4 py-2 text-sm font-semibold" @click="addAttachment()">
                            Add
                        </button>
                    </template>
                    <template v-else>
                        <input ref="newAttachmentInput" type="file" class="hidden" @change="addAttachment" />
                        <button
                            type="button"
                            :disabled="!newAttachmentMediaType || uploadingAttachment"
                            class="rounded-full border border-border px-4 py-2 text-sm font-semibold disabled:opacity-40"
                            @click="pickFile"
                        >
                            {{ uploadingAttachment ? 'Uploading…' : 'Upload File' }}
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button
                    type="button"
                    class="rounded-full border border-border px-5 py-2.5 font-semibold text-body"
                    @click="current ? finishAttachments() : $emit('cancelled')"
                >
                    {{ current ? 'Close' : 'Cancel' }}
                </button>
                <button
                    type="submit"
                    :disabled="saving"
                    class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </form>
    </Modal>

    <MediaItemFormModal v-model="mediaModalOpen" :save="saveMedia" :item="editingMedia" @saved="onMediaSaved" />
</template>
