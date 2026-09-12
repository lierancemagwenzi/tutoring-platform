<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTutorServicesStore } from '../../stores/tutorServices'
import SelectInput from '../../components/forms/SelectInput.vue'
import FloatingLabelInput from '../../components/forms/FloatingLabelInput.vue'
import TextareaInput from '../../components/forms/TextareaInput.vue'
import CheckboxGroup from '../../components/forms/CheckboxGroup.vue'

// ZAR is the only currency PayFast (the sole payment gateway) can settle —
// see OrderService's currency checks — so it's the only option here too.
const CURRENCY_OPTIONS = [{ value: 'ZAR', label: 'ZAR' }]

const VISIBILITY_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'paused', label: 'Paused' },
]

const route = useRoute()
const router = useRouter()
const store = useTutorServicesStore()

const serviceId = computed(() => (route.params.id ? Number(route.params.id) : null))
const isEditing = computed(() => serviceId.value !== null)

const loading = ref(true)
const saving = ref(false)
const formError = ref('')

const form = reactive({
    subjectId: '',
    gradeId: '',
    serviceCategoryId: '',
    sessionFormatId: '',
    title: '',
    description: '',
    price: '',
    currency: 'ZAR',
    sessionDurationMinutes: '',
    sessionsIncluded: '',
    validityPeriodDays: '',
    maxStudentsPerSession: '',
    learningResourceIds: [],
    assessmentTypeIds: [],
    curriculumIds: [],
    visibility: 'draft',
})

const subjectOptions = computed(() => store.subjects.map((subject) => ({ value: String(subject.id), label: subject.name })))
// Grades are per tutor-subject approval — only offer the grades this
// tutor was actually approved to teach the currently-selected subject
// for (see StoreServiceRequest's grade_id validation).
const gradeOptions = computed(() => {
    const tutorSubject = store.approvedTutorSubjects.find((ts) => String(ts.subject_id) === String(form.subjectId))
    return tutorSubject ? tutorSubject.grades.map((grade) => ({ value: String(grade.id), label: grade.name })) : []
})

watch(
    () => form.subjectId,
    () => {
        if (!gradeOptions.value.some((option) => option.value === form.gradeId)) {
            form.gradeId = ''
        }
    },
)
const categoryOptions = computed(() => store.categories.map((category) => ({ value: String(category.id), label: category.name })))
const sessionFormatOptions = computed(() => store.sessionFormats.map((format) => ({ value: String(format.id), label: format.name })))
const learningResourceOptions = computed(() => store.learningResources.map((resource) => ({ value: resource.id, label: resource.name })))
const assessmentTypeOptions = computed(() => store.assessmentTypes.map((type) => ({ value: type.id, label: type.name })))
const curriculumOptions = computed(() => store.curricula.map((curriculum) => ({ value: curriculum.id, label: curriculum.name })))

onMounted(async () => {
    await store.fetchLookups()

    if (isEditing.value) {
        const service = await store.fetchService(serviceId.value)
        form.subjectId = String(service.subject.id)
        form.gradeId = service.grade?.id ? String(service.grade.id) : ''
        form.serviceCategoryId = String(service.category.id)
        form.sessionFormatId = String(service.session_format.id)
        form.title = service.title
        form.description = service.description
        form.price = String(service.price)
        form.currency = service.currency
        form.sessionDurationMinutes = String(service.session_duration_minutes)
        form.sessionsIncluded = String(service.sessions_included)
        form.validityPeriodDays = String(service.validity_period_days)
        form.maxStudentsPerSession = String(service.max_students_per_session)
        form.learningResourceIds = service.learning_resources.map((resource) => resource.id)
        form.assessmentTypeIds = service.assessment_types.map((type) => type.id)
        form.curriculumIds = service.curricula.map((curriculum) => curriculum.id)
        form.visibility = service.visibility
    }

    loading.value = false
})

function buildPayload() {
    return {
        subject_id: Number(form.subjectId),
        grade_id: Number(form.gradeId),
        service_category_id: Number(form.serviceCategoryId),
        session_format_id: Number(form.sessionFormatId),
        title: form.title,
        description: form.description,
        price: Number(form.price),
        currency: form.currency,
        session_duration_minutes: Number(form.sessionDurationMinutes),
        sessions_included: Number(form.sessionsIncluded),
        validity_period_days: Number(form.validityPeriodDays),
        max_students_per_session: Number(form.maxStudentsPerSession),
        learning_resource_ids: form.learningResourceIds,
        assessment_type_ids: form.assessmentTypeIds,
        curriculum_ids: form.curriculumIds,
        visibility: form.visibility,
    }
}

