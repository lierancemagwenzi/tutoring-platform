<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { TrashIcon } from '@heroicons/vue/24/outline'
import { useSubmissionsStore } from '../../stores/submissions'
import SelectInput from '../../components/forms/SelectInput.vue'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'
import FileUploadInput from '../../components/forms/FileUploadInput.vue'
import RichTextEditor from '../../components/lms/RichTextEditor.vue'

const STATUS_LABELS = {
    submitted: 'Submitted',
    under_review: 'Under Review',
    returned: 'Returned for Revision',
    graded: 'Graded',
}

const STATUS_CLASSES = {
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

const route = useRoute()
const router = useRouter()
const store = useSubmissionsStore()

const loading = ref(true)
const errorMessage = ref('')
const actionError = ref('')
const submission = ref(null)

const scoreForm = ref('')
const feedbackHtml = ref('')
const feedbackJson = ref(null)
const grading = ref(false)

const attachmentMediaType = ref('pdf')
const attachmentFile = ref(null)
const attachmentFileName = ref('')
const attachmentUploading = ref(false)

const canReview = computed(() => submission.value?.status === 'submitted')
const canReturn = computed(() => ['submitted', 'under_review'].includes(submission.value?.status))
const canPublish = computed(() => submission.value?.status === 'graded' && !submission.value?.published_at)

onMounted(async () => {
    await load()
})

async function load() {
    loading.value = true
    try {
        submission.value = await store.fetchSubmission(route.params.id)
        scoreForm.value = submission.value.score ?? ''
        feedbackHtml.value = submission.value.feedback_text?.html ?? ''
        feedbackJson.value = submission.value.feedback_text?.json ?? null
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This submission could not be found.'
    } finally {
        loading.value = false
    }
}

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-card-alt text-muted'
}

async function markUnderReview() {
    actionError.value = ''
    try {
        submission.value = await store.markUnderReview(submission.value.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function returnForRevision() {
    if (!confirm('Return this submission to the student for revision?')) return

    actionError.value = ''
    try {
        submission.value = await store.returnForRevision(submission.value.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function saveGrade() {
    grading.value = true
    actionError.value = ''
    try {
        submission.value = await store.gradeSubmission(submission.value.id, {
            score: scoreForm.value,
            feedback_text_html: feedbackHtml.value || null,
            feedback_text_json: feedbackJson.value,
        })
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        grading.value = false
    }
}

async function publishResults() {
    actionError.value = ''
    try {
        submission.value = await store.publishSubmission(submission.value.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}

async function addFeedbackAttachment() {
    if (!attachmentFile.value) return

    attachmentUploading.value = true
    actionError.value = ''
    try {
        const created = await store.addFeedbackAttachment(submission.value.id, {
            media_type: attachmentMediaType.value,
            file: attachmentFile.value,
        })
        submission.value.feedback_attachments.push(created)
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

async function removeFeedbackAttachment(attachment) {
    try {
        await store.removeFeedbackAttachment(attachment.id)
        submission.value.feedback_attachments = submission.value.feedback_attachments.filter((item) => item.id !== attachment.id)
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Something went wrong. Please try again.'
    }
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="router.back()">&larr; Back</button>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="submission">
            <div class="mt-4 flex items-center gap-3">
                <h1 class="text-body text-2xl font-bold">{{ submission.student.first_name }} {{ submission.student.last_name }}</h1>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClasses(submission.status)">
                    {{ statusLabel(submission.status) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted">
                Attempt {{ submission.attempt_number }} &middot; Submitted {{ new Date(submission.submitted_at).toLocaleString() }}
            </p>

            <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <section class="rounded-2xl bg-card p-6 shadow-elevated">
                        <h2 class="text-body font-bold">Submission</h2>
                        <div v-if="submission.submission_text?.html" class="prose prose-sm mt-3 max-w-none" v-html="submission.submission_text.html" />
                        <p v-else class="mt-3 text-sm text-muted">No text was submitted.</p>

                        <div v-if="submission.student_attachments?.length" class="mt-4 space-y-1.5">
                            <p class="text-xs font-semibold tracking-wide text-muted uppercase">Files</p>
                            <a
                                v-for="attachment in submission.student_attachments"
                                :key="attachment.id"
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener"
                                class="text-accent block text-sm font-semibold underline"
                            >
                                {{ attachment.original_name }}
                            </a>
                        </div>
                    </section>

                    <section class="flex flex-wrap gap-3 rounded-2xl bg-card p-6 shadow-elevated">
                        <button
                            v-if="canReview"
                            type="button"
                            class="rounded-full border border-border px-5 py-2.5 text-sm font-semibold text-body"
                            @click="markUnderReview"
                        >
                            Mark Under Review
                        </button>
                        <button
                            v-if="canReturn"
                            type="button"
                            class="rounded-full border border-border px-5 py-2.5 text-sm font-semibold text-red-600"
                            @click="returnForRevision"
                        >
                            Return for Revision
                        </button>
                        <button
                            v-if="canPublish"
                            type="button"
                            class="bg-amber rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
                            @click="publishResults"
                        >
                            Publish Results
                        </button>
                        <p v-if="submission.published_at" class="text-sm text-green-700">Results published to student.</p>
                    </section>
                </div>

                <section class="space-y-4 rounded-2xl bg-card p-6 shadow-elevated">
                    <h2 class="text-body font-bold">Grade &amp; Feedback</h2>

                    <FloatingLabelInput
                        id="score"
                        v-model="scoreForm"
                        type="number"
                        :label="submission.max_score ? `Score (out of ${submission.max_score})` : 'Score'"
                    />
                    <p v-if="submission.passing_score" class="text-xs text-muted">Passing score: {{ submission.passing_score }}</p>

                    <RichTextEditor v-model="feedbackHtml" @update:json="(json) => (feedbackJson = json)" />

                    <div class="space-y-2">
                        <p class="text-xs font-semibold tracking-wide text-muted uppercase">Feedback Files</p>
                        <div
                            v-for="attachment in submission.feedback_attachments ?? []"
                            :key="attachment.id"
                            class="flex items-center justify-between rounded-xl border border-border px-4 py-3"
                        >
                            <span class="truncate text-sm font-medium text-body">{{ attachment.original_name }}</span>
                            <button type="button" class="text-red-500 hover:text-red-700" @click="removeFeedbackAttachment(attachment)">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="flex items-end gap-3">
                            <div class="w-40">
                                <SelectInput id="feedback-media-type" v-model="attachmentMediaType" label="File Type" :options="ATTACHMENT_TYPE_OPTIONS" />
                            </div>
                            <div class="flex-1">
                                <FileUploadInput
                                    id="feedback-attachment-file"
                                    label=""
                                    hint="e.g. annotated PDF or solution sheet"
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
                                @click="addFeedbackAttachment"
                            >
                                Add
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="button"
                            class="bg-amber rounded-full px-6 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="grading || scoreForm === ''"
                            @click="saveGrade"
                        >
                            {{ grading ? 'Saving…' : 'Save Grade' }}
                        </button>
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
