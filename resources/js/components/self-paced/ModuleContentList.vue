<script setup>
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'
import { Bars3Icon, EyeIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import ActivityEditor from './ActivityEditor.vue'
import AssessmentEditor from './AssessmentEditor.vue'

const props = defineProps({
    module: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const store = useSelfPacedCoursesStore()
const error = ref('')

const items = computed({
    get: () =>
        [
            ...props.module.activities.map((activity) => ({ ...activity, kind: 'activity' })),
            ...props.module.assessments.map((assessment) => ({ ...assessment, kind: 'assessment' })),
        ].sort((a, b) => a.position - b.position),
    set: () => {
        // Reordering is persisted explicitly in onReorder(); vuedraggable's
        // v-model needs a setter, but the source of truth stays the module
        // prop from the parent.
    },
})

const editingActivity = ref(undefined)
const editingAssessment = ref(undefined)

function addActivity() {
    editingActivity.value = null
}

function editActivity(activity) {
    editingActivity.value = activity
}

function addAssessment() {
    editingAssessment.value = null
}

function editAssessment(assessment) {
    editingAssessment.value = assessment
}

function onActivitySaved(activity) {
    const activities = editingActivity.value
        ? props.module.activities.map((item) => (item.id === activity.id ? activity : item))
        : [...props.module.activities, activity]
    editingActivity.value = undefined
    emit('changed', { ...props.module, activities })
}

function onAssessmentSaved(assessment) {
    const assessments = editingAssessment.value
        ? props.module.assessments.map((item) => (item.id === assessment.id ? assessment : item))
        : [...props.module.assessments, assessment]
    editingAssessment.value = undefined
    emit('changed', { ...props.module, assessments })
}

async function removeItem(item) {
    if (!confirm(`Delete "${item.title}"?`)) return

    if (item.kind === 'activity') {
        await store.deleteActivity(item.id)
        emit('changed', { ...props.module, activities: props.module.activities.filter((activity) => activity.id !== item.id) })
    } else {
        await store.deleteAssessment(item.id)
        emit('changed', { ...props.module, assessments: props.module.assessments.filter((assessment) => assessment.id !== item.id) })
    }
}

function firstAttachmentUrl(item) {
    const attachment = item.attachments?.[0]
    if (!attachment) return null

    return attachment.file_path ? `/storage/${attachment.file_path}` : attachment.external_url
}

async function onReorder() {
    error.value = ''
    try {
        const updated = await store.reorderModuleContent(
            props.module.id,
            items.value.map((item) => ({ type: item.kind, id: item.id })),
        )
        emit('changed', updated)
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Could not save the new order.'
    }
}
</script>

<template>
    <div>
        <p v-if="error" class="mb-3 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-600">{{ error }}</p>

        <div v-if="items.length === 0" class="rounded-xl bg-gray-50 py-8 text-center text-sm text-gray-500">
            No content yet. Add a Learning Activity or Assessment below.
        </div>

        <draggable v-else v-model="items" item-key="id" handle=".drag-handle" class="space-y-2" @end="onReorder">
            <template #item="{ element: item }">
                <div class="flex items-center gap-3 rounded-xl bg-gray-50 px-4 py-3">
                    <span class="drag-handle cursor-grab text-gray-400">
                        <Bars3Icon class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-ink truncate text-sm font-semibold">{{ item.title }}</p>
                        <p class="text-xs text-gray-500 capitalize">
                            {{ item.kind === 'activity' ? item.type.replace('_', ' ') : `Assessment · ${item.assessment_type.replace('_', ' ')}` }}
                            <span v-if="!item.required"> · optional</span>
                        </p>
                    </div>
                    <a
                        v-if="item.kind === 'activity' && firstAttachmentUrl(item)"
                        :href="firstAttachmentUrl(item)"
                        target="_blank"
                        rel="noopener"
                        class="shrink-0 text-gray-400 hover:text-accent"
                        title="Preview attachment"
                    >
                        <EyeIcon class="h-4 w-4" />
                    </a>
                    <button
                        type="button"
                        class="shrink-0 text-gray-400 hover:text-gray-700"
                        @click="item.kind === 'activity' ? editActivity(item) : editAssessment(item)"
                    >
                        <PencilSquareIcon class="h-4 w-4" />
                    </button>
                    <button type="button" class="shrink-0 text-red-400 hover:text-red-600" @click="removeItem(item)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </draggable>

        <div class="mt-4 flex gap-3">
            <button type="button" class="text-accent flex items-center gap-1 text-sm font-semibold" @click="addActivity">
                <PlusIcon class="h-4 w-4" /> Add Activity
            </button>
            <button type="button" class="text-accent flex items-center gap-1 text-sm font-semibold" @click="addAssessment">
                <PlusIcon class="h-4 w-4" /> Add Assessment
            </button>
        </div>

        <ActivityEditor
            v-if="editingActivity !== undefined"
            :module-id="module.id"
            :activity="editingActivity"
            @saved="onActivitySaved"
            @cancelled="editingActivity = undefined"
        />
        <AssessmentEditor
            v-if="editingAssessment !== undefined"
            :module-id="module.id"
            :assessment="editingAssessment"
            @saved="onAssessmentSaved"
            @cancelled="editingAssessment = undefined"
        />
    </div>
</template>
