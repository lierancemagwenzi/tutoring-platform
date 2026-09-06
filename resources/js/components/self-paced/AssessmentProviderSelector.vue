<script setup>
import { onMounted, ref, watch } from 'vue'
import { PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import { useSelfPacedSurveyContentsStore } from '../../stores/selfPacedSurveyContents'
import SelectInput from '../forms/SelectInput.vue'

const PROVIDER_OPTIONS = [
    { value: 'surveyjs', label: 'SurveyJS' },
    { value: 'h5p', label: 'H5P' },
]

const props = defineProps({
    provider: { type: String, default: '' },
    providerConfig: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:provider', 'update:providerConfig'])

const store = useSelfPacedCoursesStore()
const surveyStore = useSelfPacedSurveyContentsStore()
const h5pContents = ref([])
const surveyContents = ref([])
const h5pContentId = ref(props.providerConfig?.h5p_content_id ?? '')
const surveyContentId = ref(props.providerConfig?.survey_content_id ? String(props.providerConfig.survey_content_id) : '')

onMounted(async () => {
    // Both scoped to this tutor's self-paced-tagged content only — never
    // Tutor-Led Learning's Quiz/H5P content, and never another tutor's.
    const [h5p, surveys] = await Promise.all([store.fetchH5pContents(), surveyStore.fetchSurveyContents()])
    h5pContents.value = h5p
    surveyContents.value = surveys
})

watch(h5pContentId, (value) => {
    emit('update:providerConfig', value ? { h5p_content_id: value } : {})
})

watch(surveyContentId, (value) => {
    emit('update:providerConfig', value ? { survey_content_id: Number(value) } : {})
})
</script>

<template>
    <div class="space-y-3">
        <SelectInput
            id="assessment-provider"
            :model-value="provider"
            label="Provider"
            :options="PROVIDER_OPTIONS"
            @update:model-value="$emit('update:provider', $event)"
        />

        <div v-if="provider === 'surveyjs'">
            <SelectInput
                id="assessment-survey-content"
                v-model="surveyContentId"
                label="Survey Question Bank"
                :options="
                    surveyContents.map((content) => ({
                        value: String(content.id),
                        label: `${content.title} (${content.grade?.name} · ${content.subject?.name} · ${content.curriculum?.name})`,
                    }))
                "
            />

            <div v-if="surveyContents.length === 0" class="mt-1 text-xs text-muted">
                No survey question banks yet — create one below.
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-4">
                <router-link :to="{ name: 'tutor.self-paced-survey-contents' }" class="text-accent flex items-center gap-1 text-sm font-semibold">
                    <PlusIcon class="h-4 w-4" /> Create or Manage Survey Banks
                </router-link>
                <router-link
                    v-if="surveyContentId"
                    :to="{ name: 'tutor.self-paced-survey-contents.builder', params: { id: surveyContentId } }"
                    class="text-accent flex items-center gap-1 text-sm font-semibold"
                >
                    <PencilSquareIcon class="h-4 w-4" /> Edit Selected Bank
                </router-link>
            </div>
            <p class="mt-1 text-xs text-muted">
                Managing survey banks navigates away from this course editor — you'll return to select one once saved.
            </p>
        </div>

        <div v-else-if="provider === 'h5p'">
            <SelectInput
                id="assessment-h5p-content"
                v-model="h5pContentId"
                label="H5P Content"
                :options="h5pContents.map((content) => ({ value: content.h5p_content_id, label: content.title ?? `Content #${content.h5p_content_id}` }))"
            />

            <div v-if="h5pContents.length === 0" class="mt-1 text-xs text-muted">
                No H5P content tagged for self-paced courses yet — create one below.
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-4">
                <router-link
                    :to="{ name: 'tutor.h5p-content.new', query: { context: 'self_paced' } }"
                    class="text-accent flex items-center gap-1 text-sm font-semibold"
                >
                    <PlusIcon class="h-4 w-4" /> Create New H5P Content
                </router-link>
                <router-link
                    v-if="h5pContentId"
                    :to="{ name: 'tutor.h5p-content.edit', params: { id: h5pContentId }, query: { context: 'self_paced' } }"
                    class="text-accent flex items-center gap-1 text-sm font-semibold"
                >
                    <PencilSquareIcon class="h-4 w-4" /> Edit Selected Content
                </router-link>
            </div>
            <p class="mt-1 text-xs text-muted">
                Creating content here navigates away from this course editor — you'll return to select it once saved.
            </p>
        </div>
    </div>
</template>
