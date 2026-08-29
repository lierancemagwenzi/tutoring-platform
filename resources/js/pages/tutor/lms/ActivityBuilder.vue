<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import draggable from 'vuedraggable'
import { Bars3Icon, DocumentIcon, MusicalNoteIcon, PencilSquareIcon, PhotoIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useLearningActivitiesStore } from '../../../stores/learningActivities'
import { useActivityAttachmentsStore } from '../../../stores/activityAttachments'
import Modal from '../../../components/common/Modal.vue'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../../components/forms/TextareaInput.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'
import FileUploadInput from '../../../components/forms/FileUploadInput.vue'
import TagInput from '../../../components/forms/TagInput.vue'
import RichTextEditor from '../../../components/lms/RichTextEditor.vue'

const ACTIVITY_TYPE_LABELS = {
    assignment: 'Assignment',
    homework: 'Homework',
    practice: 'Practice',
    assessment: 'Assessment',
    project: 'Project',
    lab: 'Lab',
    reflection: 'Reflection',
    reading: 'Reading',
    external_activity: 'External Activity',
}

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const SUBMISSION_TYPE_OPTIONS = [
    { value: 'none', label: 'None' },
    { value: 'text', label: 'Text' },
    { value: 'file_upload', label: 'File Upload' },
    { value: 'text_and_file', label: 'Text + File' },
    { value: 'code', label: 'Code (future)' },
]

