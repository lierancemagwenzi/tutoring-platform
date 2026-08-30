<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../../services/api'
import { useCoursesStore } from '../../../stores/courses'
import { useSelfPacedCoursesStore } from '../../../stores/selfPacedCourses'
import SelectInput from '../../../components/forms/SelectInput.vue'
import H5pEditorWidget from '../../../components/lms/H5pEditorWidget.vue'

const route = useRoute()
const router = useRouter()
const coursesStore = useCoursesStore()
const selfPacedStore = useSelfPacedCoursesStore()

const contentId = computed(() => route.params.id ?? null)
const lessonBlockId = computed(() => route.query.lessonBlockId ?? null)
const isSelfPaced = computed(() => route.query.context === 'self_paced')
const selfPacedCourseId = computed(() => route.query.selfPacedCourseId ?? null)

const editorWidget = ref(null)
const saving = ref(false)
const actionError = ref('')

// Every piece of H5P content belongs to exactly one tutor and is classified
// under a Subject/Grade/Curriculum (see App\Models\H5pContentClassification)
// — required here the same way it's required when creating a Course.
const tutorSubjects = ref([])
const curricula = ref([])
const classification = reactive({ subject_id: '', grade_id: '', curriculum_id: '' })

// When authoring content for a specific lesson block, only content matching
// that block's ancestor Course's exact Grade/Subject/Curriculum is eligible
// to attach (see H5pManager.vue/H5pBlockHandler::rules()) — so classification
// is locked to the course's own rather than left freely pickable. This is
// shown as read-only text, not disabled <select>s: the course's Subject/
// Grade may no longer be on the tutor's *current* approved-subjects list
// (e.g. that approval changed after the course was created — see
// StoreH5pContentRequest), in which case it wouldn't even appear as an
// option in those dropdowns to select/display in the first place.
//
// A Self-Paced course (selfPacedCourseId) locks the same way, but only
// Subject+Grade — it has no curriculum, so lockedClassification omits
// `curriculum` in that case and the Curriculum field stays a normal,
// freely-pickable <select> (see curriculumLocked below).
const lockedClassification = ref(null)
const classificationLocked = computed(() => lockedClassification.value !== null)
const curriculumLocked = computed(() => Boolean(lockedClassification.value?.curriculum))

const approvedTutorSubjects = computed(() => tutorSubjects.value.filter((entry) => entry.status === 'approved'))
const subjectOptions = computed(() =>
    approvedTutorSubjects.value.map((tutorSubject) => ({ value: String(tutorSubject.subject.id), label: tutorSubject.subject.name })),
)
const gradeOptions = computed(() => {
    const tutorSubject = approvedTutorSubjects.value.find((entry) => String(entry.subject.id) === classification.subject_id)
    return tutorSubject ? tutorSubject.grades.map((grade) => ({ value: String(grade.id), label: grade.name })) : []
})
const curriculumOptions = computed(() => curricula.value.map((curriculum) => ({ value: String(curriculum.id), label: curriculum.name })))
const classificationComplete = computed(() => classification.subject_id && classification.grade_id && classification.curriculum_id)

function onSubjectChange(value) {
    classification.subject_id = value
    const stillValid = gradeOptions.value.some((option) => option.value === classification.grade_id)
    if (!stillValid) classification.grade_id = ''
}

function onEditorLoaded(existing) {
    if (!existing || classificationLocked.value) return
    classification.subject_id = String(existing.subject_id)
    classification.grade_id = String(existing.grade_id)
    classification.curriculum_id = String(existing.curriculum_id)
}

onMounted(async () => {
    const [subjectsRes, curriculaRes] = await Promise.all([api.get('/tutor/subjects'), api.get('/curricula')])
    tutorSubjects.value = subjectsRes.data.subjects
    curricula.value = curriculaRes.data.curricula

    if (lessonBlockId.value) {
        const block = await coursesStore.fetchBlock(lessonBlockId.value)
        lockedClassification.value = block.course_classification
        classification.subject_id = String(block.course_classification.subject.id)
        classification.grade_id = String(block.course_classification.grade.id)
        classification.curriculum_id = String(block.course_classification.curriculum.id)
    } else if (selfPacedCourseId.value) {
        const course = await selfPacedStore.fetchCourse(selfPacedCourseId.value)
        lockedClassification.value = { subject: course.subject, grade: course.grade }
        classification.subject_id = String(course.subject.id)
        classification.grade_id = String(course.grade.id)
    }
})

