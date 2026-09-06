<script setup>
import { computed, onMounted, ref } from 'vue'
import { TrashIcon } from '@heroicons/vue/24/outline'
import { useSubmissionsStore } from '../../stores/submissions'
import SelectInput from '../forms/SelectInput.vue'
import FileUploadInput from '../forms/FileUploadInput.vue'
import RichTextEditor from './RichTextEditor.vue'

const STATUS_LABELS = {
    draft: 'Draft',
    submitted: 'Submitted',
    under_review: 'Under Review',
    returned: 'Returned for Revision',
    graded: 'Graded',
}

const STATUS_CLASSES = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-amber-100 text-amber-700',
    under_review: 'bg-amber-100 text-amber-700',
    returned: 'bg-red-100 text-red-700',
    graded: 'bg-green-100 text-green-700',
}

const ATTACHMENT_TYPE_OPTIONS = [
    { value: 'pdf', label: 'PDF' },
    { value: 'doc', label: 'Word (.doc)' },
    { value: 'docx', label: 'Word (.docx)' },
    { value: 'ppt', label: 'PowerPoint (.ppt)' },
    { value: 'pptx', label: 'PowerPoint (.pptx)' },
    { value: 'xls', label: 'Excel (.xls)' },
    { value: 'xlsx', label: 'Excel (.xlsx)' },
    { value: 'zip', label: 'ZIP Archive' },
    { value: 'image', label: 'Image' },
    { value: 'mp3', label: 'Audio (MP3)' },
    { value: 'wav', label: 'Audio (WAV)' },
]

const props = defineProps({
    bookingId: { type: [String, Number], required: true },
    block: { type: Object, required: true },
})

const store = useSubmissionsStore()

const loading = ref(true)
const actionError = ref('')
const submissions = ref([])
const draftText = ref('')
const draftJson = ref(null)
const saving = ref(false)
const submitting = ref(false)

const attachmentMediaType = ref('pdf')
const attachmentFile = ref(null)
const attachmentFileName = ref('')
const attachmentUploading = ref(false)

const submissionType = computed(() => props.block.learning_activity?.submission_type ?? 'none')
const needsText = computed(() => ['text', 'text_and_file'].includes(submissionType.value))
const needsFile = computed(() => ['file_upload', 'text_and_file'].includes(submissionType.value))

const current = computed(() => submissions.value[0] ?? null)
const history = computed(() => submissions.value.slice(1))
const isDraft = computed(() => current.value?.status === 'draft')

onMounted(async () => {
    await load()
    loading.value = false
})

async function load() {
    submissions.value = await store.fetchSubmissions(props.bookingId, props.block.id)
    if (isDraft.value) {
        draftText.value = current.value.submission_text?.html ?? ''
        draftJson.value = current.value.submission_text?.json ?? null
    }
}

async function startAttempt() {
    actionError.value = ''
    try {
        const created = await store.startOrResumeDraft(props.bookingId, props.block.id)
        submissions.value.unshift(created)
        draftText.value = created.submission_text?.html ?? ''
        draftJson.value = created.submission_text?.json ?? null
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    }
}

async function saveDraft() {
    saving.value = true
    actionError.value = ''
    try {
        const updated = await store.updateDraft(current.value.id, {
            submission_text_html: draftText.value || null,
            submission_text_json: draftJson.value,
        })
        submissions.value[0] = updated
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    } finally {
        saving.value = false
    }
}

async function submitAttempt() {
    submitting.value = true
    actionError.value = ''
    try {
        await saveDraft()
        const updated = await store.submitDraft(current.value.id)
        submissions.value[0] = updated
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        submitting.value = false
    }
}

async function addAttachment() {
    if (!attachmentFile.value) return

    attachmentUploading.value = true
    actionError.value = ''
    try {
        const created = await store.addAttachment(current.value.id, {
            media_type: attachmentMediaType.value,
            file: attachmentFile.value,
        })
        submissions.value[0].student_attachments.push(created)
        attachmentFile.value = null
        attachmentFileName.value = ''
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        attachmentUploading.value = false
    }
}

