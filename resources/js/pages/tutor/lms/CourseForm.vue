<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCoursesStore } from '../../../stores/courses'
import SelectInput from '../../../components/forms/SelectInput.vue'
import FloatingLabelInput from '../../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../../components/forms/TextareaInput.vue'
import FileUploadInput from '../../../components/forms/FileUploadInput.vue'

const DIFFICULTY_OPTIONS = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'advanced', label: 'Advanced' },
]

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
]

const route = useRoute()
const router = useRouter()
const store = useCoursesStore()

const courseId = computed(() => (route.params.id ? Number(route.params.id) : null))
const isEditing = computed(() => courseId.value !== null)

const loading = ref(true)
const saving = ref(false)
const formError = ref('')

const form = reactive({
    curriculumId: '',
    gradeId: '',
    subjectId: '',
    title: '',
    description: '',
    estimatedDurationMinutes: '',
    difficulty: 'beginner',
    language: 'English',
    status: 'draft',
    thumbnail: null,
    coverImage: null,
})

const thumbnailName = ref('')
const coverImageName = ref('')

const curriculumOptions = computed(() => store.curricula.map((curriculum) => ({ value: String(curriculum.id), label: curriculum.name })))

const subjectOptions = computed(() =>
    store.approvedTutorSubjects.map((tutorSubject) => ({ value: String(tutorSubject.subject.id), label: tutorSubject.subject.name })),
)

const gradeOptions = computed(() => {
    const tutorSubject = store.approvedTutorSubjects.find((entry) => String(entry.subject.id) === form.subjectId)
    return tutorSubject ? tutorSubject.grades.map((grade) => ({ value: String(grade.id), label: grade.name })) : []
})

function onSubjectChange(value) {
    form.subjectId = value
    const stillValid = gradeOptions.value.some((option) => option.value === form.gradeId)
    if (!stillValid) {
        form.gradeId = ''
    }
}

onMounted(async () => {
    await store.fetchLookups()

    if (isEditing.value) {
        const course = await store.fetchCourse(courseId.value)
        form.curriculumId = String(course.curriculum.id)
        form.gradeId = String(course.grade.id)
        form.subjectId = String(course.subject.id)
        form.title = course.title
        form.description = course.description
        form.estimatedDurationMinutes = String(course.estimated_duration_minutes)
        form.difficulty = course.difficulty
        form.language = course.language
        form.status = course.status
        thumbnailName.value = course.thumbnail_url ? course.thumbnail_url.split('/').pop() : ''
        coverImageName.value = course.cover_image_url ? course.cover_image_url.split('/').pop() : ''
    }

    loading.value = false
})

function buildPayload() {
    return {
        curriculum_id: Number(form.curriculumId),
        grade_id: Number(form.gradeId),
        subject_id: Number(form.subjectId),
        title: form.title,
        description: form.description,
        estimated_duration_minutes: Number(form.estimatedDurationMinutes),
        difficulty: form.difficulty,
        language: form.language,
        status: form.status,
        ...(form.thumbnail ? { thumbnail: form.thumbnail } : {}),
        ...(form.coverImage ? { cover_image: form.coverImage } : {}),
    }
}

async function save() {
    saving.value = true
    formError.value = ''

    try {
        if (isEditing.value) {
            await store.updateCourse(courseId.value, buildPayload())
        } else {
            await store.createCourse(buildPayload())
        }
        router.push('/tutor/courses')
    } catch (error) {
        const errors = error.response?.data?.errors
        formError.value = errors
            ? Object.values(errors).flat().join(' ')
            : (error.response?.data?.message ?? 'Something went wrong. Please try again.')
    } finally {
        saving.value = false
    }
}
</script>

<template>
    <div class="p-8">
        <h1 class="text-ink text-2xl font-bold">{{ isEditing ? 'Edit Course' : 'Add Course' }}</h1>
        <p class="mt-1 text-gray-500">Set up the course details. You'll add chapters and lessons next.</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <form v-else class="mt-8 max-w-3xl space-y-6" novalidate @submit.prevent="save">
            <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

            <section v-if="subjectOptions.length === 0" class="rounded-2xl bg-amber-50 p-6 text-sm text-amber-700">
                You don't have any approved subjects yet. Add and get a subject approved before creating a course.
            </section>

            <section class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="text-ink font-bold">Curriculum Alignment</h2>
                <SelectInput
                    id="subject"
                    :model-value="form.subjectId"
                    label="Subject"
                    :options="subjectOptions"
                    @update:model-value="onSubjectChange"
                />
                <SelectInput id="grade" v-model="form.gradeId" label="Grade" :options="gradeOptions" />
                <SelectInput id="curriculum" v-model="form.curriculumId" label="Curriculum" :options="curriculumOptions" />
            </section>

            <section class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="text-ink font-bold">Basic Information</h2>
                <FloatingLabelInput id="title" v-model="form.title" label="Course Title" />
                <TextareaInput id="description" v-model="form.description" label="Description" />
                <FloatingLabelInput
                    id="duration"
                    v-model="form.estimatedDurationMinutes"
                    type="number"
                    label="Estimated Duration (minutes)"
                />
                <SelectInput id="difficulty" v-model="form.difficulty" label="Difficulty Level" :options="DIFFICULTY_OPTIONS" />
                <FloatingLabelInput id="language" v-model="form.language" label="Language" />
            </section>

            <section class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="text-ink font-bold">Media</h2>
                <FileUploadInput
                    id="thumbnail"
                    label="Thumbnail"
                    hint="JPG, JPEG, PNG or WEBP"
                    accept=".jpg,.jpeg,.png,.webp"
                    :file-name="form.thumbnail?.name ?? thumbnailName"
                    @select="(file) => { form.thumbnail = file; thumbnailName = file.name }"
                />
                <FileUploadInput
                    id="cover-image"
                    label="Cover Image"
                    hint="JPG, JPEG, PNG or WEBP"
                    accept=".jpg,.jpeg,.png,.webp"
                    :file-name="form.coverImage?.name ?? coverImageName"
                    @select="(file) => { form.coverImage = file; coverImageName = file.name }"
                />
            </section>

            <section class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="text-ink font-bold">Status</h2>
                <SelectInput id="status" v-model="form.status" label="Status" :options="STATUS_OPTIONS" />
            </section>

            <div class="flex justify-end gap-3 pb-4">
                <router-link to="/tutor/courses" class="rounded-full border border-gray-300 px-5 py-2.5 font-semibold text-gray-700">
                    Cancel
                </router-link>
                <button
                    type="submit"
                    :disabled="saving"
                    class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-sm transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </form>
    </div>
</template>