async function save() {
    if (!classificationComplete.value) {
        actionError.value = 'Choose a subject, grade, and curriculum before saving.'
        return
    }

    saving.value = true
    actionError.value = ''

    try {
        const result = await editorWidget.value.save({
            subject_id: Number(classification.subject_id),
            grade_id: Number(classification.grade_id),
            curriculum_id: Number(classification.curriculum_id),
            // Tells the backend to validate against this lesson block's or
            // self-paced course's own classification instead of the tutor's
            // current approved-subjects list (see StoreH5pContentRequest) —
            // that course may be classified under a subject/grade the tutor
            // isn't currently approved for if that approval changed since it
            // was created, but it's still the correct classification for
            // content meant to attach to this specific block/course.
            lesson_block_id: lessonBlockId.value ? Number(lessonBlockId.value) : undefined,
            self_paced_course_id: selfPacedCourseId.value ? Number(selfPacedCourseId.value) : undefined,
        })

        if (isSelfPaced.value) {
            // Tags this content so it appears in the self-paced Assessment
            // provider dropdown — never mixed with Tutor-Led Learning's H5P
            // content, which never gets this tag.
            await selfPacedStore.registerH5pContent({
                h5p_content_id: result.id,
                title: result.metadata?.title ?? null,
            })
            router.back()
        } else if (lessonBlockId.value) {
            const block = await coursesStore.fetchBlock(lessonBlockId.value)
            await coursesStore.updateBlock(lessonBlockId.value, {
                block_type: 'h5p',
                status: block.status,
                h5p_content_id: result.id,
            })
            router.push({ name: 'tutor.lesson-blocks.h5p', params: { id: lessonBlockId.value } })
        } else {
            router.push({ name: 'tutor.h5p-content.edit', params: { id: result.id } })
        }
    } catch (error) {
        const errors = error.response?.data?.errors
        actionError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? error.message ?? 'Could not save this activity. Please try again.')
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
                :disabled="saving || !classificationComplete"
                class="bg-amber rounded-full px-6 py-2.5 text-sm font-bold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save' }}
            </button>
        </div>

        <p v-if="actionError" class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ actionError }}</p>

        <div v-if="classificationLocked" class="mt-6 grid grid-cols-1 gap-4 rounded-2xl bg-white p-5 shadow-sm sm:grid-cols-3">
            <div>
                <p class="text-xs font-semibold text-gray-500">Subject</p>
                <p class="mt-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3.5 text-gray-700">{{ lockedClassification.subject.name }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500">Grade</p>
                <p class="mt-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3.5 text-gray-700">{{ lockedClassification.grade.name }}</p>
            </div>
            <div v-if="curriculumLocked">
                <p class="text-xs font-semibold text-gray-500">Curriculum</p>
                <p class="mt-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3.5 text-gray-700">{{ lockedClassification.curriculum.name }}</p>
            </div>
            <SelectInput v-else id="classification-curriculum" v-model="classification.curriculum_id" label="Curriculum" :options="curriculumOptions" />
        </div>
        <div v-else class="mt-6 grid grid-cols-1 gap-4 rounded-2xl bg-white p-5 shadow-sm sm:grid-cols-3">
            <SelectInput
                id="classification-subject"
                :model-value="classification.subject_id"
                label="Subject"
                :options="subjectOptions"
                @update:model-value="onSubjectChange"
            />
            <SelectInput id="classification-grade" v-model="classification.grade_id" label="Grade" :options="gradeOptions" />
            <SelectInput id="classification-curriculum" v-model="classification.curriculum_id" label="Curriculum" :options="curriculumOptions" />
        </div>
        <p v-if="classificationLocked" class="mt-2 text-xs text-gray-500">
            {{ curriculumLocked ? "Locked to match this lesson's course, so the activity stays eligible to attach here."
                : "Subject and Grade are locked to match this self-paced course; choose a Curriculum for this content." }}
        </p>

        <div class="mt-8 overflow-hidden rounded-2xl bg-white shadow-sm">
            <H5pEditorWidget ref="editorWidget" :content-id="contentId" class="min-h-[70vh] p-6" @save-error="onSaveError" @loaded="onEditorLoaded" />
        </div>
    </div>
</template>