async function save() {
    saving.value = true
    formError.value = ''

    try {
        if (isEditing.value) {
            await store.updateService(serviceId.value, buildPayload())
        } else {
            await store.createService(buildPayload())
        }
        router.push('/tutor/services')
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
        <h1 class="text-body text-2xl font-bold">{{ isEditing ? 'Edit Service' : 'Add Service' }}</h1>
        <p class="mt-1 text-muted">Define what you're offering, how it's delivered, and what students get.</p>

        <div v-if="loading" class="flex justify-center py-24">
            <div class="border-amber h-10 w-10 animate-spin rounded-full border-4 border-t-transparent" />
        </div>

        <form v-else class="mt-8 max-w-3xl space-y-6" novalidate @submit.prevent="save">
            <p v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{{ formError }}</p>

            <section class="space-y-5 rounded-2xl bg-card p-6 shadow-elevated">
                <h2 class="text-body font-bold">Basic Information</h2>
                <SelectInput id="subject" v-model="form.subjectId" label="Subject" :options="subjectOptions" />
                <SelectInput
                    id="grade"
                    v-model="form.gradeId"
                    label="Grade"
                    :options="gradeOptions"
                    :disabled="!form.subjectId"
                />
                <SelectInput id="category" v-model="form.serviceCategoryId" label="Service Category" :options="categoryOptions" />
                <FloatingLabelInput id="title" v-model="form.title" label="Service Title" />
                <TextareaInput id="description" v-model="form.description" label="Description" />
            </section>

            <section class="space-y-5 rounded-2xl bg-card p-6 shadow-elevated">
                <h2 class="text-body font-bold">Pricing</h2>
                <FloatingLabelInput id="price" v-model="form.price" type="number" label="Price" />
                <SelectInput id="currency" v-model="form.currency" label="Currency" :options="CURRENCY_OPTIONS" />
            </section>

            <section class="space-y-5 rounded-2xl bg-card p-6 shadow-elevated">
                <h2 class="text-body font-bold">Lesson Configuration</h2>
                <FloatingLabelInput id="duration" v-model="form.sessionDurationMinutes" type="number" label="Session Duration (minutes)" />
                <FloatingLabelInput id="sessions" v-model="form.sessionsIncluded" type="number" label="Number of Sessions Included" />
                <FloatingLabelInput id="validity" v-model="form.validityPeriodDays" type="number" label="Validity Period (days)" />
                <FloatingLabelInput
                    id="max-students"
                    v-model="form.maxStudentsPerSession"
                    type="number"
                    label="Maximum Students Per Session"
                />
                <SelectInput id="session-format" v-model="form.sessionFormatId" label="Session Format" :options="sessionFormatOptions" />
            </section>

            <section class="rounded-2xl bg-card p-6 shadow-elevated">
                <CheckboxGroup
                    id="learning-resources"
                    v-model="form.learningResourceIds"
                    label="Learning Resources"
                    :options="learningResourceOptions"
                />
            </section>

            <section class="rounded-2xl bg-card p-6 shadow-elevated">
                <CheckboxGroup id="assessments" v-model="form.assessmentTypeIds" label="Assessments" :options="assessmentTypeOptions" />
            </section>

            <section class="rounded-2xl bg-card p-6 shadow-elevated">
                <CheckboxGroup id="curriculum" v-model="form.curriculumIds" label="Curriculum" :options="curriculumOptions" />
            </section>

            <section class="space-y-5 rounded-2xl bg-card p-6 shadow-elevated">
                <h2 class="text-body font-bold">Visibility</h2>
                <SelectInput id="visibility" v-model="form.visibility" label="Visibility" :options="VISIBILITY_OPTIONS" />
            </section>

            <div class="flex justify-end gap-3 pb-4">
                <router-link to="/tutor/services" class="rounded-full border border-border px-5 py-2.5 font-semibold text-body">
                    Cancel
                </router-link>
                <button
                    type="submit"
                    :disabled="saving"
                    class="bg-amber rounded-full px-6 py-2.5 font-semibold text-white shadow-elevated transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </form>
    </div>
</template>
