<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PlusIcon, QuestionMarkCircleIcon, TrashIcon } from '@heroicons/vue/24/outline'
import api from '../../../services/api'
import { useSelfPacedSurveyContentsStore } from '../../../stores/selfPacedSurveyContents'
import Modal from '../../../components/common/Modal.vue'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import SelectInput from '../../../components/forms/SelectInput.vue'

const router = useRouter()
const store = useSelfPacedSurveyContentsStore()

const loading = ref(true)
const surveyContents = ref([])
const grades = ref([])
const subjects = ref([])
const curricula = ref([])

const createOpen = ref(false)
const creating = ref(false)
const error = ref('')
const form = reactive({ title: '', grade_id: '', subject_id: '', curriculum_id: '' })

async function load() {
    loading.value = true
    const [contents, gradesRes, subjectsRes, curriculaRes] = await Promise.all([
        store.fetchSurveyContents(),
        api.get('/grades'),
        api.get('/tutor/subjects'),
        api.get('/curricula'),
    ])
    surveyContents.value = contents
    grades.value = gradesRes.data.grades
    // /tutor/subjects returns TutorSubjectResource rows ({id: tutorSubjectId,
    // status, subject: {id, name}}), not flat Subject records — unwrap to
    // the underlying subject (and only ones this tutor is approved to
    // teach) before using its id/name.
    subjects.value = subjectsRes.data.subjects
        .filter((tutorSubject) => tutorSubject.status === 'approved')
        .map((tutorSubject) => tutorSubject.subject)
    curricula.value = curriculaRes.data.curricula
    loading.value = false
}

onMounted(load)

function openCreate() {
    form.title = ''
    form.grade_id = ''
    form.subject_id = ''
    form.curriculum_id = ''
    error.value = ''
    createOpen.value = true
}

async function create() {
    creating.value = true
    error.value = ''

    try {
        const created = await store.createSurveyContent({
            title: form.title,
            grade_id: Number(form.grade_id),
            subject_id: Number(form.subject_id),
            curriculum_id: Number(form.curriculum_id),
        })
        router.push(`/tutor/self-paced-survey-contents/${created.id}`)
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message ?? 'Something went wrong.')
    } finally {
        creating.value = false
    }
}

async function remove(surveyContent) {
    if (!confirm(`Delete "${surveyContent.title}"? This cannot be undone.`)) return

    await store.deleteSurveyContent(surveyContent.id)
    surveyContents.value = surveyContents.value.filter((entry) => entry.id !== surveyContent.id)
}
</script>

<template>
    <div class="p-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-body text-2xl font-bold">Survey Question Banks</h1>
                <p class="mt-1 text-muted">Reusable SurveyJS question banks for your self-paced course Assessments.</p>
            </div>
            <button
                type="button"
                class="bg-amber flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-bold text-white shadow-elevated transition hover:brightness-95"
                @click="openCreate"
            >
                <PlusIcon class="h-4 w-4" />
                New Survey
            </button>
        </div>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <div v-else-if="surveyContents.length === 0" class="mt-16 flex flex-col items-center text-center">
            <span class="flex h-16 w-16 items-center justify-center rounded-full bg-card-alt text-muted">
                <QuestionMarkCircleIcon class="h-8 w-8" />
            </span>
            <p class="mt-4 text-muted">No survey question banks yet. Create your first one to get started.</p>
        </div>

        <div v-else class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="surveyContent in surveyContents" :key="surveyContent.id" class="flex flex-col rounded-2xl bg-card p-5 shadow-elevated">
                <router-link :to="`/tutor/self-paced-survey-contents/${surveyContent.id}`" class="flex-1">
                    <p class="text-body font-bold">{{ surveyContent.title }}</p>
                    <p class="mt-1 text-sm text-muted">
                        {{ surveyContent.grade?.name }} &middot; {{ surveyContent.subject?.name }} &middot; {{ surveyContent.curriculum?.name }}
                    </p>
                    <p class="mt-2 text-xs text-muted">
                        {{ surveyContent.questions_count }} question{{ surveyContent.questions_count === 1 ? '' : 's' }}
                    </p>
                </router-link>
                <button
                    type="button"
                    class="mt-3 flex w-fit items-center gap-1 text-xs font-semibold text-red-500 hover:text-red-700"
                    @click="remove(surveyContent)"
                >
                    <TrashIcon class="h-3.5 w-3.5" /> Delete
                </button>
            </div>
        </div>

        <Modal v-model="createOpen" title="New Survey Question Bank">
            <form class="space-y-4" novalidate @submit.prevent="create">
                <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>
                <FloatingLabelInput id="survey-title" v-model="form.title" label="Title" />
                <SelectInput
                    id="survey-grade"
                    v-model="form.grade_id"
                    label="Grade"
                    :options="grades.map((grade) => ({ value: String(grade.id), label: grade.name }))"
                />
                <SelectInput
                    id="survey-subject"
                    v-model="form.subject_id"
                    label="Subject"
                    :options="subjects.map((subject) => ({ value: String(subject.id), label: subject.name }))"
                />
                <SelectInput
                    id="survey-curriculum"
                    v-model="form.curriculum_id"
                    label="Curriculum"
                    :options="curricula.map((curriculum) => ({ value: String(curriculum.id), label: curriculum.name }))"
                />
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-border px-5 py-2.5 font-semibold text-body" @click="createOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="creating || !form.title || !form.grade_id || !form.subject_id || !form.curriculum_id"
                        class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ creating ? 'Creating…' : 'Create' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
