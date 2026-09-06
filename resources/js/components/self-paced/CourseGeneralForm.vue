<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '../../services/api'
import { useSelfPacedCoursesStore } from '../../stores/selfPacedCourses'
import FloatingLabelInput from '../forms/FloatingLabelInput.vue'
import SelectInput from '../forms/SelectInput.vue'
import RichTextEditor from '../lms/RichTextEditor.vue'

const DIFFICULTY_OPTIONS = [
    { value: 'beginner', label: 'Beginner' },
    { value: 'intermediate', label: 'Intermediate' },
    { value: 'advanced', label: 'Advanced' },
]

const props = defineProps({
    course: { type: Object, required: true },
})

const emit = defineEmits(['updated'])

const store = useSelfPacedCoursesStore()
const subjects = ref([])
const grades = ref([])
const categories = ref([])
const saving = ref(false)
const error = ref('')
const success = ref(false)
const thumbnailInput = ref(null)
const uploadingThumbnail = ref(false)

const subjectOptions = computed(() => subjects.value.map((subject) => ({ value: String(subject.id), label: subject.name })))
const gradeOptions = computed(() => grades.value.map((grade) => ({ value: String(grade.id), label: grade.name })))
const categoryOptions = computed(() => categories.value.map((category) => ({ value: String(category.id), label: category.name })))

const form = reactive({
    title: props.course.title,
    subtitle: props.course.subtitle ?? '',
    description: props.course.description ?? '',
    promo_description: props.course.promo_description ?? '',
    subject_id: props.course.subject?.id ? String(props.course.subject.id) : '',
    grade_id: props.course.grade?.id ? String(props.course.grade.id) : '',
    service_category_id: props.course.category?.id ? String(props.course.category.id) : '',
    difficulty: props.course.difficulty ?? '',
    language: props.course.language ?? '',
    estimated_duration_minutes: props.course.estimated_duration_minutes ?? '',
})

onMounted(async () => {
    const [subjectsRes, gradesRes, categoriesRes] = await Promise.all([
        api.get('/tutor/subjects'),
        api.get('/grades'),
        api.get('/service-categories'),
    ])
    // /tutor/subjects returns TutorSubjectResource rows ({id: tutorSubjectId,
    // status, subject: {id, name}}), not flat Subject records — unwrap to
    // the underlying subject (and only ones this tutor is approved to
    // teach) before using its id/name.
    subjects.value = subjectsRes.data.subjects
        .filter((tutorSubject) => tutorSubject.status === 'approved')
        .map((tutorSubject) => tutorSubject.subject)
    grades.value = gradesRes.data.grades
    categories.value = categoriesRes.data.categories
})

async function save() {
    saving.value = true
    error.value = ''
    success.value = false

    try {
        const updated = await store.updateCourse(props.course.id, {
            title: form.title,
            subtitle: form.subtitle || null,
            description: form.description || null,
            promo_description: form.promo_description || null,
            subject_id: form.subject_id ? Number(form.subject_id) : null,
            grade_id: form.grade_id ? Number(form.grade_id) : null,
            service_category_id: form.service_category_id ? Number(form.service_category_id) : null,
            difficulty: form.difficulty || null,
            language: form.language || null,
            estimated_duration_minutes: form.estimated_duration_minutes || null,
        })
        emit('updated', updated)
        success.value = true
    } catch (err) {
        const errors = err.response?.data?.errors
        error.value = errors ? Object.values(errors).flat().join(' ') : (err.response?.data?.message ?? 'Something went wrong.')
    } finally {
        saving.value = false
    }
}

async function uploadThumbnail(event) {
    const file = event.target.files?.[0]
    if (!file) return

    uploadingThumbnail.value = true
    try {
        const updated = await store.updateCourse(props.course.id, { thumbnail: file })
        emit('updated', updated)
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Could not upload the thumbnail.'
    } finally {
        uploadingThumbnail.value = false
        if (thumbnailInput.value) thumbnailInput.value.value = ''
    }
}
</script>

<template>
    <form class="max-w-2xl space-y-4 rounded-2xl bg-card p-6 shadow-elevated" novalidate @submit.prevent="save">
        <p v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ error }}</p>
        <p v-if="success" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">Saved.</p>

        <div>
            <p class="mb-2 text-sm font-semibold text-body">Thumbnail</p>
            <div class="flex items-center gap-4">
                <img
                    v-if="course.thumbnail_path"
                    :src="`/storage/${course.thumbnail_path}`"
                    class="h-20 w-32 rounded-lg object-cover"
                    alt="Course thumbnail"
                />
                <div v-else class="flex h-20 w-32 items-center justify-center rounded-lg bg-card-alt text-xs text-muted">No thumbnail</div>
                <div>
                    <input ref="thumbnailInput" type="file" accept="image/*" class="hidden" @change="uploadThumbnail" />
                    <button
                        type="button"
                        :disabled="uploadingThumbnail"
                        class="rounded-full border border-border px-4 py-2 text-sm font-semibold text-body disabled:opacity-40"
                        @click="thumbnailInput.click()"
                    >
                        {{ uploadingThumbnail ? 'Uploading…' : 'Upload Thumbnail' }}
                    </button>
                </div>
            </div>
        </div>

        <FloatingLabelInput id="course-title" v-model="form.title" label="Course Title" />
        <FloatingLabelInput id="course-subtitle" v-model="form.subtitle" label="Subtitle" />

        <div>
            <p class="mb-1 text-sm font-semibold text-body">Description</p>
            <RichTextEditor v-model="form.description" />
        </div>
        <div>
            <p class="mb-1 text-sm font-semibold text-body">Promotional Description</p>
            <RichTextEditor v-model="form.promo_description" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <SelectInput id="course-subject" v-model="form.subject_id" label="Subject" :options="subjectOptions" />
            <SelectInput id="course-grade" v-model="form.grade_id" label="Grade" :options="gradeOptions" />
            <SelectInput id="course-category" v-model="form.service_category_id" label="Category" :options="categoryOptions" />
            <SelectInput id="course-difficulty" v-model="form.difficulty" label="Difficulty" :options="DIFFICULTY_OPTIONS" />
            <FloatingLabelInput id="course-language" v-model="form.language" label="Language" />
            <FloatingLabelInput
                id="course-duration"
                v-model="form.estimated_duration_minutes"
                type="number"
                label="Estimated Duration (minutes)"
            />
        </div>

        <div class="flex justify-end pt-2">
            <button
                type="submit"
                :disabled="saving"
                class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
        </div>
    </form>
</template>
