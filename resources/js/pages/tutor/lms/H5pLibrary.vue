<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, PuzzlePieceIcon } from '@heroicons/vue/24/outline'
import api from '../../../services/api'
import { useH5pContentStore } from '../../../stores/h5pContent'
import Modal from '../../../components/common/Modal.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'
import H5pContentCard from '../../../components/lms/H5pContentCard.vue'
import H5pPlayerWidget from '../../../components/lms/H5pPlayerWidget.vue'

const router = useRouter()
const h5pStore = useH5pContentStore()

const loading = ref(true)
const actionError = ref('')
const contents = ref([])

const tutorSubjects = ref([])
const curricula = ref([])

const filters = reactive({ subjectId: '', gradeId: '', curriculumId: '' })

const previewOpen = ref(false)
const previewContentId = ref(null)

const approvedTutorSubjects = computed(() => tutorSubjects.value.filter((entry) => entry.status === 'approved'))

const subjectOptions = computed(() =>
    approvedTutorSubjects.value.map((tutorSubject) => ({ value: String(tutorSubject.subject.id), label: tutorSubject.subject.name })),
)

const gradeOptions = computed(() => {
    const tutorSubject = approvedTutorSubjects.value.find((entry) => String(entry.subject.id) === filters.subjectId)
    return tutorSubject ? tutorSubject.grades.map((grade) => ({ value: String(grade.id), label: grade.name })) : []
})

const curriculumOptions = computed(() => curricula.value.map((curriculum) => ({ value: String(curriculum.id), label: curriculum.name })))

// Subject -> Grade, in that order, is the "grouped by" ask — curriculum is
// shown per-card instead (see H5pContentCard.vue) rather than nested further,
// since a third level would mostly just repeat the same header per card.
const groups = computed(() => {
    const bySubject = new Map()

    for (const item of contents.value) {
        const subjectName = item.subject?.name ?? 'Uncategorized'
        const gradeName = item.grade?.name ?? 'Uncategorized'

        if (!bySubject.has(subjectName)) bySubject.set(subjectName, new Map())
        const byGrade = bySubject.get(subjectName)

        if (!byGrade.has(gradeName)) byGrade.set(gradeName, [])
        byGrade.get(gradeName).push(item)
    }

    return [...bySubject.entries()].map(([subjectName, byGrade]) => ({
        subjectName,
        grades: [...byGrade.entries()].map(([gradeName, items]) => ({ gradeName, items })),
    }))
})

function onSubjectFilterChange(value) {
    filters.subjectId = value
    const stillValid = gradeOptions.value.some((option) => option.value === filters.gradeId)
    if (!stillValid) filters.gradeId = ''
    loadContents()
}

async function loadLookups() {
    const [subjectsRes, curriculaRes] = await Promise.all([api.get('/tutor/subjects'), api.get('/curricula')])
    tutorSubjects.value = subjectsRes.data.subjects
    curricula.value = curriculaRes.data.curricula
}

async function loadContents() {
    contents.value = await h5pStore.fetchLibrary({
        subject_id: filters.subjectId || undefined,
        grade_id: filters.gradeId || undefined,
        curriculum_id: filters.curriculumId || undefined,
    })
}

onMounted(async () => {
    loading.value = true
    await Promise.all([loadLookups(), loadContents()])
    loading.value = false
})

function createNew() {
    router.push({ name: 'tutor.h5p-content.new' })
}

function editContent(item) {
    router.push({ name: 'tutor.h5p-content.edit', params: { id: item.id } })
}

function preview(item) {
    previewContentId.value = item.id
    previewOpen.value = true
}

async function duplicate(item) {
    actionError.value = ''
    try {
        const editorModel = await h5pStore.fetchEditorModel(item.id)
        await h5pStore.saveContent(null, {
            library: editorModel.library,
            params: { params: editorModel.params, metadata: { ...editorModel.metadata, title: `${editorModel.metadata.title} (Copy)` } },
            grade_id: item.grade?.id,
            subject_id: item.subject?.id,
            curriculum_id: item.curriculum?.id,
        })
        await loadContents()
    } catch (error) {
        actionError.value = error.response?.data?.message ?? 'Could not duplicate this content. Please try again.'
    }
}

async function remove(item) {
    if (!confirm(`Delete "${item.title}"? This cannot be undone.`)) return

    actionError.value = ''
    try {
        await h5pStore.deleteContent(item.id)
        contents.value = contents.value.filter((entry) => entry.id !== item.id)
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
                <h1 class="text-body text-2xl font-bold">H5P Content</h1>
                <p class="mt-1 text-muted">Your interactive activities, grouped by subject and grade.</p>
            </div>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
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
            <div class="mt-6 grid grid-cols-1 gap-4 rounded-2xl bg-card p-5 shadow-elevated sm:grid-cols-3">
                <SelectInput
                    id="filter-subject"
                    :model-value="filters.subjectId"
                    label="Subject"
                    :options="[{ value: '', label: 'All Subjects' }, ...subjectOptions]"
                    @update:model-value="onSubjectFilterChange"
                />
                <SelectInput
                    id="filter-grade"
                    :model-value="filters.gradeId"
                    label="Grade"
                    :options="[{ value: '', label: 'All Grades' }, ...gradeOptions]"
                    @update:model-value="(value) => { filters.gradeId = value; loadContents() }"
                />
                <SelectInput
                    id="filter-curriculum"
                    :model-value="filters.curriculumId"
                    label="Curriculum"
                    :options="[{ value: '', label: 'All Curricula' }, ...curriculumOptions]"
                    @update:model-value="(value) => { filters.curriculumId = value; loadContents() }"
                />
            </div>

            <div v-if="contents.length === 0" class="mt-16 flex flex-col items-center text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-card-alt text-muted">
                    <PuzzlePieceIcon class="h-8 w-8" />
                </span>
                <p class="mt-4 text-muted">No H5P activities yet. Create your first one to get started.</p>
            </div>

            <div v-else class="mt-8 space-y-10">
                <section v-for="group in groups" :key="group.subjectName">
                    <h2 class="text-body text-lg font-bold">{{ group.subjectName }}</h2>
                    <div v-for="gradeGroup in group.grades" :key="gradeGroup.gradeName" class="mt-4">
                        <h3 class="text-sm font-semibold text-muted">{{ gradeGroup.gradeName }}</h3>
                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <H5pContentCard
                                v-for="item in gradeGroup.items"
                                :key="item.id"
                                :item="item"
                                :export-url="exportUrl(item.id)"
                                @preview="preview"
                                @edit="editContent"
                                @duplicate="duplicate"
                                @delete="remove"
                            />
                        </div>
                    </div>
                </section>
            </div>
        </template>

        <Modal v-model="previewOpen" title="Preview">
            <H5pPlayerWidget v-if="previewContentId" :content-id="previewContentId" />
        </Modal>
    </div>
</template>