const ATTACHMENT_TYPE_OPTIONS = [
    { value: 'pdf', label: 'PDF', accept: '.pdf', icon: DocumentIcon },
    { value: 'image', label: 'Image', accept: '.jpg,.jpeg,.png,.webp,.gif', icon: PhotoIcon },
    { value: 'video_upload', label: 'Video', accept: '.mp4,.mov,.avi,.webm', icon: DocumentIcon },
    { value: 'zip', label: 'ZIP Archive', accept: '.zip', icon: DocumentIcon },
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

const route = useRoute()
const activityStore = useLearningActivitiesStore()
const attachmentStore = useActivityAttachmentsStore()
const activityId = computed(() => Number(route.params.id))

const loading = ref(true)
const saving = ref(false)
const saveError = ref('')
const saveSuccess = ref(false)
const activity = ref(null)
const attachments = ref([])

const form = reactive({
    title: '',
    description: '',
    instructionsHtml: '',
    instructionsJson: null,
    status: 'draft',
    submissionType: 'text',
    maxScore: '',
    // Inner keys stay snake_case (unlike the rest of this form) because they
    // round-trip verbatim through the `settings` JSON column into backend
    // validation (see UpdateLearningActivityRequest's settings.external_url
    // check) — same convention as other free-form JSON blobs in this app
    // (e.g. LessonBlock.content's h5p_content_id).
    settings: {
        is_group_project: false,
        equipment_required: [],
        software_required: [],
        safety_instructions: '',
        practical_notes: '',
        reflection_prompt: '',
        estimated_reading_time_minutes: '',
        required_reading: true,
        time_limit_minutes: '',
        external_url: '',
        button_label: '',
        open_in_new_tab: true,
        embed_enabled: false,
    },
})

const attachmentModalOpen = ref(false)
const editingAttachment = ref(null)
const attachmentForm = reactive({ mediaType: 'pdf', title: '', description: '', file: null, status: 'draft' })
const attachmentFileName = ref('')
const attachmentSaving = ref(false)
const attachmentError = ref('')

const pageTitle = computed(() => ACTIVITY_TYPE_LABELS[activity.value?.type] ?? 'Activity')

function typeMeta(mediaType) {
    return ATTACHMENT_TYPE_OPTIONS.find((option) => option.value === mediaType) ?? ATTACHMENT_TYPE_OPTIONS[0]
}

onMounted(async () => {
    activity.value = await activityStore.fetchActivity(activityId.value)
    attachments.value = activity.value.attachments ?? []
    populateForm(activity.value)
    loading.value = false
})

function populateForm(source) {
    form.title = source.title
    form.description = source.description ?? ''
    form.instructionsHtml = source.instructions?.html ?? ''
    form.instructionsJson = source.instructions?.json ?? null
    form.status = source.status
    form.submissionType = source.submission_type
    form.maxScore = source.max_score ?? ''

    const settings = source.settings ?? {}
    form.settings.is_group_project = settings.is_group_project ?? false
    form.settings.equipment_required = settings.equipment_required ?? []
    form.settings.software_required = settings.software_required ?? []
    form.settings.safety_instructions = settings.safety_instructions ?? ''
    form.settings.practical_notes = settings.practical_notes ?? ''
    form.settings.reflection_prompt = settings.reflection_prompt ?? ''
    form.settings.estimated_reading_time_minutes = settings.estimated_reading_time_minutes ?? ''
    form.settings.required_reading = settings.required_reading ?? true
    form.settings.time_limit_minutes = settings.time_limit_minutes ?? ''
    form.settings.external_url = settings.external_url ?? ''
    form.settings.button_label = settings.button_label ?? ''
    form.settings.open_in_new_tab = settings.open_in_new_tab ?? true
    form.settings.embed_enabled = settings.embed_enabled ?? false
}

function buildPayload() {
    return {
        title: form.title,
        description: form.description || null,
        instructions_html: form.instructionsHtml || null,
        instructions_json: form.instructionsJson,
        status: form.status,
        submission_type: form.submissionType,
        max_score: form.maxScore || null,
        settings: buildSettingsPayload(),
    }
}

function buildSettingsPayload() {
    switch (activity.value?.type) {
        case 'project':
            return {
                is_group_project: form.settings.is_group_project,
            }
        case 'lab':
            return {
                equipment_required: form.settings.equipment_required,
                software_required: form.settings.software_required,
                safety_instructions: form.settings.safety_instructions || null,
                practical_notes: form.settings.practical_notes || null,
            }
        case 'reflection':
            return {
                reflection_prompt: form.settings.reflection_prompt || null,
            }
        case 'reading':
            return {
                estimated_reading_time_minutes: form.settings.estimated_reading_time_minutes || null,
                required_reading: form.settings.required_reading,
            }
        case 'assessment':
            return {
                time_limit_minutes: form.settings.time_limit_minutes || null,
            }
        case 'external_activity':
            return {
                external_url: form.settings.external_url || null,
                button_label: form.settings.button_label || null,
                open_in_new_tab: form.settings.open_in_new_tab,
                embed_enabled: form.settings.embed_enabled,
            }
        default:
            return {}
    }
}

async function save() {
    saving.value = true
    saveError.value = ''
    saveSuccess.value = false

    try {
        activity.value = await activityStore.updateActivity(activityId.value, buildPayload())
        saveSuccess.value = true
    } catch (error) {
        const errors = error.response?.data?.errors
        saveError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}

// --- Attachments ---

function openCreateAttachment() {
    editingAttachment.value = null
    attachmentForm.mediaType = 'pdf'
    attachmentForm.title = ''
    attachmentForm.description = ''
    attachmentForm.file = null
    attachmentForm.status = 'draft'
    attachmentFileName.value = ''
    attachmentError.value = ''
    attachmentModalOpen.value = true
}

function openEditAttachment(item) {
    editingAttachment.value = item
    attachmentForm.mediaType = item.media_type
    attachmentForm.title = item.title
    attachmentForm.description = item.description ?? ''
    attachmentForm.file = null
    attachmentForm.status = item.status
    attachmentFileName.value = item.original_name ?? ''
    attachmentError.value = ''
    attachmentModalOpen.value = true
}

async function saveAttachment() {
    attachmentSaving.value = true
    attachmentError.value = ''

    const payload = {
        media_type: attachmentForm.mediaType,
        title: attachmentForm.title,
        description: attachmentForm.description || null,
        status: attachmentForm.status,
        ...(attachmentForm.file ? { file: attachmentForm.file } : {}),
    }

    try {
        if (editingAttachment.value) {
            const updated = await attachmentStore.updateAttachment(editingAttachment.value.id, payload)
            const index = attachments.value.findIndex((item) => item.id === updated.id)
            if (index !== -1) {
                attachments.value[index] = updated
            }
        } else {
            const created = await attachmentStore.createAttachment(activityId.value, payload)
            attachments.value.push(created)
        }
        attachmentModalOpen.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        attachmentError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        attachmentSaving.value = false
    }
}

async function removeAttachment(item) {
    if (!confirm(`Remove "${item.title}"?`)) {
        return
    }

    try {
        await attachmentStore.deleteAttachment(item.id)
        attachments.value = attachments.value.filter((entry) => entry.id !== item.id)
    } catch (error) {
        saveError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function onReorderAttachments() {
    try {
        await attachmentStore.reorderAttachments(activityId.value, attachments.value.map((item) => item.id))
    } catch (error) {
        saveError.value = error.response?.data?.message ?? 'Could not save the new order. Please try again.'
    }
}
</script>

<template>
    <div class="p-8">
        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <template v-else>
            <button type="button" class="text-accent text-sm font-semibold" @click="$router.back()">&larr; Back to Lesson Builder</button>
            <h1 class="text-ink mt-1 text-2xl font-bold">{{ pageTitle }} Configuration</h1>
            <p class="mt-1 text-gray-500">
                Configure the reusable content for this activity. Availability, completion, attempts, and passing
                score are configured per session, in Manage Lesson Content.
            </p>

            <p v-if="saveError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ saveError }}</p>
            <p v-if="saveSuccess" class="mt-6 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</p>

            <form class="mt-8 max-w-3xl space-y-6" novalidate @submit.prevent="save">
                <section class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">General</h2>
                    <FloatingLabelInput id="activity-title" v-model="form.title" label="Title" />
                    <TextareaInput id="activity-description" v-model="form.description" label="Description" />
                    <SelectInput id="activity-status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />
                </section>

                <section class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Instructions</h2>
                    <RichTextEditor
                        v-model="form.instructionsHtml"
                        @update:json="(json) => (form.instructionsJson = json)"
                    />
                </section>

                <section v-if="activity.type === 'lab'" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Lab Details</h2>
                    <TagInput id="lab-equipment" v-model="form.settings.equipment_required" label="Equipment Required (press Enter to add)" />
                    <TagInput id="lab-software" v-model="form.settings.software_required" label="Software Required (press Enter to add)" />
                    <TextareaInput id="lab-safety" v-model="form.settings.safety_instructions" label="Safety Instructions" />
                    <TextareaInput id="lab-notes" v-model="form.settings.practical_notes" label="Practical Notes" />
                </section>

                <section v-if="activity.type === 'reflection'" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Reflection</h2>
                    <TextareaInput id="reflection-prompt" v-model="form.settings.reflection_prompt" label="Reflection Prompt" />
                </section>

                <section v-if="activity.type === 'reading'" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Reading Details</h2>
                    <FloatingLabelInput
                        id="reading-time"
                        v-model="form.settings.estimated_reading_time_minutes"
                        type="number"
                        label="Estimated Reading Time (minutes)"
                    />
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.settings.required_reading" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                        Required reading
                    </label>
                </section>

                <section v-if="activity.type === 'project'" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Project Details</h2>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.settings.is_group_project" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                        Group project
                    </label>
                </section>

                <section v-if="activity.type === 'assessment'" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Assessment Details</h2>
                    <FloatingLabelInput
                        id="assessment-time-limit"
                        v-model="form.settings.time_limit_minutes"
                        type="number"
                        label="Time Limit (minutes, optional)"
                    />
                </section>

                <section v-if="activity.type === 'external_activity'" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">External Activity</h2>
                    <FloatingLabelInput id="external-url" v-model="form.settings.external_url" label="External URL" />
                    <FloatingLabelInput id="external-button-label" v-model="form.settings.button_label" label="Button Label" />
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.settings.open_in_new_tab" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                        Open in new tab
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.settings.embed_enabled" type="checkbox" class="accent-accent h-4 w-4 rounded" />
                        Embed activity (optional)
                    </label>
                </section>

                <section class="rounded-2xl bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-ink font-bold">Attachments</h2>
                        <button
                            type="button"
                            class="text-accent flex items-center gap-1 text-sm font-semibold"
                            @click="openCreateAttachment"
                        >
                            <PlusIcon class="h-4 w-4" />
                            Add Attachment
                        </button>
                    </div>

                    <p v-if="attachments.length === 0" class="mt-4 text-sm text-gray-500">No attachments yet.</p>

                    <draggable
                        v-else
                        v-model="attachments"
                        item-key="id"
                        handle=".drag-handle"
                        class="mt-4 space-y-2"
                        @end="onReorderAttachments"
                    >
                        <template #item="{ element: item }">
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 px-4 py-3">
                                <span class="drag-handle cursor-grab text-gray-400">
                                    <Bars3Icon class="h-4 w-4" />
                                </span>
                                <component :is="typeMeta(item.media_type).icon" class="text-accent h-4 w-4 shrink-0" />
                                <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900">{{ item.title }}</span>
                                <span class="shrink-0 text-xs text-gray-500">{{ typeMeta(item.media_type).label }}</span>
                                <button type="button" class="shrink-0 text-gray-500 hover:text-gray-700" @click="openEditAttachment(item)">
                                    <PencilSquareIcon class="h-4 w-4" />
                                </button>
                                <button type="button" class="shrink-0 text-red-500 hover:text-red-700" @click="removeAttachment(item)">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                    </draggable>
                </section>

                <section class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="text-ink font-bold">Scoring &amp; Submission</h2>
                    <SelectInput id="submission-type" v-model="form.submissionType" label="Submission Type" :options="SUBMISSION_TYPE_OPTIONS" />
                    <FloatingLabelInput
                        id="max-score"
                        v-model="form.maxScore"
                        type="number"
                        label="Maximum Score (optional, if this activity is scored)"
                    />
                </section>

                <div class="flex justify-end gap-3 pb-4">
                    <router-link to="/tutor/courses" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700">
                        Done
                    </router-link>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </template>

        <Modal v-model="attachmentModalOpen" :title="editingAttachment ? 'Edit Attachment' : 'Add Attachment'">
            <form class="space-y-4" novalidate @submit.prevent="saveAttachment">
                <p v-if="attachmentError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ attachmentError }}</p>

                <SelectInput id="attachment-type" v-model="attachmentForm.mediaType" label="Attachment Type" :options="ATTACHMENT_TYPE_OPTIONS" />
                <FloatingLabelInput id="attachment-title" v-model="attachmentForm.title" label="Title" />
                <TextareaInput id="attachment-description" v-model="attachmentForm.description" label="Description (optional)" />
                <FileUploadInput
                    id="attachment-file"
                    label="File"
                    :hint="typeMeta(attachmentForm.mediaType).accept"
                    :accept="typeMeta(attachmentForm.mediaType).accept"
                    :file-name="attachmentForm.file?.name ?? attachmentFileName"
                    @select="(file) => { attachmentForm.file = file; attachmentFileName = file.name }"
                />
                <SelectInput v-if="editingAttachment" id="attachment-status" v-model="attachmentForm.status" label="Status" :options="STATUS_OPTIONS" />

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700" @click="attachmentModalOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="attachmentSaving"
                        class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ attachmentSaving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
