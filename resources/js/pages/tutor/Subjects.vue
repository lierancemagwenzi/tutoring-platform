<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { BookOpenIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { useTutorSubjectsStore } from '../../stores/tutorSubjects'
import SelectInput from '../../components/forms/SelectInput.vue'
import Modal from '../../components/common/Modal.vue'
import { formatGradeLevels } from '../../utils/formatGradeLevels'

const store = useTutorSubjectsStore()

const loading = ref(true)
const saving = ref(false)
const formError = ref('')
const showFormModal = ref(false)
const editingId = ref(null)
const deleteTarget = ref(null)
const deleting = ref(false)

const form = reactive({
    subjectId: '',
    gradeIds: [],
})

const STATUS_LABELS = { pending: 'Pending Review', approved: 'Approved', rejected: 'Rejected' }
const STATUS_CLASSES = {
    pending: 'bg-gray-100 text-gray-600',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
}

function statusLabel(status) {
    return STATUS_LABELS[status] ?? status
}

function statusClasses(status) {
    return STATUS_CLASSES[status] ?? 'bg-gray-100 text-gray-600'
}

const subjectOptions = computed(() => store.subjects.map((subject) => ({ value: String(subject.id), label: subject.name })))

const availableSubjectOptions = computed(() => {
    const assignedIds = new Set(store.tutorSubjects.map((tutorSubject) => tutorSubject.subject_id))
    return subjectOptions.value.filter((option) => !assignedIds.has(Number(option.value)))
})

const isEditing = computed(() => editingId.value !== null)
const editingSubjectName = computed(() => store.tutorSubjects.find((tutorSubject) => tutorSubject.id === editingId.value)?.subject.name)

function gradesLabel(tutorSubject) {
    return formatGradeLevels(tutorSubject.grades.map((grade) => grade.level))
}

onMounted(async () => {
    await Promise.all([store.fetchSubjects(), store.fetchGrades(), store.fetchTutorSubjects()])
    loading.value = false
})

function openAddModal() {
    form.subjectId = ''
    form.gradeIds = []
    editingId.value = null
    formError.value = ''
    showFormModal.value = true
}

function openEditModal(tutorSubject) {
    form.subjectId = String(tutorSubject.subject_id)
    form.gradeIds = tutorSubject.grades.map((grade) => grade.id)
    editingId.value = tutorSubject.id
    formError.value = ''
    showFormModal.value = true
}

function toggleGrade(gradeId) {
    form.gradeIds = form.gradeIds.includes(gradeId)
        ? form.gradeIds.filter((id) => id !== gradeId)
        : [...form.gradeIds, gradeId]
}

async function saveSubject() {
    saving.value = true
    formError.value = ''

    try {
        if (isEditing.value) {
            await store.updateTutorSubject(editingId.value, { grade_ids: form.gradeIds })
        } else {
            await store.addTutorSubject({ subject_id: Number(form.subjectId), grade_ids: form.gradeIds })
        }
        showFormModal.value = false
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}

function confirmDelete(tutorSubject) {
    deleteTarget.value = tutorSubject
}

async function removeSubject() {
    deleting.value = true

    try {
        await store.removeTutorSubject(deleteTarget.value.id)
        deleteTarget.value = null
    } finally {
        deleting.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <h1 class="text-ink text-2xl font-bold">My Subjects</h1>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95"
                @click="openAddModal"
            >
                <PlusIcon class="h-4 w-4" />
                Add Subject
            </button>
        </div>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="store.tutorSubjects.length === 0" class="mt-16 flex flex-col items-center text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <BookOpenIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-gray-500">You haven't added any subjects yet.</p>
            <button
                type="button"
                class="bg-amber mt-6 rounded-full px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-95"
                @click="openAddModal"
            >
                Add Subject
            </button>
        </div>

        <div v-else class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="tutorSubject in store.tutorSubjects"
                :key="tutorSubject.id"
                class="rounded-2xl bg-white p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-2">
                    <p class="text-ink font-bold">{{ tutorSubject.subject.name }}</p>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="statusClasses(tutorSubject.status)">
                        {{ statusLabel(tutorSubject.status) }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">Grades {{ gradesLabel(tutorSubject) }}</p>
                <p v-if="tutorSubject.status === 'rejected'" class="mt-2 text-sm text-red-600">
                    This subject wasn't approved. You can remove it and add it again for review.
                </p>
                <div class="mt-4 flex items-center gap-4">
                    <button type="button" class="text-accent flex items-center gap-1 text-sm font-semibold" @click="openEditModal(tutorSubject)">
                        <PencilSquareIcon class="h-4 w-4" />
                        Edit
                    </button>
                    <button type="button" class="flex items-center gap-1 text-sm font-semibold text-red-600" @click="confirmDelete(tutorSubject)">
                        <TrashIcon class="h-4 w-4" />
                        Remove
                    </button>
                </div>
            </div>
        </div>

        <Modal v-model="showFormModal" :title="isEditing ? 'Edit Subject' : 'Add Subject'">
            <form class="space-y-6" novalidate @submit.prevent="saveSubject">
                <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

                <p v-if="isEditing" class="text-ink font-semibold">{{ editingSubjectName }}</p>
                <SelectInput
                    v-else
                    id="subject-id"
                    v-model="form.subjectId"
                    label="Subject"
                    :options="availableSubjectOptions"
                />

                <div>
                    <p class="mb-2 font-semibold text-gray-700">Grades</p>
                    <div class="grid grid-cols-3 gap-3">
                        <label
                            v-for="grade in store.grades"
                            :key="grade.id"
                            class="flex cursor-pointer items-center gap-2 rounded-xl border border-gray-300 px-3 py-2.5 has-[:checked]:border-accent"
                        >
                            <input
                                type="checkbox"
                                class="accent-accent h-4 w-4 rounded border-gray-300"
                                :checked="form.gradeIds.includes(grade.id)"
                                @change="toggleGrade(grade.id)"
                            />
                            <span class="text-sm text-gray-900">{{ grade.name }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700"
                        @click="showFormModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving || form.gradeIds.length === 0 || (!isEditing && !form.subjectId)"
                        class="bg-amber rounded-full px-5 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ saving ? 'Saving…' : 'Save' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :model-value="deleteTarget !== null" :title="`Remove ${deleteTarget?.subject.name}?`" @update:model-value="deleteTarget = null">
            <p class="text-gray-600">This subject will no longer appear on your profile.</p>
            <template #footer>
                <button
                    type="button"
                    class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700"
                    @click="deleteTarget = null"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="deleting"
                    class="rounded-full bg-red-600 px-5 py-2.5 font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="removeSubject"
                >
                    {{ deleting ? 'Removing…' : 'Remove' }}
                </button>
            </template>
        </Modal>
    </div>
</template>