async function removeAttachment(attachment) {
    try {
        await store.removeAttachment(attachment.id)
        submissions.value[0].student_attachments = submissions.value[0].student_attachments.filter(
            (item) => item.id !== attachment.id,
        )
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-card-alt text-muted'
}
</script>

<template>
    <div class="mt-6 rounded-2xl bg-card p-6 shadow-elevated">
        <h2 class="text-body font-bold">Your Submission</h2>

        <p v-if="actionError" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="loading" class="mt-6 flex justify-center py-8">
            <div class="border-amber h-8 w-8 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="!current" class="mt-4">
            <p class="text-sm text-muted">You haven't started this yet.</p>
            <button
                type="button"
                class="bg-amber mt-4 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
                @click="startAttempt"
            >
                Start Submission
            </button>
        </div>

        <template v-else>
            <div class="mt-4 flex items-center gap-2">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(current.status)">
                    {{ statusLabel(current.status) }}
                </span>
                <span class="text-xs text-muted">Attempt {{ current.attempt_number }}</span>
            </div>

            <div v-if="isDraft" class="mt-4 space-y-4">
                <RichTextEditor v-if="needsText" v-model="draftText" @update:json="(json) => (draftJson = json)" />

                <div v-if="needsFile" class="space-y-3">
                    <p v-if="!current.student_attachments?.length" class="text-sm text-muted">No files attached yet.</p>
                    <div v-for="attachment in current.student_attachments ?? []" :key="attachment.id" class="flex items-center justify-between rounded-xl border border-border px-4 py-3">
                        <span class="truncate text-sm font-medium text-body">{{ attachment.original_name }}</span>
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeAttachment(attachment)">
                            <TrashIcon class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="flex items-end gap-3">
                        <div class="w-40">
                            <SelectInput id="attachment-media-type" v-model="attachmentMediaType" label="File Type" :options="ATTACHMENT_TYPE_OPTIONS" />
                        </div>
                        <div class="flex-1">
                            <FileUploadInput
                                id="submission-attachment-file"
                                label=""
                                hint="Add a file"
                                :file-name="attachmentFile?.name ?? attachmentFileName"
                                removable
                                :uploading="attachmentUploading"
                                @select="(file) => { attachmentFile = file; attachmentFileName = file.name }"
                                @remove="attachmentFile = null"
                            />
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-border px-4 py-2.5 text-sm font-semibold text-body disabled:opacity-40"
                            :disabled="!attachmentFile || attachmentUploading"
                            @click="addAttachment"
                        >
                            Add
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="rounded-full border border-border px-5 py-2.5 text-sm font-semibold text-body disabled:opacity-40"
                        :disabled="saving"
                        @click="saveDraft"
                    >
                        {{ saving ? 'Saving…' : 'Save Draft' }}
                    </button>
                    <button
                        type="button"
                        class="bg-amber rounded-full px-6 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="submitting"
                        @click="submitAttempt"
                    >
                        {{ submitting ? 'Submitting…' : 'Submit' }}
                    </button>
                </div>
            </div>

            <div v-else class="mt-4 space-y-4">
                <div v-if="current.submission_text?.html" class="prose prose-sm max-w-none" v-html="current.submission_text.html" />

                <div v-if="current.student_attachments?.length" class="space-y-1.5">
                    <p class="text-xs font-semibold tracking-wide text-muted uppercase">Your Files</p>
                    <a
                        v-for="attachment in current.student_attachments"
                        :key="attachment.id"
                        :href="attachment.url"
                        target="_blank"
                        rel="noopener"
                        class="text-accent block text-sm font-semibold underline"
                    >
                        {{ attachment.original_name }}
                    </a>
                </div>

                <div v-if="current.is_published" class="rounded-xl bg-card-alt p-4">
                    <p class="text-sm font-semibold text-body">
                        Score: {{ current.score }}<span v-if="current.max_score"> / {{ current.max_score }}</span>
                        <span v-if="current.percentage !== null"> ({{ current.percentage }}%)</span>
                    </p>
                    <p v-if="current.passed !== null" class="mt-1 text-sm font-semibold" :class="current.passed ? 'text-green-700' : 'text-red-700'">
                        {{ current.passed ? 'Passed' : 'Not Passed' }}
                    </p>
                    <div v-if="current.feedback_text?.html" class="prose prose-sm mt-3 max-w-none" v-html="current.feedback_text.html" />
                    <div v-if="current.feedback_attachments?.length" class="mt-3 space-y-1.5">
                        <p class="text-xs font-semibold tracking-wide text-muted uppercase">Tutor Files</p>
                        <a
                            v-for="attachment in current.feedback_attachments"
                            :key="attachment.id"
                            :href="attachment.url"
                            target="_blank"
                            rel="noopener"
                            class="text-accent block text-sm font-semibold underline"
                        >
                            {{ attachment.original_name }}
                        </a>
                    </div>
                </div>

                <p v-if="current.status === 'graded'" class="text-sm text-muted">
                    This activity has been graded and no longer accepts submissions.
                </p>
                <button
                    v-else
                    type="button"
                    class="rounded-full border border-border px-5 py-2.5 text-sm font-semibold text-body"
                    @click="startAttempt"
                >
                    Start New Attempt
                </button>
            </div>

            <div v-if="history.length" class="mt-6 border-t border-border pt-4">
                <p class="text-xs font-semibold tracking-wide text-muted uppercase">Previous Attempts</p>
                <div v-for="past in history" :key="past.id" class="mt-2 flex items-center justify-between text-sm">
                    <span class="text-muted">Attempt {{ past.attempt_number }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(past.status)">
                        {{ statusLabel(past.status) }}
                    </span>
                </div>
            </div>
        </template>
    </div>
</template>
