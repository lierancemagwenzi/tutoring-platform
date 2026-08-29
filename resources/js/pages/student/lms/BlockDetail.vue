<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { blockRegistry } from '../../../lms/blockRegistry'
import { useBookingStore } from '../../../stores/booking'
import MermaidRender from '../../../components/lms/MermaidRender.vue'
import KatexRender from '../../../components/lms/KatexRender.vue'
import StudentSubmissionPanel from '../../../components/lms/StudentSubmissionPanel.vue'
import StudentAttemptPanel from '../../../components/lms/StudentAttemptPanel.vue'

const LEARNING_ACTIVITY_TYPES = [
    'assignment',
    'homework',
    'practice',
    'assessment',
    'project',
    'lab',
    'reflection',
    'reading',
    'external_activity',
]

// Mirrors the Submission Engine's eligible types (App\Models\LessonBlock::supportsSubmissions) —
// Practice, Assessment, Reading and External Activity don't accept submissions in this phase.
const SUBMISSION_ELIGIBLE_TYPES = ['assignment', 'homework', 'project', 'lab', 'reflection']

const route = useRoute()
const router = useRouter()
const store = useBookingStore()

const loading = ref(true)
const errorMessage = ref('')
const block = ref(null)

const label = computed(() => blockRegistry[block.value?.block_type]?.label ?? block.value?.block_type)
const isLearningActivity = computed(() => LEARNING_ACTIVITY_TYPES.includes(block.value?.block_type))
const supportsSubmissions = computed(
    () =>
        SUBMISSION_ELIGIBLE_TYPES.includes(block.value?.block_type) &&
        block.value?.learning_activity?.submission_type &&
        block.value.learning_activity.submission_type !== 'none',
)
// Mirrors the Attempt Engine's eligible types (App\Models\LessonBlock::attemptProvider).
const supportsAttempts = computed(() => ['h5p', 'quiz'].includes(block.value?.block_type))

const isPlayableMedia = (item) => ['video_upload', 'mp3', 'wav'].includes(item.media_type)
const isImage = (item) => item.media_type === 'image'
const isAudio = (item) => ['mp3', 'wav'].includes(item.media_type)

onMounted(async () => {
    try {
        block.value = await store.fetchLessonBlock(route.params.bookingId, route.params.blockId)
    } catch (error) {
        errorMessage.value = error.response?.data?.message ?? 'This content is not available.'
    } finally {
        loading.value = false
    }
})

function backToBooking() {
    router.push(`/student/bookings/${route.params.bookingId}`)
}
</script>

<template>
    <div class="p-8">
        <button type="button" class="text-accent text-sm font-semibold" @click="backToBooking">&larr; Back to Booking</button>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <p v-else-if="errorMessage" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ errorMessage }}</p>

        <template v-else-if="block">
            <div class="mt-4 flex items-center gap-3">
                <span class="bg-accent/10 text-accent flex h-10 w-10 shrink-0 items-center justify-center rounded-full">
                    <component :is="blockRegistry[block.block_type]?.icon" class="h-5 w-5" />
                </span>
                <div>
                    <h1 class="text-ink text-2xl font-bold">{{ block.title || label }}</h1>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ label }}</span>
                </div>
            </div>

            <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm">
                <div v-if="block.block_type === 'rich_text'" class="prose prose-sm max-w-none" v-html="block.content?.html" />

                <KatexRender v-else-if="block.block_type === 'math'" :latex="block.content?.latex" :display-mode="block.content?.display_mode" />

                <MermaidRender v-else-if="block.block_type === 'mermaid'" :diagram="block.content?.diagram" />

                <div v-else-if="block.block_type === 'media'" class="space-y-3">
                    <p v-if="!block.media_items?.length" class="text-sm text-gray-500">No media has been added yet.</p>
                    <div v-for="item in block.media_items ?? []" :key="item.id">
                        <img v-if="isImage(item)" :src="item.url" :alt="item.title" class="max-w-full rounded-xl" />
                        <video v-else-if="isPlayableMedia(item) && !isAudio(item)" :src="item.url" controls class="w-full rounded-xl" />
                        <audio v-else-if="isAudio(item)" :src="item.url" controls class="w-full" />
                        <a v-else :href="item.url" target="_blank" rel="noopener" class="text-accent text-sm font-semibold underline">
                            {{ item.title || item.original_name || 'Open file' }}
                        </a>
                    </div>
                </div>

                <p v-else-if="block.block_type === 'h5p' && block.h5p_content" class="text-sm text-gray-500">
                    Launch the activity below to begin.
                </p>
                <p v-else-if="block.block_type === 'h5p'" class="text-sm text-gray-500">No H5P activity has been selected for this block yet.</p>

                <p v-else-if="block.block_type === 'quiz' && block.quiz" class="text-sm text-gray-500">
                    {{ block.quiz.questions?.length ?? 0 }} questions. Launch the activity below to begin.
                </p>
                <p v-else-if="block.block_type === 'quiz'" class="text-sm text-gray-500">No quiz has been configured for this block yet.</p>

                <div v-else-if="isLearningActivity && block.learning_activity" class="space-y-3">
                    <p v-if="block.learning_activity.description" class="text-sm text-gray-600">{{ block.learning_activity.description }}</p>

                    <div
                        v-if="block.learning_activity.instructions?.html"
                        class="prose prose-sm max-w-none"
                        v-html="block.learning_activity.instructions.html"
                    />

                    <div v-if="block.learning_activity.attachments?.length" class="space-y-1.5">
                        <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Attachments</p>
                        <a
                            v-for="attachment in block.learning_activity.attachments"
                            :key="attachment.id"
                            :href="attachment.url"
                            target="_blank"
                            rel="noopener"
                            class="text-accent block text-sm font-semibold underline"
                        >
                            {{ attachment.title || attachment.original_name || 'Open file' }}
                        </a>
                    </div>

                    <p class="text-xs text-gray-400">Your tutor will guide you through this during the session.</p>
                </div>

                <p v-else class="text-sm text-gray-500">This content is available. Your tutor will guide you through it during the session.</p>
            </div>

            <StudentSubmissionPanel v-if="supportsSubmissions" :booking-id="route.params.bookingId" :block="block" />
            <StudentAttemptPanel v-if="supportsAttempts" :booking-id="route.params.bookingId" :block="block" />
        </template>
    </div>
</template>
