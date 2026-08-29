<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCoursesStore } from '../../../stores/courses'
import { useSelfPacedCoursesStore } from '../../../stores/selfPacedCourses'
import H5pEditorWidget from '../../../components/lms/H5pEditorWidget.vue'

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()
const selfPacedStore = useSelfPacedCoursesStore()

const contentId = computed(() => route.params.id ?? null)
const lessonBlockId = computed(() => route.query.lessonBlockId ?? null)
const isSelfPaced = computed(() => route.query.context === 'self_paced')

const editorWidget = ref(null)
const saving = ref(false)
const actionError = ref('')

async function save() {
    saving.value = true
    actionError.value = ''

    try {
        const result = await editorWidget.value.save()

        if (isSelfPaced.value) {
            // Tags this content so it appears in the self-paced Assessment
            // provider dropdown — never mixed with Tutor-Led Learning's H5P
            // content, which never gets this tag.
            await selfPacedStore.registerH5pContent({
                h5p_content_id: result.contentId,
                title: result.metadata?.title ?? null,
            })
            router.back()
        } else if (lessonBlockId.value) {
            const block = await coursesStore.fetchBlock(lessonBlockId.value)
            await coursesStore.updateBlock(lessonBlockId.value, {
                block_type: 'h5p',
                status: block.status,
                h5p_content_id: result.contentId,
            })
            router.push({ name: 'tutor.lesson-blocks.h5p', params: { id: lessonBlockId.value } })
        } else {
            router.push({ name: 'tutor.h5p-content.edit', params: { id: result.contentId } })
        }
    } catch (error) {
        actionError.value = error.message ?? 'Could not save this activity. Please try again.'
    } finally {
        saving.value = false
    }
}

function onSaveError(detail) {
    actionError.value = detail?.message ?? 'Could not save this activity. Please try again.'
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <button type="button" class="text-accent text-sm font-semibold" @click="$router.back()">&larr; Back</button>
                <h1 class="text-ink mt-1 text-2xl font-bold">{{ contentId ? 'Edit H5P Activity' : 'Create H5P Activity' }}</h1>
            </div>
            <button
                type="button"
                :disabled="saving"
                class="bg-amber rounded-full px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save' }}
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div class="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm">
            <H5pEditorWidget ref="editorWidget" :content-id="contentId" class="min-h-[70vh] p-6" @save-error="onSaveError" />
        </div>
    </div>
</template>
